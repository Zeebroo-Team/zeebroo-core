<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Business\Models\Business;
use Modules\Pos\Services\Backup\PosBackupExportService;
use Modules\Pos\Services\Backup\PosBackupImportService;

// Builds only the handful of tables these tests touch — the registry skips the rest.
beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');

    Schema::create('file_manager_files', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->string('original_filename');
        $t->string('stored_path');
        $t->timestamps();
    });
    Schema::create('products', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->string('name');
        $t->foreignId('file_manager_file_id')->nullable()->constrained('file_manager_files')->nullOnDelete();
        $t->timestamps();
    });
    Schema::create('product_images', function (Blueprint $t) {
        $t->id();
        $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();
        $t->foreignId('file_manager_file_id')->constrained('file_manager_files')->cascadeOnDelete();
        $t->unsignedInteger('sort_order')->default(0);
        $t->timestamps();
    });
    Schema::create('pos_customers', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->string('name');
        $t->timestamps();
    });

    $this->b1 = (new Business)->forceFill(['id' => 1, 'name' => 'Shop One']);
    $this->b2 = (new Business)->forceFill(['id' => 2, 'name' => 'Shop Two']);

    Storage::disk('public')->put('business-files/1/root/apple.png', 'PNG-BYTES');
    DB::table('file_manager_files')->insert(['id' => 10, 'business_id' => 1, 'original_filename' => 'apple.png', 'stored_path' => 'business-files/1/root/apple.png']);
    DB::table('products')->insert([
        ['id' => 1, 'business_id' => 1, 'name' => 'Apple', 'file_manager_file_id' => 10],
        ['id' => 2, 'business_id' => 1, 'name' => 'Pear', 'file_manager_file_id' => null],
    ]);
    DB::table('product_images')->insert(['id' => 5, 'product_id' => 1, 'file_manager_file_id' => 10, 'sort_order' => 0]);
    DB::table('pos_customers')->insert([
        ['id' => 1, 'business_id' => 1, 'name' => 'Ann'],
        ['id' => 2, 'business_id' => 1, 'name' => 'Ben'],
        ['id' => 3, 'business_id' => 2, 'name' => 'Other shop customer'],
    ]);
});

function backupZip(Business $business, array $groups): string
{
    $export = app(PosBackupExportService::class);
    $out = $export->export($business, $groups);

    return $export->exportPath($business, $out['token']);
}

function importBackup(Business $business, string $zip): array
{
    $import = app(PosBackupImportService::class);
    $state = $import->start($business, 1, 'backup.zip', filesize($zip));
    $state = $import->appendChunk($business, $state['id'], 0, fopen($zip, 'rb'));
    $state = $import->validate($business, $state['id']);
    for ($i = 0; $state['status'] === 'validated' || $state['status'] === 'importing'; $i++) {
        $state = $import->run($business, $state['id']);
        if ($i > 100) {
            break;
        }
    }

    return $state;
}

/** Rewrite one table's data inside a backup and fix its checksum, as an attacker would. */
function tamperBackup(string $zip, string $table, callable $mutate): string
{
    $copy = $zip.'.tampered.zip';
    copy($zip, $copy);
    $z = new ZipArchive();
    $z->open($copy);
    $rows = array_map(fn ($l) => json_decode($l, true), array_filter(explode("\n", $z->getFromName("data/{$table}.ndjson"))));
    $data = implode("\n", array_map('json_encode', $mutate($rows)))."\n";
    $manifest = json_decode($z->getFromName('manifest.json'), true);
    $manifest['tables'][$table]['sha256'] = hash('sha256', $data);
    $z->addFromString("data/{$table}.ndjson", $data);
    $z->addFromString('manifest.json', json_encode($manifest));
    $z->close();

    return $copy;
}

test('full round trip restores changed rows, deleted rows and product image files', function () {
    $zip = backupZip($this->b1, ['products', 'customers']);   // products pulls in media

    DB::table('products')->where('id', 1)->update(['name' => 'CHANGED']);
    DB::table('pos_customers')->where('id', 2)->delete();
    Storage::disk('public')->delete('business-files/1/root/apple.png');

    $state = importBackup($this->b1, $zip);

    expect($state['status'])->toBe('done')
        ->and($state['has_safety_backup'])->toBeTrue()
        ->and(DB::table('products')->where('id', 1)->value('name'))->toBe('Apple')
        ->and(DB::table('pos_customers')->where('id', 2)->value('name'))->toBe('Ben')
        ->and(DB::table('pos_customers')->where('id', 3)->value('business_id'))->toBe(2)
        ->and(Storage::disk('public')->get('business-files/1/root/apple.png'))->toBe('PNG-BYTES');
});

test('export only contains the selected sections and this business\'s rows', function () {
    $zip = new ZipArchive();
    $zip->open(backupZip($this->b1, ['customers']));
    $manifest = json_decode($zip->getFromName('manifest.json'), true);

    expect($manifest['groups'])->toBe(['customers'])
        ->and(array_keys($manifest['tables']))->toBe(['pos_customers'])
        ->and($manifest['tables']['pos_customers']['rows'])->toBe(2)
        ->and($zip->getFromName('data/pos_customers.ndjson'))->not->toContain('Other shop customer');
});

test('a backup whose ids belong to another business is rejected without writing', function () {
    $zip = backupZip($this->b1, ['customers']);

    $state = importBackup($this->b2, $zip);

    expect($state['status'])->toBe('invalid')
        ->and(implode(' ', $state['report']['errors']))->toContain('already belong to another business')
        ->and(DB::table('pos_customers')->where('business_id', 2)->count())->toBe(1);
});

test('child rows pointing at another business are rejected', function () {
    DB::table('products')->insert(['id' => 99, 'business_id' => 2, 'name' => 'Not yours']);
    $zip = tamperBackup(backupZip($this->b1, ['products']), 'product_images', function ($rows) {
        $rows[0]['product_id'] = 99;

        return $rows;
    });

    $state = importBackup($this->b1, $zip);

    expect($state['status'])->toBe('invalid')
        ->and(implode(' ', $state['report']['errors']))->toContain('belong to another business');
});

test('a damaged backup fails its checksum', function () {
    $zip = backupZip($this->b1, ['customers']);
    $z = new ZipArchive();
    $z->open($zip);
    $z->addFromString('data/pos_customers.ndjson', '{"id":1,"business_id":1,"name":"x"}'."\n");
    $z->close();

    $state = importBackup($this->b1, $zip);

    expect($state['status'])->toBe('invalid')
        ->and($state['report']['errors'][0])->toContain('checksum');
});

test('file paths outside the business file area are rejected', function () {
    $zip = backupZip($this->b1, ['media']);
    $z = new ZipArchive();
    $z->open($zip);
    $manifest = json_decode($z->getFromName('manifest.json'), true);
    $manifest['files'][] = ['path' => 'business-files/1/../../2/secret.txt', 'size' => 1, 'sha256' => 'x'];
    $z->addFromString('manifest.json', json_encode($manifest));
    $z->close();

    $state = importBackup($this->b1, $zip);

    expect($state['status'])->toBe('invalid')
        ->and(implode(' ', $state['report']['errors']))->toContain('outside the business file area');
});
