<?php

namespace Modules\Pos\Services\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Business\Models\Business;
use RuntimeException;
use ZipArchive;

/**
 * Builds a business backup zip:
 *
 *   manifest.json             format, version, source business, groups, per-table row counts + sha256
 *   data/<table>.ndjson       one JSON object per row (raw column values)
 *   files/<stored path>       binaries under business-files/<id>/ (product images, File Manager)
 *
 * Zips are written under storage/app/private/pos-backups/<business>/exports and
 * fetched with a random token, so one business can never download another's.
 */
class PosBackupExportService
{
    private const CHUNK = 1000;

    /** Exports and finished imports older than this are removed on the next run. */
    private const KEEP_HOURS = 24;

    public function __construct(
        private readonly PosBackupRegistry $registry,
    ) {
    }

    /**
     * Group list for the export tab, with live row counts for this business.
     *
     * @return array<int, array<string, mixed>>
     */
    public function summary(Business $business): array
    {
        $specs = $this->registry->allTables();
        $out = [];
        foreach ($this->registry->groups() as $key => $group) {
            $rows = 0;
            foreach ($this->registry->tablesFor([$key]) as $table => $spec) {
                $rows += $this->registry->ownedQuery($table, $business->id, $specs)->count();
            }
            $item = [
                'key' => $key,
                'label' => $group['label'],
                'icon' => $group['icon'],
                'description' => $group['description'],
                'requires' => $group['requires'] ?? [],
                'rows' => $rows,
            ];
            if (! empty($group['files'])) {
                [$count, $bytes] = $this->fileStats($business->id);
                $item['files'] = $count;
                $item['bytes'] = $bytes;
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $groupKeys
     * @return array{token: string, filename: string, size: int, rows: int, files: int, groups: array<int, string>}
     */
    public function export(Business $business, array $groupKeys, string $appVersion = ''): array
    {
        @set_time_limit(0);
        $this->cleanup($business->id);

        $groups = $this->registry->resolveSelection($groupKeys);
        if ($groups === []) {
            throw new RuntimeException('Select at least one item to back up.');
        }

        $token = Str::lower(Str::random(40));
        $dir = $this->exportsDir($business->id);
        File::ensureDirectoryExists($dir);
        // Short temp names: libzip silently skips files past the Windows MAX_PATH limit.
        $work = $dir.DIRECTORY_SEPARATOR.substr($token, 0, 8);
        File::ensureDirectoryExists($work);
        $zipPath = $dir.DIRECTORY_SEPARATOR.$token.'.zip';

        $specs = $this->registry->tablesFor($groups);
        $allSpecs = $this->registry->allTables();
        $manifestTables = [];
        $totalRows = 0;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the backup file on the server.');
        }

        try {
            foreach ($specs as $table => $spec) {
                $file = $work.DIRECTORY_SEPARATOR.count($manifestTables).'.ndjson';
                $fh = fopen($file, 'wb');
                $rows = 0;
                $this->registry->ownedQuery($table, $business->id, $allSpecs)
                    ->lazyById(self::CHUNK, 'id')
                    ->each(function ($row) use ($fh, &$rows) {
                        fwrite($fh, json_encode((array) $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)."\n");
                        $rows++;
                    });
                fclose($fh);

                if (! $zip->addFile($file, 'data/'.$table.'.ndjson')) {
                    throw new RuntimeException("Could not add \"{$table}\" to the backup.");
                }
                $manifestTables[$table] = [
                    'group' => $spec['group'],
                    'rows' => $rows,
                    'sha256' => hash_file('sha256', $file),
                ];
                $totalRows += $rows;
            }

            $manifestFiles = [];
            if (in_array('media', $groups, true)) {
                $disk = Storage::disk(PosBackupRegistry::FILES_DISK);
                $prefix = PosBackupRegistry::filesPrefix($business->id);
                foreach ($disk->allFiles(rtrim($prefix, '/')) as $path) {
                    $abs = $disk->path($path);
                    if (! is_file($abs)) {
                        continue;
                    }
                    $src = $abs;
                    if (strlen($abs) > 200) {
                        $src = $work.DIRECTORY_SEPARATOR.'f'.count($manifestFiles);
                        copy($abs, $src);
                    }
                    if (! $zip->addFile($src, 'files/'.$path)) {
                        throw new RuntimeException("Could not add file \"{$path}\" to the backup.");
                    }
                    // Images are already compressed — storing them avoids wasted CPU.
                    $zip->setCompressionName('files/'.$path, ZipArchive::CM_STORE);
                    $manifestFiles[] = [
                        'path' => $path,
                        'size' => filesize($abs),
                        'sha256' => hash_file('sha256', $abs),
                    ];
                }
            }

            $manifest = [
                'format' => PosBackupRegistry::FORMAT,
                'version' => PosBackupRegistry::VERSION,
                'created_at' => now()->toIso8601String(),
                'app_version' => $appVersion,
                'database_driver' => DB::connection()->getDriverName(),
                'business' => ['id' => $business->id, 'name' => $business->name],
                'groups' => $groups,
                'tables' => $manifestTables,
                'files' => $manifestFiles,
            ];
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            if (! $zip->close()) {
                throw new RuntimeException('Could not finish writing the backup file.');
            }
            $check = new ZipArchive();
            $expected = count($manifestTables) + count($manifestFiles) + 1;
            if ($check->open($zipPath) !== true || $check->numFiles !== $expected) {
                throw new RuntimeException('The backup file was written incompletely. Please try again.');
            }
            $check->close();
        } catch (\Throwable $e) {
            try {
                $zip->close();
            } catch (\Throwable) {
                // already closed
            }
            @unlink($zipPath);
            throw $e;
        } finally {
            File::deleteDirectory($work);
        }

        $slug = Str::slug($business->name) ?: 'business';

        return [
            'token' => $token,
            'filename' => 'zeebroo-backup-'.$slug.'-'.now()->format('Y-m-d-His').'.zip',
            'size' => filesize($zipPath),
            'rows' => $totalRows,
            'files' => count($manifestFiles),
            'groups' => $groups,
        ];
    }

    /** Absolute path of a finished export zip, or null when the token is unknown. */
    public function exportPath(Business $business, string $token): ?string
    {
        if (! preg_match('/^[a-z0-9]{40}$/', $token)) {
            return null;
        }
        $path = $this->exportsDir($business->id).DIRECTORY_SEPARATOR.$token.'.zip';

        return is_file($path) ? $path : null;
    }

    public function exportsDir(int $businessId): string
    {
        return Storage::disk('local')->path('pos-backups/'.$businessId.'/exports');
    }

    /** @return array{0: int, 1: int} */
    private function fileStats(int $businessId): array
    {
        $disk = Storage::disk(PosBackupRegistry::FILES_DISK);
        $count = 0;
        $bytes = 0;
        foreach ($disk->allFiles(rtrim(PosBackupRegistry::filesPrefix($businessId), '/')) as $path) {
            $count++;
            $bytes += (int) $disk->size($path);
        }

        return [$count, $bytes];
    }

    private function cleanup(int $businessId): void
    {
        $dir = $this->exportsDir($businessId);
        if (! is_dir($dir)) {
            return;
        }
        $cutoff = time() - self::KEEP_HOURS * 3600;
        foreach (File::files($dir) as $file) {
            if ($file->getMTime() < $cutoff) {
                @unlink($file->getPathname());
            }
        }
    }
}
