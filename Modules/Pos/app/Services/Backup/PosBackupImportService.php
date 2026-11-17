<?php

namespace Modules\Pos\Services\Backup;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Business\Models\Business;
use RuntimeException;
use ZipArchive;

/**
 * Restores a backup zip made by PosBackupExportService into the current business.
 *
 * The desktop client drives it in small HTTP calls so any PHP upload limit or
 * request timeout is avoided and a real progress bar can be shown:
 *
 *   start → chunk × N → validate → run × N (snapshot → rows → files → finalize)
 *
 * Validation reads every row before anything is written. Writes are upserts by
 * primary key (rows present in the backup are added or updated, nothing is
 * deleted). Before the first write a safety backup of the same groups is taken
 * so a failed or unwanted import can be rolled back by importing that file.
 *
 * Tenant safety: owner columns are always overwritten with the target business,
 * rows whose id already belongs to another business are rejected, and every
 * foreign key that points at a business-scoped table must resolve to a row in
 * the backup or one this business owns.
 */
class PosBackupImportService
{
    public const MAX_UPLOAD_BYTES = 2 * 1024 * 1024 * 1024;   // 2 GB zip

    public const MAX_CHUNK_BYTES = 8 * 1024 * 1024;

    private const MAX_EXTRACT_BYTES = 8 * 1024 * 1024 * 1024;  // zip-bomb guard

    private const MAX_ENTRIES = 250000;

    /** Short slices keep the desktop progress bar moving smoothly. */
    private const STEP_SECONDS = 1.0;

    private const READ_BATCH = 500;

    private const MAX_MESSAGES = 40;

    private const KEEP_HOURS = 24;

    /** @var array<int, string> */
    private array $errors = [];

    /** @var array<int, string> */
    private array $warnings = [];

    public function __construct(
        private readonly PosBackupRegistry $registry,
        private readonly PosBackupExportService $exporter,
    ) {
    }

    // ── Upload ───────────────────────────────────────────────────────────────

    public function start(Business $business, int $userId, string $filename, int $size): array
    {
        if ($size <= 0 || $size > self::MAX_UPLOAD_BYTES) {
            throw new RuntimeException('Backup file must be between 1 byte and 2 GB.');
        }
        $this->cleanup($business->id);
        if ($active = $this->active($business)) {
            throw new RuntimeException('Another import is already running for this business ('.$active['id'].').');
        }

        $id = Str::lower(Str::random(32));
        File::ensureDirectoryExists($this->dir($business->id, $id));
        touch($this->zipPath($business->id, $id));

        $state = [
            'id' => $id,
            'business_id' => $business->id,
            'user_id' => $userId,
            'created_at' => now()->toIso8601String(),
            'status' => 'uploading',
            'upload' => ['filename' => mb_substr($filename, 0, 200), 'size' => $size, 'received' => 0, 'next_index' => 0],
            'report' => null,
            'plan' => null,
            'cursor' => null,
            'safety' => null,
            'error' => null,
        ];
        $this->save($state);

        return $this->view($state);
    }

    /** @param  resource  $stream  raw request body */
    public function appendChunk(Business $business, string $id, int $index, $stream): array
    {
        $state = $this->load($business, $id);
        if ($state['status'] !== 'uploading') {
            throw new RuntimeException('This import is not accepting upload data.');
        }
        if ($index !== $state['upload']['next_index']) {
            throw new RuntimeException('Unexpected chunk '.$index.'; expected '.$state['upload']['next_index'].'.');
        }

        $out = fopen($this->zipPath($business->id, $id), 'ab');
        $written = stream_copy_to_stream($stream, $out, self::MAX_CHUNK_BYTES + 1);
        fclose($out);

        $state['upload']['received'] += (int) $written;
        $state['upload']['next_index']++;
        if ($written > self::MAX_CHUNK_BYTES || $state['upload']['received'] > $state['upload']['size']) {
            $this->fail($state, 'Upload is larger than announced — aborted.');
            throw new RuntimeException($state['error']);
        }
        if ($state['upload']['received'] === $state['upload']['size']) {
            $state['status'] = 'uploaded';
        }
        $this->save($state);

        return $this->view($state);
    }

    // ── Validation ───────────────────────────────────────────────────────────

    public function validate(Business $business, string $id): array
    {
        @set_time_limit(0);
        $state = $this->load($business, $id);
        if ($state['status'] !== 'uploaded') {
            throw new RuntimeException('Upload the backup file completely before validating.');
        }

        $this->errors = [];
        $this->warnings = [];
        $plan = null;
        $report = null;

        try {
            [$plan, $report] = $this->inspect($business, $state);
        } catch (BackupInvalid $e) {
            $this->error($e->getMessage());
        }

        $report ??= [];
        $report['errors'] = $this->errors;
        $report['warnings'] = $this->warnings;
        $report['ok'] = $this->errors === [];

        $state['report'] = $report;
        $state['plan'] = $report['ok'] ? $plan : null;
        $state['status'] = $report['ok'] ? 'validated' : 'invalid';
        if (! $report['ok']) {
            File::deleteDirectory($this->dir($business->id, $id).DIRECTORY_SEPARATOR.'extracted');
        }
        @unlink($this->zipPath($business->id, $id));   // extracted copy is all we need now
        $this->save($state);

        return $this->view($state);
    }

    /** @return array{0: array, 1: array} plan, report */
    private function inspect(Business $business, array $state): array
    {
        $zipPath = $this->zipPath($business->id, $state['id']);
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CHECKCONS) !== true) {
            throw new BackupInvalid('The file is not a valid zip archive (it may be damaged or incomplete).');
        }

        try {
            $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
            if (! is_array($manifest) || ($manifest['format'] ?? null) !== PosBackupRegistry::FORMAT) {
                throw new BackupInvalid('This zip is not a Zeebroo backup (manifest.json missing or unrecognised).');
            }
            if ((int) ($manifest['version'] ?? 0) < 1 || (int) $manifest['version'] > PosBackupRegistry::VERSION) {
                throw new BackupInvalid('This backup was made by a newer version of Zeebroo. Update the app and try again.');
            }

            $sourceId = (int) ($manifest['business']['id'] ?? 0);
            if ($sourceId <= 0) {
                throw new BackupInvalid('The backup manifest does not name its source business.');
            }

            $known = $this->registry->groupKeys();
            $groups = array_values(array_intersect((array) ($manifest['groups'] ?? []), $known));
            if ($unknown = array_diff((array) ($manifest['groups'] ?? []), $known)) {
                $this->warn('Skipping sections this server does not know: '.implode(', ', $unknown).'.');
            }
            if ($groups === []) {
                throw new BackupInvalid('The backup does not contain any section this server can import.');
            }

            $specs = $this->registry->tablesFor($groups);
            $manifestTables = is_array($manifest['tables'] ?? null) ? $manifest['tables'] : [];
            foreach (array_keys($manifestTables) as $t) {
                if (! isset($specs[$t])) {
                    $this->warn("Table \"{$t}\" is not used by this server version and will be skipped.");
                }
            }

            $this->checkEntries($zip, $manifestTables, (array) ($manifest['files'] ?? []));

            $extractDir = $this->dir($business->id, $state['id']).DIRECTORY_SEPARATOR.'extracted';
            File::deleteDirectory($extractDir);
            File::ensureDirectoryExists($extractDir.DIRECTORY_SEPARATOR.'data');
            File::ensureDirectoryExists($extractDir.DIRECTORY_SEPARATOR.'files');

            // Data files
            $dataFiles = [];
            foreach ($specs as $table => $spec) {
                if (! isset($manifestTables[$table])) {
                    continue;
                }
                $local = $extractDir.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.count($dataFiles).'.ndjson';
                $this->extractEntry($zip, 'data/'.$table.'.ndjson', $local, (string) ($manifestTables[$table]['sha256'] ?? ''), "data for \"{$table}\"");
                $dataFiles[$table] = $local;
            }

            // Binary files (product images, File Manager)
            $files = [];
            $bytes = 0;
            if (in_array('media', $groups, true)) {
                $srcPrefix = PosBackupRegistry::filesPrefix($sourceId);
                $dstPrefix = PosBackupRegistry::filesPrefix($business->id);
                $missing = 0;
                foreach ((array) ($manifest['files'] ?? []) as $i => $f) {
                    $path = (string) ($f['path'] ?? '');
                    if (! $this->safeStoredPath($path) || ! str_starts_with($path, $srcPrefix)) {
                        $this->error("File \"{$path}\" is outside the business file area — the backup looks tampered with.");

                        continue;
                    }
                    if ($zip->locateName('files/'.$path) === false) {
                        $missing++;

                        continue;
                    }
                    $local = $extractDir.DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR.$i.'.bin';
                    $this->extractEntry($zip, 'files/'.$path, $local, (string) ($f['sha256'] ?? ''), "file \"{$path}\"");
                    $size = filesize($local);
                    $bytes += $size;
                    $files[] = ['local' => $local, 'target' => $dstPrefix.substr($path, strlen($srcPrefix)), 'size' => $size];
                }
                if ($missing) {
                    $this->warn("{$missing} file(s) listed in the backup are missing from the zip and will be skipped.");
                }
            }
        } finally {
            $zip->close();
        }

        $tables = $this->validateRows($business, $specs, $dataFiles, $sourceId);

        $groupMeta = $this->registry->groups();
        $groupRows = [];
        foreach ($tables as $t) {
            $groupRows[$t['group']] = ($groupRows[$t['group']] ?? 0) + $t['rows'];
        }

        $report = [
            'backup' => [
                'business_id' => $sourceId,
                'business_name' => (string) ($manifest['business']['name'] ?? ''),
                'created_at' => $manifest['created_at'] ?? null,
                'app_version' => $manifest['app_version'] ?? null,
                'same_business' => $sourceId === $business->id,
            ],
            'groups' => array_map(fn ($g) => [
                'key' => $g,
                'label' => $groupMeta[$g]['label'],
                'icon' => $groupMeta[$g]['icon'],
                'rows' => $groupRows[$g] ?? 0,
                'files' => $g === 'media' ? count($files) : null,
            ], $groups),
            'totals' => [
                'rows' => array_sum(array_column($tables, 'rows')),
                'files' => count($files),
                'bytes' => $bytes,
            ],
        ];

        if (! $report['backup']['same_business']) {
            $this->warn('This backup came from another business ("'.$report['backup']['business_name'].'"). Its data will be added to this business.');
        }

        $plan = [
            'source_business_id' => $sourceId,
            'groups' => $groups,
            'tables' => $tables,
            'files' => $files,
        ];

        return [$plan, $report];
    }

    /** Reject anything in the zip we did not put there (zip-slip, bombs, junk). */
    private function checkEntries(ZipArchive $zip, array $manifestTables, array $manifestFiles): void
    {
        if ($zip->numFiles > self::MAX_ENTRIES) {
            throw new BackupInvalid('The backup contains too many entries.');
        }
        $allowed = ['manifest.json' => true];
        foreach (array_keys($manifestTables) as $t) {
            if (preg_match('/^[a-z0-9_]+$/', (string) $t)) {
                $allowed['data/'.$t.'.ndjson'] = true;
            }
        }
        foreach ($manifestFiles as $f) {
            $allowed['files/'.($f['path'] ?? '')] = true;
        }

        $total = 0;
        $ignored = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = (string) $stat['name'];
            if (str_ends_with($name, '/')) {
                continue;
            }
            $total += (int) $stat['size'];
            if (! isset($allowed[$name])) {
                $ignored++;
            }
        }
        if ($total > self::MAX_EXTRACT_BYTES) {
            throw new BackupInvalid('The backup expands to more than 8 GB and cannot be imported.');
        }
        if ($ignored) {
            $this->warn("{$ignored} unexpected item(s) in the zip will be ignored.");
        }
    }

    private function extractEntry(ZipArchive $zip, string $name, string $dest, string $sha256, string $label): void
    {
        $in = $zip->getStream($name);
        if ($in === false) {
            throw new BackupInvalid("The backup is missing {$label}.");
        }
        $out = fopen($dest, 'wb');
        $ctx = hash_init('sha256');
        while (! feof($in)) {
            $buf = fread($in, 1 << 20);
            if ($buf === false) {
                break;
            }
            hash_update($ctx, $buf);
            fwrite($out, $buf);
        }
        fclose($in);
        fclose($out);

        if ($sha256 === '' || ! hash_equals($sha256, hash_final($ctx))) {
            throw new BackupInvalid("The backup is damaged: {$label} failed its checksum.");
        }
    }

    private function safeStoredPath(string $path): bool
    {
        return $path !== ''
            && ! str_starts_with($path, '/')
            && ! preg_match('#[\x00-\x1f\\\\:]#', $path)
            && ! in_array('..', explode('/', $path), true);
    }

    /**
     * Full pass over every row: structure, required columns, duplicate ids,
     * foreign keys and cross-business id clashes.
     *
     * @return array<int, array<string, mixed>> per-table import plan
     */
    private function validateRows(Business $business, array $specs, array $dataFiles, int $sourceId): array
    {
        $allSpecs = $this->registry->allTables();
        $ids = [];        // table => [id => true] present in backup
        $refs = [];       // foreign table => [id => [table.col, ...]]
        $plans = [];

        foreach ($specs as $table => $spec) {
            if (! isset($dataFiles[$table])) {
                continue;
            }
            $columns = [];
            foreach (Schema::getColumns($table) as $c) {
                $columns[$c['name']] = $c;
            }
            [$ownerCol, $ownerExtra] = isset($spec['parent']) ? [null, []] : $this->registry->ownerColumn($spec);
            $dropId = ! empty($spec['drop_id']);
            $uniqueBy = $spec['key'] ?? ['id'];

            $required = [];
            foreach ($columns as $name => $c) {
                if (! $c['nullable'] && $c['default'] === null && ! $c['auto_increment']
                    && $name !== $ownerCol && ! array_key_exists($name, $ownerExtra) && ! ($dropId && $name === 'id')) {
                    $required[] = $name;
                }
            }

            // Columns that reference a business-scoped table must stay inside this business.
            $fkCols = [];
            foreach (Schema::getForeignKeys($table) as $fk) {
                if (count($fk['columns']) === 1 && ($fk['foreign_columns'][0] ?? 'id') === 'id'
                    && $fk['foreign_table'] !== 'businesses' && $fk['columns'][0] !== $ownerCol
                    && $this->registry->isBusinessScoped($fk['foreign_table'])) {
                    $fkCols[$fk['columns'][0]] = $fk['foreign_table'];
                }
            }
            if (isset($spec['parent'])) {
                $fkCols[$spec['parent'][0]] = $spec['parent'][1];
            }

            $ids[$table] = [];
            $unknown = [];
            $badLines = 0;
            $missingReq = 0;
            $badIds = 0;
            $dupes = 0;
            $rows = 0;
            $lineNo = 0;

            $fh = fopen($dataFiles[$table], 'rb');
            while (($line = fgets($fh)) !== false) {
                $lineNo++;
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $row = json_decode($line, true, 4);
                if (! is_array($row) || array_is_list($row) || ! $this->scalarRow($row)) {
                    $badLines++;

                    continue;
                }
                $rows++;

                foreach ($row as $col => $_) {
                    if (! isset($columns[$col])) {
                        $unknown[$col] = true;
                    }
                }
                foreach ($required as $col) {
                    if (! isset($row[$col])) {
                        $missingReq++;
                        break;
                    }
                }
                foreach ($uniqueBy as $col) {
                    if (! in_array($col, [$ownerCol, ...array_keys($ownerExtra)], true) && ! isset($row[$col])) {
                        $missingReq++;
                        break;
                    }
                }

                if (! $dropId) {
                    $id = $row['id'] ?? null;
                    if (! is_numeric($id) || (int) $id <= 0 || (int) $id != $id) {
                        $badIds++;
                    } elseif (isset($ids[$table][(int) $id])) {
                        $dupes++;
                    } else {
                        $ids[$table][(int) $id] = true;
                    }
                }

                foreach ($fkCols as $col => $foreign) {
                    $v = $row[$col] ?? null;
                    if ($v !== null && $v !== '' && is_numeric($v)) {
                        $refs[$foreign][(int) $v][$table.'.'.$col] = true;
                    }
                }
            }
            fclose($fh);

            if ($badLines) {
                $this->error("\"{$table}\": {$badLines} line(s) are not valid records.");
            }
            if ($missingReq) {
                $this->error("\"{$table}\": {$missingReq} record(s) are missing required values.");
            }
            if ($badIds) {
                $this->error("\"{$table}\": {$badIds} record(s) have an invalid id.");
            }
            if ($dupes) {
                $this->error("\"{$table}\": {$dupes} duplicate id(s) in the backup.");
            }
            if ($unknown) {
                $this->warn("\"{$table}\": column(s) ".implode(', ', array_keys($unknown)).' do not exist on this server and will be ignored.');
            }

            if ($rows > 0) {
                $plans[$table] = [
                    'table' => $table,
                    'group' => $spec['group'],
                    'file' => $dataFiles[$table],
                    'rows' => $rows,
                    'columns' => array_keys($columns),
                    'nullable' => array_keys(array_filter($columns, fn ($c) => $c['nullable'])),
                    'unique_by' => $uniqueBy,
                    'drop_id' => $dropId,
                    'owner_col' => $ownerCol,
                    'owner_extra' => $ownerExtra,
                    'nullify' => [],
                ];
            }
        }

        // Rows whose id is already taken by another business would overwrite that business's data.
        $clashes = [];
        foreach ($plans as $table => $p) {
            if ($p['drop_id'] || $ids[$table] === []) {
                continue;
            }
            $clash = 0;
            foreach (array_chunk(array_keys($ids[$table]), 500) as $chunk) {
                $existing = DB::table($table)->whereIn('id', $chunk)->count();
                $owned = $this->registry->ownedQuery($table, $business->id, $allSpecs)->whereIn('id', $chunk)->count();
                $clash += $existing - $owned;
            }
            if ($clash > 0) {
                $clashes[$table] = $clash;
            }
        }
        if ($clashes) {
            arsort($clashes);
            $top = array_slice(array_map(fn ($t, $n) => str_replace('_', ' ', $t)." ({$n})", array_keys($clashes), $clashes), 0, 5);
            $this->error(array_sum($clashes).' record(s) in '.count($clashes).' table(s) use ids that already belong to another business on this server — '
                .implode(', ', $top).(count($clashes) > 5 ? ', …' : '').'. '
                .'A backup can be restored into the business it came from, or onto a server where those records do not exist.');
        }

        // Foreign keys must resolve inside this backup or to rows this business owns.
        foreach ($refs as $foreign => $values) {
            $missing = array_diff_key($values, $ids[$foreign] ?? []);
            if ($missing === []) {
                continue;
            }
            $foreignOwned = [];
            $foreignAny = [];
            foreach (array_chunk(array_keys($missing), 500) as $chunk) {
                foreach ($this->registry->ownedQuery($foreign, $business->id, $allSpecs)->whereIn('id', $chunk)->pluck('id') as $id) {
                    $foreignOwned[(int) $id] = true;
                }
                foreach (DB::table($foreign)->whereIn('id', $chunk)->pluck('id') as $id) {
                    $foreignAny[(int) $id] = true;
                }
            }
            $foreignBlocked = [];
            $dangling = [];
            foreach ($missing as $id => $usedBy) {
                if (isset($foreignOwned[$id])) {
                    continue;
                }
                foreach (array_keys($usedBy) as $tc) {
                    if (isset($foreignAny[$id])) {
                        $foreignBlocked[$tc] = ($foreignBlocked[$tc] ?? 0) + 1;
                    } else {
                        $dangling[$tc][] = $id;
                    }
                }
            }
            foreach ($foreignBlocked as $tc => $n) {
                $this->error("\"{$tc}\": {$n} reference(s) point at {$foreign} records that belong to another business.");
            }
            foreach ($dangling as $tc => $danglingIds) {
                [$t, $col] = explode('.', $tc, 2);
                if (isset($plans[$t]) && in_array($col, $plans[$t]['nullable'], true)) {
                    $plans[$t]['nullify'][$col] = $danglingIds;
                    $this->warn("\"{$t}\": ".count($danglingIds)." link(s) to missing {$foreign} records will be cleared. Include that section in the backup to keep them.");
                } else {
                    $this->error("\"{$t}\": ".count($danglingIds)." record(s) need {$foreign} records that are neither in this backup nor in this business. Include that section in the backup.");
                }
            }
        }

        if ($plans === []) {
            $this->warn('The backup contains no records.');
        }

        return array_values($plans);
    }

    private function scalarRow(array $row): bool
    {
        foreach ($row as $k => $v) {
            if (! is_string($k) || ! preg_match('/^[A-Za-z0-9_]+$/', $k) || is_array($v)) {
                return false;
            }
        }

        return true;
    }

    // ── Apply ────────────────────────────────────────────────────────────────

    /** Do one time-boxed slice of the import and report progress. */
    public function run(Business $business, string $id): array
    {
        $lock = Cache::lock('pos-backup-import:'.$business->id, 600);
        if (! $lock->get()) {
            return $this->view($this->load($business, $id)) + ['busy' => true];
        }

        try {
            $state = $this->load($business, $id);
            if ($state['status'] === 'validated') {
                $state['status'] = 'importing';
                $state['started_at'] = now()->toIso8601String();
                $state['cursor'] = ['phase' => 'snapshot', 'table' => 0, 'offset' => 0, 'rows_done' => 0, 'files_done' => 0];
                $this->save($state);
            }
            if ($state['status'] !== 'importing') {
                return $this->view($state);
            }

            @set_time_limit(0);
            ignore_user_abort(true);
            $deadline = microtime(true) + self::STEP_SECONDS;

            try {
                match ($state['cursor']['phase']) {
                    'snapshot' => $this->stepSnapshot($business, $state),
                    'rows' => $this->stepRows($business, $state, $deadline),
                    'files' => $this->stepFiles($state, $deadline),
                    'finalize' => $this->stepFinalize($state),
                    default => null,
                };
            } catch (\Throwable $e) {
                Log::error('POS backup import failed', ['business_id' => $business->id, 'import' => $id, 'exception' => $e]);
                $this->fail($state, 'Import stopped: '.Str::limit($e->getMessage(), 400));
            }

            $this->save($state);

            return $this->view($state);
        } finally {
            $lock->release();
        }
    }

    private function stepSnapshot(Business $business, array &$state): void
    {
        $snap = $this->exporter->export($business, $state['plan']['groups'], 'safety-snapshot');
        $state['safety'] = ['token' => $snap['token'], 'filename' => 'safety-before-import-'.$snap['filename'], 'size' => $snap['size']];
        $state['cursor']['phase'] = 'rows';
    }

    private function stepRows(Business $business, array &$state, float $deadline): void
    {
        $tables = $state['plan']['tables'];
        $c = &$state['cursor'];
        $limit = DB::connection()->getDriverName() === 'sqlite' ? 900 : 10000;   // bound parameters per statement
        $srcPrefix = PosBackupRegistry::filesPrefix($state['plan']['source_business_id']);
        $dstPrefix = PosBackupRegistry::filesPrefix($business->id);
        $rewritePaths = $srcPrefix !== $dstPrefix;

        Schema::disableForeignKeyConstraints();
        try {
            while ($c['table'] < count($tables) && microtime(true) < $deadline) {
                $p = $tables[$c['table']];
                $allowed = array_flip($p['columns']);
                $nullify = array_map(fn ($ids) => array_flip($ids), $p['nullify']);

                $fh = fopen($p['file'], 'rb');
                fseek($fh, $c['offset']);
                $batch = [];
                while (count($batch) < self::READ_BATCH && ($line = fgets($fh)) !== false) {
                    $row = json_decode(trim($line), true, 4);
                    if (! is_array($row) || array_is_list($row)) {
                        continue;
                    }
                    $row = array_intersect_key($row, $allowed);
                    if ($p['drop_id']) {
                        unset($row['id']);
                    }
                    if ($p['owner_col']) {
                        $row[$p['owner_col']] = $business->id;
                        foreach ($p['owner_extra'] as $col => $val) {
                            $row[$col] = $val;
                        }
                    }
                    foreach ($nullify as $col => $bad) {
                        if (isset($row[$col]) && isset($bad[(int) $row[$col]])) {
                            $row[$col] = null;
                        }
                    }
                    if ($rewritePaths) {
                        foreach ($row as $col => $v) {
                            if (is_string($v) && str_starts_with($v, $srcPrefix)) {
                                $row[$col] = $dstPrefix.substr($v, strlen($srcPrefix));
                            }
                        }
                    }
                    $batch[] = $row;
                }
                $eof = $line === false || feof($fh);
                $offset = ftell($fh);
                fclose($fh);

                if ($batch !== []) {
                    DB::transaction(fn () => $this->upsert($p['table'], $batch, $p['unique_by'], $limit));
                }

                $c['rows_done'] += count($batch);
                if ($eof) {
                    $c['table']++;
                    $c['offset'] = 0;
                } else {
                    $c['offset'] = $offset;
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        if ($c['table'] >= count($tables)) {
            $c['phase'] = 'files';
        }
    }

    private function upsert(string $table, array $rows, array $uniqueBy, int $paramLimit): void
    {
        // upsert() takes its column list from the first row, so group by shape.
        $shapes = [];
        foreach ($rows as $row) {
            ksort($row);
            $shapes[implode(',', array_keys($row))][] = $row;
        }
        foreach ($shapes as $shapeRows) {
            $cols = array_keys($shapeRows[0]);
            $update = array_values(array_diff($cols, $uniqueBy));
            $per = max(1, intdiv($paramLimit, max(1, count($cols))));
            foreach (array_chunk($shapeRows, $per) as $chunk) {
                if ($update === []) {
                    DB::table($table)->insertOrIgnore($chunk);
                } else {
                    DB::table($table)->upsert($chunk, $uniqueBy, $update);
                }
            }
        }
    }

    private function stepFiles(array &$state, float $deadline): void
    {
        $files = $state['plan']['files'];
        $c = &$state['cursor'];
        $disk = Storage::disk(PosBackupRegistry::FILES_DISK);

        while ($c['files_done'] < count($files) && microtime(true) < $deadline) {
            $f = $files[$c['files_done']];
            $in = fopen($f['local'], 'rb');
            $disk->writeStream($f['target'], $in);
            if (is_resource($in)) {
                fclose($in);
            }
            $c['files_done']++;
        }

        if ($c['files_done'] >= count($files)) {
            $c['phase'] = 'finalize';
        }
    }

    private function stepFinalize(array &$state): void
    {
        File::deleteDirectory($this->dir($state['business_id'], $state['id']).DIRECTORY_SEPARATOR.'extracted');
        $state['cursor']['phase'] = 'done';
        $state['status'] = 'done';
        $state['finished_at'] = now()->toIso8601String();
    }

    // ── Queries ──────────────────────────────────────────────────────────────

    public function get(Business $business, string $id): array
    {
        return $this->view($this->load($business, $id));
    }

    /** The import currently in progress for this business (so the client can resume it). */
    public function active(Business $business): ?array
    {
        $root = $this->root($business->id);
        if (! is_dir($root)) {
            return null;
        }
        foreach (File::directories($root) as $dir) {
            $state = $this->readState($dir);
            if ($state && $state['status'] === 'importing') {
                return $this->view($state);
            }
        }

        return null;
    }

    public function cancel(Business $business, string $id): void
    {
        $state = $this->load($business, $id);
        if ($state['status'] === 'importing') {
            throw new RuntimeException('An import that has started cannot be cancelled. Let it finish, then restore the safety backup if needed.');
        }
        File::deleteDirectory($this->dir($business->id, $id));
    }

    /** Absolute path of this import's safety snapshot zip. */
    public function safetyPath(Business $business, string $id): ?array
    {
        $state = $this->load($business, $id);
        if (empty($state['safety']['token'])) {
            return null;
        }
        $path = $this->exporter->exportPath($business, $state['safety']['token']);

        return $path ? ['path' => $path, 'filename' => $state['safety']['filename']] : null;
    }

    // ── State helpers ────────────────────────────────────────────────────────

    private function view(array $state): array
    {
        $plan = $state['plan'] ?? null;
        $cursor = $state['cursor'] ?? null;
        $progress = null;

        if ($plan && $cursor) {
            $rowsTotal = array_sum(array_column($plan['tables'], 'rows'));
            $filesTotal = count($plan['files']);
            $units = max(1, $rowsTotal + $filesTotal);
            $done = $cursor['rows_done'] + $cursor['files_done'];
            $phase = $cursor['phase'];
            $percent = $phase === 'snapshot' ? 2 : 8 + (int) floor(90 * $done / $units);
            if ($phase === 'done') {
                $percent = 100;
            }
            $current = $plan['tables'][$cursor['table']]['table'] ?? null;
            $progress = [
                'phase' => $phase,
                'label' => match ($phase) {
                    'snapshot' => 'Creating a safety backup of your current data…',
                    'rows' => 'Importing '.str_replace('_', ' ', (string) $current).'…',
                    'files' => 'Restoring images & files…',
                    'finalize' => 'Finishing up…',
                    default => 'Import complete',
                },
                'percent' => min(100, $percent),
                'rows_done' => $cursor['rows_done'],
                'rows_total' => $rowsTotal,
                'files_done' => $cursor['files_done'],
                'files_total' => $filesTotal,
            ];
        }

        return [
            'id' => $state['id'],
            'status' => $state['status'],
            'upload' => [
                'filename' => $state['upload']['filename'],
                'size' => $state['upload']['size'],
                'received' => $state['upload']['received'],
                'next_index' => $state['upload']['next_index'],
            ],
            'report' => $state['report'],
            'progress' => $progress,
            'has_safety_backup' => ! empty($state['safety']['token']),
            'error' => $state['error'],
        ];
    }

    private function fail(array &$state, string $message): void
    {
        $state['status'] = 'failed';
        $state['error'] = $message;
    }

    private function error(string $msg): void
    {
        if (count($this->errors) < self::MAX_MESSAGES) {
            $this->errors[] = $msg;
        }
    }

    private function warn(string $msg): void
    {
        if (count($this->warnings) < self::MAX_MESSAGES) {
            $this->warnings[] = $msg;
        }
    }

    private function load(Business $business, string $id): array
    {
        if (! preg_match('/^[a-z0-9]{32}$/', $id)) {
            throw new BackupNotFound();
        }
        $state = $this->readState($this->dir($business->id, $id));
        if (! $state || (int) $state['business_id'] !== $business->id) {
            throw new BackupNotFound();
        }

        return $state;
    }

    private function readState(string $dir): ?array
    {
        $file = $dir.DIRECTORY_SEPARATOR.'state.json';
        if (! is_file($file)) {
            return null;
        }
        $state = json_decode((string) file_get_contents($file), true);

        return is_array($state) ? $state : null;
    }

    private function save(array $state): void
    {
        $file = $this->dir($state['business_id'], $state['id']).DIRECTORY_SEPARATOR.'state.json';
        file_put_contents($file.'.tmp', json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        rename($file.'.tmp', $file);
    }

    private function root(int $businessId): string
    {
        return Storage::disk('local')->path('pos-backups/'.$businessId.'/imports');
    }

    private function dir(int $businessId, string $id): string
    {
        return $this->root($businessId).DIRECTORY_SEPARATOR.$id;
    }

    private function zipPath(int $businessId, string $id): string
    {
        return $this->dir($businessId, $id).DIRECTORY_SEPARATOR.'upload.zip';
    }

    private function cleanup(int $businessId): void
    {
        $root = $this->root($businessId);
        if (! is_dir($root)) {
            return;
        }
        $cutoff = time() - self::KEEP_HOURS * 3600;
        foreach (File::directories($root) as $dir) {
            $state = $this->readState($dir);
            if ($state && $state['status'] === 'importing') {
                continue;
            }
            $stateFile = $dir.DIRECTORY_SEPARATOR.'state.json';
            if (! is_file($stateFile) || filemtime($stateFile) < $cutoff) {
                File::deleteDirectory($dir);
            }
        }
    }
}
