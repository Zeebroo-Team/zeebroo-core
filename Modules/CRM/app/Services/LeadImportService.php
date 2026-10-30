<?php

namespace Modules\CRM\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Jobs\RunLeadImportJob;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadImport;
use Modules\CRM\Models\Project;
use Modules\CRM\Support\LeadImportReader;
use Modules\CRM\Support\PhoneNormalizer;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Bulk lead upload: upload → map columns → preview → run.
 *
 * The file can be our CSV template, a Google Forms responses export or a Facebook Lead Ads
 * export; the format is detected from the header row and each column gets a suggested field,
 * which the user can change. Columns that are not a lead field are kept as "Question: answer"
 * lines in the lead's notes. Every row goes through LeadIntakeService, so duplicates follow the
 * intake rule (an open lead gets the enquiry logged on it, a closed lead or a customer flags the
 * new lead for review). Each imported row is remembered by a key, so uploading a newer export
 * of the same form only brings in the new responses.
 */
class LeadImportService
{
    /** Bigger imports run on the queue. */
    public const QUEUE_ABOVE_ROWS = 200;

    /** Lead fields a column can be imported as: key => label. */
    public const FIELDS = [
        'name'             => 'Full name',
        'first_name'       => 'First name',
        'last_name'        => 'Last name',
        'company'          => 'Company',
        'phone'            => 'Phone',
        'email'            => 'Email',
        'source'           => 'Source / platform',
        'product_interest' => 'Product interest',
        'date'             => 'Enquiry date',
        'estimated_value'  => 'Estimated value',
        'notes'            => 'Notes',
        'external_id'      => 'Lead ID (from the source)',
    ];

    /** Keep the column as a "Column: value" line in the notes. */
    public const TARGET_NOTE = 'note';

    /** Leave the column out. */
    public const TARGET_IGNORE = '';

    /** What to do with a row whose contact already has an open lead. */
    public const DUPLICATE_MODES = [
        'log'  => 'Add it to the existing lead as a repeat enquiry',
        'skip' => 'Skip the row',
    ];

    public const TEMPLATE_HEADERS = ['name', 'phone', 'email', 'company', 'source', 'product_interest', 'date', 'notes'];

    /** Header (normalised) => field, for exact matches. */
    private const HEADER_FIELDS = [
        'name' => 'name', 'full name' => 'name', 'your name' => 'name', 'customer name' => 'name', 'contact name' => 'name', 'lead name' => 'name', 'client name' => 'name',
        'first name' => 'first_name', 'given name' => 'first_name',
        'last name' => 'last_name', 'surname' => 'last_name', 'family name' => 'last_name',
        'company' => 'company', 'company name' => 'company', 'business' => 'company', 'business name' => 'company', 'organization' => 'company', 'organisation' => 'company',
        'phone' => 'phone', 'phone number' => 'phone', 'mobile' => 'phone', 'mobile number' => 'phone', 'contact number' => 'phone', 'contact no' => 'phone', 'telephone' => 'phone', 'whatsapp' => 'phone', 'whatsapp number' => 'phone',
        'email' => 'email', 'email address' => 'email', 'e mail' => 'email',
        'source' => 'source', 'lead source' => 'source', 'platform' => 'source',
        'product interest' => 'product_interest', 'product' => 'product_interest', 'products' => 'product_interest', 'interested in' => 'product_interest', 'interest' => 'product_interest', 'service' => 'product_interest', 'requirement' => 'product_interest',
        'date' => 'date', 'timestamp' => 'date', 'created time' => 'date', 'enquiry date' => 'date', 'inquiry date' => 'date', 'submitted at' => 'date', 'submission date' => 'date', 'created at' => 'date',
        'notes' => 'notes', 'note' => 'notes', 'comments' => 'notes', 'comment' => 'notes', 'message' => 'notes', 'remarks' => 'notes',
        'external id' => 'external_id', 'lead id' => 'external_id', 'leadgen id' => 'external_id',
        'estimated value' => 'estimated_value', 'deal value' => 'estimated_value',
    ];

    /** Header words => field, for free-text questions such as "What is your phone number?". */
    private const HEADER_HINTS = [
        'email'           => ['email', 'e mail'],
        'phone'           => ['phone', 'mobile', 'whatsapp', 'contact number', 'telephone'],
        'company'         => ['company', 'business name', 'organi'],
        'first_name'      => ['first name'],
        'last_name'       => ['last name', 'surname'],
        'name'            => ['name'],
        'product_interest'=> ['product', 'interest', 'service'],
        'notes'           => ['message', 'comment', 'note', 'remark'],
    ];

    /** Facebook export columns about the ad, not the person: kept in the notes or left out. */
    private const FACEBOOK_NOTE_COLUMNS   = ['campaign name', 'form name', 'ad name', 'adset name'];
    private const FACEBOOK_IGNORE_COLUMNS = ['ad id', 'adset id', 'campaign id', 'form id', 'is organic', 'lead status', 'inbox url'];

    public function __construct(
        private readonly LeadImportReader $reader,
        private readonly LeadIntakeService $intake,
        private readonly DuplicateLeadService $duplicates,
        private readonly CrmSettingsService $settings,
    ) {}

    // ── Upload ────────────────────────────────────────────────────────────

    /**
     * Store the file and work out its format and a column mapping.
     *
     * @return array{import: LeadImport, headers: list<string>, sample: list<list<string>>}
     */
    public function upload(Business $business, UploadedFile $file, ?int $userId): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $table     = $this->reader->read($file->getRealPath(), $extension);
        $format    = $this->detectFormat($table['headers']);

        $path = $file->storeAs('crm/lead-imports/' . $business->id, Str::uuid() . '.' . $extension, 'local');

        $import = LeadImport::create([
            'business_id' => $business->id,
            'user_id'     => $userId,
            'project_id'  => $this->intake->intakeProject($business)?->id,
            'file_name'   => mb_substr($file->getClientOriginalName(), 0, 255),
            'file_path'   => $path,
            'format'      => $format,
            'source'      => LeadImport::FORMATS[$format][1],
            'mapping'     => $this->suggestMapping($table['headers'], $format),
            'options'     => ['duplicates' => 'log'],
            'status'      => LeadImport::STATUS_UPLOADED,
            'total_rows'  => count($table['rows']),
        ]);

        return ['import' => $import, 'headers' => $table['headers'], 'sample' => array_slice(array_values($table['rows']), 0, 5)];
    }

    /** The column names and first rows of a stored import (to show the mapping again). */
    public function headersAndSample(LeadImport $import): array
    {
        $table = $this->readImport($import);

        return ['headers' => $table['headers'], 'sample' => array_slice(array_values($table['rows']), 0, 5)];
    }

    public function detectFormat(array $headers): string
    {
        $h = array_map(fn ($x) => $this->normalizeHeader($x), $headers);

        if (in_array('created time', $h, true) && array_intersect(['ad id', 'form id', 'is organic', 'platform'], $h) !== []) {
            return 'facebook';
        }
        if (($h[0] ?? null) === 'timestamp') {
            return 'google_form';
        }

        return 'template';
    }

    /** @return list<string> a field key, TARGET_NOTE or TARGET_IGNORE for each column */
    public function suggestMapping(array $headers, string $format): array
    {
        $used    = [];
        $mapping = [];

        foreach ($headers as $header) {
            $h = $this->normalizeHeader($header);

            if ($format === 'facebook' && in_array($h, self::FACEBOOK_IGNORE_COLUMNS, true)) {
                $mapping[] = self::TARGET_IGNORE;
                continue;
            }
            if ($format === 'facebook' && in_array($h, self::FACEBOOK_NOTE_COLUMNS, true)) {
                $mapping[] = self::TARGET_NOTE;
                continue;
            }

            $field = self::HEADER_FIELDS[$h] ?? ($format === 'facebook' && $h === 'id' ? 'external_id' : null);
            if ($field === null) {
                foreach (self::HEADER_HINTS as $candidate => $words) {
                    if (! isset($used[$candidate]) && Str::contains($h, $words)) {
                        $field = $candidate;
                        break;
                    }
                }
            }

            if ($field !== null && ! isset($used[$field])) {
                $used[$field] = true;
                $mapping[]    = $field;
            } else {
                // Every other answer of a form is worth keeping on the lead
                $mapping[] = self::TARGET_NOTE;
            }
        }

        return $mapping;
    }

    // ── Preview ───────────────────────────────────────────────────────────

    /**
     * Save the mapping and check every row without saving anything.
     *
     * @param  list<string>  $mapping
     * @return array{rows: list<array>, totals: array<string, int>}
     */
    public function preview(LeadImport $import, array $mapping, string $source, Project $project, string $duplicateMode): array
    {
        $table   = $this->readImport($import);
        $mapping = $this->validMapping($mapping, $table['headers']);

        $import->update([
            'mapping'    => $mapping,
            'source'     => $source,
            'project_id' => $project->id,
            'options'    => ['duplicates' => $duplicateMode, 'day_first' => $this->dayFirst($table['rows'], $mapping, $import->format)],
            'status'     => LeadImport::STATUS_PREVIEWED,
            'total_rows' => count($table['rows']),
        ]);

        $context  = $this->context($import, $table['headers']);
        $imported = $this->importedKeys($import->business_id, array_map(fn ($cells) => $this->evaluate($cells, $context)['key'], $table['rows']));

        $rows     = [];
        $seenKeys = [];
        $contacts = [];
        foreach ($table['rows'] as $line => $cells) {
            $row = $this->evaluate($cells, $context);
            [$status, $reason] = $this->previewStatus($import, $row, $line, $imported, $seenKeys, $contacts, $duplicateMode);

            $rows[] = [
                'row'      => $line,
                'name'     => $row['data']['name'],
                'phone'    => $row['data']['phone'],
                'email'    => $row['data']['email'],
                'status'   => $status,
                'reason'   => $reason,
                'warnings' => $row['warnings'],
            ];
        }

        $totals = ['total' => count($rows), 'ok' => 0, 'duplicate' => 0, 'error' => 0, 'skipped' => 0];
        foreach ($rows as $r) {
            $totals[$r['status']]++;
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    /** @return array{0: string, 1: ?string} status (ok|duplicate|error|skipped) and why */
    private function previewStatus(LeadImport $import, array $row, int $line, array $imported, array &$seenKeys, array &$contacts, string $mode): array
    {
        if ($row['errors'] !== []) {
            return ['error', implode(' ', $row['errors'])];
        }
        if (isset($imported[$row['key']])) {
            return ['skipped', 'Imported before.'];
        }
        if (isset($seenKeys[$row['key']])) {
            return ['skipped', 'Same as row ' . $seenKeys[$row['key']] . '.'];
        }
        $seenKeys[$row['key']] = $line;

        $earlier = ($row['normalized_phone'] !== null ? ($contacts['p:' . $row['normalized_phone']] ?? null) : null)
            ?? ($row['data']['email'] !== null ? ($contacts['e:' . $row['data']['email']] ?? null) : null);

        if ($earlier === null) {
            if ($row['normalized_phone'] !== null) {
                $contacts['p:' . $row['normalized_phone']] = $line;
            }
            if ($row['data']['email'] !== null) {
                $contacts['e:' . $row['data']['email']] = $line;
            }
        }

        if ($earlier !== null) {
            return $mode === 'skip'
                ? ['skipped', "Same contact as row {$earlier}."]
                : ['duplicate', "Same contact as row {$earlier} — added to that lead as a repeat enquiry."];
        }

        $matches = $this->duplicates->matchingLeads($import->business_id, $row['data']['phone'], $row['data']['email']);
        if ($open = $matches->first(fn (Lead $lead) => $lead->isOpen())) {
            return $mode === 'skip'
                ? ['skipped', "Already a lead: \"{$open->name}\" (#{$open->id})."]
                : ['duplicate', "Already a lead: \"{$open->name}\" (#{$open->id}) — added to it as a repeat enquiry."];
        }
        if ($closed = $matches->first()) {
            return ['duplicate', "New lead, flagged as a possible duplicate of the closed lead \"{$closed->name}\" (#{$closed->id})."];
        }
        if ($customer = $this->duplicates->matchingCustomers($import->business_id, $row['data']['phone'], $row['data']['email'])->first()) {
            return ['duplicate', "New lead, linked to the customer \"{$customer->name}\"."];
        }

        return ['ok', null];
    }

    // ── Run ───────────────────────────────────────────────────────────────

    /** Run a previewed import now, or queue it when it is big. Only the first call starts it. */
    public function start(LeadImport $import): LeadImport
    {
        $queue  = $import->total_rows > self::QUEUE_ABOVE_ROWS;
        $status = $queue ? LeadImport::STATUS_QUEUED : LeadImport::STATUS_PROCESSING;

        $claimed = LeadImport::query()
            ->whereKey($import->id)
            ->where('status', LeadImport::STATUS_PREVIEWED)
            ->update(['status' => $status, 'started_at' => now()]);
        if ($claimed === 0) {
            throw ValidationException::withMessages(['import' => 'This import has already been started.']);
        }

        if ($queue) {
            RunLeadImportJob::dispatch($import->id);
        } else {
            $this->run($import->fresh());
        }

        return $import->fresh();
    }

    public function run(LeadImport $import): void
    {
        $import->update(['status' => LeadImport::STATUS_PROCESSING, 'started_at' => $import->started_at ?? now()]);

        try {
            $project = Project::query()->where('business_id', $import->business_id)->find($import->project_id);
            if ($project === null) {
                throw new \RuntimeException('The pipeline for this import no longer exists.');
            }

            $table   = $this->readImport($import);
            $context = $this->context($import, $table['headers']);
            $skipDuplicates = ($import->options['duplicates'] ?? 'log') === 'skip';

            $counts    = ['created_count' => 0, 'duplicate_count' => 0, 'skipped_count' => 0, 'failed_count' => 0];
            $errorRows = [];
            $processed = 0;

            foreach ($table['rows'] as $line => $cells) {
                $outcome = $this->importRow($import, $project, $cells, $context, $skipDuplicates);

                $counts[$outcome[0] . '_count']++;
                if ($outcome[0] === 'failed') {
                    $errorRows[] = ['row' => $line, 'cells' => $cells, 'reason' => $outcome[1]];
                }

                if (++$processed % 25 === 0) {
                    $import->update(['processed_rows' => $processed] + $counts);
                }
            }

            $import->update([
                'status'         => LeadImport::STATUS_COMPLETED,
                'processed_rows' => $processed,
                'error_rows'     => $errorRows === [] ? null : ['headers' => $table['headers'], 'rows' => $errorRows],
                'finished_at'    => now(),
            ] + $counts);
        } catch (\Throwable $e) {
            $import->update(['status' => LeadImport::STATUS_FAILED, 'error' => mb_substr($e->getMessage(), 0, 500), 'finished_at' => now()]);
            report($e);
        }
    }

    /** @return array{0: string, 1?: string} created|duplicate|skipped|failed, and why it failed */
    private function importRow(LeadImport $import, Project $project, array $cells, array $context, bool $skipDuplicates): array
    {
        $row = $this->evaluate($cells, $context);
        if ($row['errors'] !== []) {
            return ['failed', implode(' ', $row['errors'])];
        }

        $alreadyIn = DB::table('crm_lead_import_keys')
            ->where('business_id', $import->business_id)
            ->where('row_key', $row['key'])
            ->exists();
        if ($alreadyIn) {
            return ['skipped'];
        }

        if ($skipDuplicates && $this->duplicates->matchingLeads($import->business_id, $row['data']['phone'], $row['data']['email'])->contains(fn (Lead $l) => $l->isOpen())) {
            return ['skipped'];
        }

        try {
            $result = $this->intake->capture($row['data'], $row['source'], $project, $import->user_id);
        } catch (ValidationException $e) {
            return ['failed', implode(' ', $e->validator->errors()->all())];
        } catch (\Throwable $e) {
            report($e);

            return ['failed', 'Could not be saved: ' . mb_substr($e->getMessage(), 0, 200)];
        }

        // Keep the enquiry's own date, so lists and reports show when the person asked
        if ($result->created && $row['date'] !== null) {
            $result->lead->forceFill(['created_at' => $row['date']])->saveQuietly();
        }

        DB::table('crm_lead_import_keys')->insertOrIgnore([
            'business_id' => $import->business_id,
            'row_key'     => $row['key'],
            'lead_id'     => $result->lead->id,
            'import_id'   => $import->id,
            'created_at'  => now(),
        ]);

        return [$result->created ? 'created' : 'duplicate'];
    }

    /** Remove an import that was never run, with its file. */
    public function discard(LeadImport $import): void
    {
        Storage::disk('local')->delete($import->file_path);
        $import->delete();
    }

    // ── Files ─────────────────────────────────────────────────────────────

    public function templateCsv(): string
    {
        return $this->toCsv([
            self::TEMPLATE_HEADERS,
            ['Kasun Perera', '0771234567', 'kasun@example.com', 'Perera Traders', 'csv', 'Solar panel 5kW', now()->format('Y-m-d'), 'Called about pricing'],
            ['Nadeesha Silva', '+94 71 555 0123', '', '', 'facebook', 'Inverter', now()->subDay()->format('Y-m-d'), ''],
        ]);
    }

    /** The rows that could not be imported, as they were in the file plus the reason. */
    public function errorRowsCsv(LeadImport $import): string
    {
        $errors = $import->error_rows ?? ['headers' => [], 'rows' => []];

        return $this->toCsv([
            ['Row', ...$errors['headers'], 'Error'],
            ...array_map(fn ($r) => [$r['row'], ...$r['cells'], $r['reason']], $errors['rows']),
        ]);
    }

    private function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");    // Excel needs the BOM to read UTF-8
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    // ── Presenting ────────────────────────────────────────────────────────

    public function present(LeadImport $import): array
    {
        return [
            'id'              => $import->id,
            'file_name'       => $import->file_name,
            'format'          => $import->format,
            'format_label'    => LeadImport::formatLabel($import->format),
            'source'          => $import->source,
            'source_label'    => Lead::sourceLabel($import->source),
            'project'         => $import->project ? ['id' => $import->project->id, 'name' => $import->project->name] : null,
            'mapping'         => $import->mapping ?? [],
            'duplicates'      => $import->options['duplicates'] ?? 'log',
            'status'          => $import->status,
            'total_rows'      => $import->total_rows,
            'processed_rows'  => $import->processed_rows,
            'created_count'   => $import->created_count,
            'duplicate_count' => $import->duplicate_count,
            'skipped_count'   => $import->skipped_count,
            'failed_count'    => $import->failed_count,
            'has_error_rows'  => ! empty($import->error_rows['rows'] ?? []),
            'error'           => $import->error,
            'user_name'       => $import->user?->name,
            'created_at'      => $import->created_at?->toIso8601String(),
            'finished_at'     => $import->finished_at?->toIso8601String(),
            'queued'          => $import->total_rows > self::QUEUE_ABOVE_ROWS,
        ];
    }

    /** Field, format and duplicate choices for the desktop import screen. */
    public function options(): array
    {
        return [
            'fields'          => collect(self::FIELDS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'formats'         => collect(LeadImport::FORMATS)->map(fn ($f, $key) => ['key' => $key, 'label' => $f[0], 'source' => $f[1]])->values(),
            'sources'         => Lead::sourceOptions(),
            'duplicate_modes' => collect(self::DUPLICATE_MODES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'max_rows'        => LeadImportReader::MAX_ROWS,
            'queue_above'     => self::QUEUE_ABOVE_ROWS,
        ];
    }

    // ── Row checks ────────────────────────────────────────────────────────

    /** What an import needs to turn a row into lead data. */
    private function context(LeadImport $import, array $headers): array
    {
        return [
            'mapping'      => $import->mapping ?? [],
            'headers'      => $headers,
            'source'       => $import->source,
            'country_code' => $this->settings->phoneCountryCode($import->business_id),
            'day_first'    => (bool) ($import->options['day_first'] ?? true),
        ];
    }

    /**
     * Turn the cells of a row into lead data and say what is wrong with it.
     *
     * @return array{data: array, source: string, errors: list<string>, warnings: list<string>, key: string, date: ?CarbonImmutable, normalized_phone: ?string}
     */
    private function evaluate(array $cells, array $context): array
    {
        $values = [];
        $notes  = [];
        foreach ($context['mapping'] as $i => $target) {
            $value = trim((string) ($cells[$i] ?? ''));
            if ($value === '' || $target === self::TARGET_IGNORE) {
                continue;
            }
            if ($target === self::TARGET_NOTE) {
                $notes[] = $this->headerLabel($context, $i) . ': ' . $value;
            } elseif (! isset($values[$target])) {
                $values[$target] = $value;
            }
        }

        $errors   = [];
        $warnings = [];

        $name = $values['name'] ?? trim(($values['first_name'] ?? '') . ' ' . ($values['last_name'] ?? ''));

        // Facebook writes phone numbers as "p:+94771234567"
        $phone      = isset($values['phone']) ? trim(preg_replace('/^p:/i', '', $values['phone'])) : null;
        $normalized = PhoneNormalizer::normalize($phone, $context['country_code']);
        if ($phone !== null && $phone !== '' && $normalized === null) {
            $errors[] = "Phone number \"{$phone}\" is not valid.";
        }

        $email = isset($values['email']) ? mb_strtolower($values['email']) : null;
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = "Email \"{$email}\" is not valid.";
        }

        if (($phone === null || $phone === '') && $email === null) {
            $errors[] = 'No phone number or email.';
        }

        $value = null;
        if (isset($values['estimated_value'])) {
            // "Rs. 250,000" → 250000; free text such as "Below 1M" is kept in the notes instead
            $clean = preg_replace('/[\s,]/', '', preg_replace('/^(rs\.?|lkr|usd|\$|€|£)\s*/i', '', $values['estimated_value']));
            if (is_numeric($clean) && (float) $clean >= 0) {
                $value = round((float) $clean, 2);
            } else {
                $notes[]    = $this->headerLabel($context, (int) array_search('estimated_value', $context['mapping'], true)) . ': ' . $values['estimated_value'];
                $warnings[] = "Estimated value \"{$values['estimated_value']}\" is not a number — kept in the notes.";
            }
        }

        $date = null;
        if (isset($values['date'])) {
            $date = $this->parseDate($values['date'], $context['day_first']);
            if ($date === null) {
                $warnings[] = "Date \"{$values['date']}\" not recognised — today's date is used.";
            } elseif ($date->isFuture()) {
                $date = null;
            }
        }

        $notesText = implode("\n", array_filter([$values['notes'] ?? null, ...$notes]));
        $externalId = isset($values['external_id']) ? mb_substr($values['external_id'], 0, 191) : null;

        $data = [
            'name'                  => $name === '' ? null : mb_substr($name, 0, 150),
            'company'               => isset($values['company']) ? mb_substr($values['company'], 0, 255) : null,
            'phone'                 => $phone === '' ? null : ($phone === null ? null : mb_substr($phone, 0, 50)),
            'email'                 => $email,
            'product_interest_note' => isset($values['product_interest']) ? mb_substr($values['product_interest'], 0, 2000) : null,
            'estimated_value'       => $value,
            'notes'                 => $notesText === '' ? null : mb_substr($notesText, 0, 5000),
            'external_id'           => $externalId,
        ];

        $key = $externalId !== null
            ? 'ext|' . $context['source'] . '|' . $externalId
            : 'row|' . implode('|', [$values['date'] ?? '', $data['name'] ?? '', $normalized ?? $phone ?? '', $email ?? '', $data['product_interest_note'] ?? '']);

        return [
            'data'             => $data,
            'source'           => $this->sourceFrom($values['source'] ?? null, $context['source']),
            'errors'           => $errors,
            'warnings'         => $warnings,
            'key'              => sha1($key),
            'date'             => $date,
            'normalized_phone' => $normalized,
        ];
    }

    private function headerLabel(array $context, int $i): string
    {
        return $context['headers'][$i] ?? ('Column ' . ($i + 1));
    }

    /** @param list<string> $keys @return array<string, true> the keys already imported */
    private function importedKeys(int $businessId, array $keys): array
    {
        $found = [];
        foreach (array_chunk(array_values(array_unique($keys)), 500) as $chunk) {
            foreach (DB::table('crm_lead_import_keys')->where('business_id', $businessId)->whereIn('row_key', $chunk)->pluck('row_key') as $key) {
                $found[$key] = true;
            }
        }

        return $found;
    }

    /** A Lead::SOURCES key for a source / platform cell ("fb", "ig", "Facebook", …). */
    private function sourceFrom(?string $value, string $default): string
    {
        $v = mb_strtolower(trim((string) $value));
        if ($v === '') {
            return $default;
        }

        $aliases = ['fb' => 'facebook', 'ig' => 'instagram', 'insta' => 'instagram', 'wa' => 'whatsapp', 'google form' => 'google-form', 'google forms' => 'google-form', 'google_form' => 'google-form', 'web form' => 'public-form', 'web_form' => 'public-form'];
        if (isset($aliases[$v])) {
            return $aliases[$v];
        }
        foreach (Lead::SOURCES as $key => $s) {
            if ($v === $key || $v === mb_strtolower($s[0])) {
                return $key;
            }
        }

        return $default;
    }

    /** Whether slash dates in the date column are day-first (31/10/2026) or month-first (10/31/2026). */
    private function dayFirst(array $rows, array $mapping, string $format): bool
    {
        $column = array_search('date', $mapping, true);
        if ($column !== false) {
            foreach ($rows as $cells) {
                if (preg_match('#^(\d{1,2})[/.\-](\d{1,2})[/.\-]\d{2,4}#', (string) ($cells[$column] ?? ''), $m)) {
                    if ((int) $m[1] > 12) {
                        return true;
                    }
                    if ((int) $m[2] > 12) {
                        return false;
                    }
                }
            }
        }

        // Google Forms timestamps are month-first unless the sheet's locale says otherwise
        return $format !== 'google_form';
    }

    private function parseDate(string $value, bool $dayFirst): ?CarbonImmutable
    {
        $value = trim($value);

        // An Excel date serial (from XLSX files)
        if (is_numeric($value)) {
            $n = (float) $value;

            return $n > 20000 && $n < 80000 ? CarbonImmutable::instance(ExcelDate::excelToDateTimeObject($n)) : null;
        }

        if (preg_match('#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{2,4})(?:[ T,]+(\d{1,2}):(\d{2})(?::(\d{2}))?\s*([AaPp][Mm])?)?$#', $value, $m)) {
            [$day, $month] = $dayFirst ? [(int) $m[1], (int) $m[2]] : [(int) $m[2], (int) $m[1]];
            $year   = (int) $m[3] < 100 ? 2000 + (int) $m[3] : (int) $m[3];
            $hour   = (int) ($m[4] ?? 0);
            $ampm   = strtolower($m[7] ?? '');
            if ($ampm === 'pm' && $hour < 12) {
                $hour += 12;
            } elseif ($ampm === 'am' && $hour === 12) {
                $hour = 0;
            }

            return checkdate($month, $day, $year) && $hour < 24
                ? CarbonImmutable::create($year, $month, $day, $hour, (int) ($m[5] ?? 0), (int) ($m[6] ?? 0))
                : null;
        }

        try {
            return CarbonImmutable::parse($value)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /** @return array{headers: list<string>, rows: array<int, list<string>>} */
    private function readImport(LeadImport $import): array
    {
        $path = Storage::disk('local')->path($import->file_path);
        if (! is_file($path)) {
            throw ValidationException::withMessages(['file' => 'The uploaded file is no longer available. Upload it again.']);
        }

        return $this->reader->read($path, pathinfo($import->file_path, PATHINFO_EXTENSION));
    }

    /** @return list<string> */
    private function validMapping(array $mapping, array $headers): array
    {
        $allowed = [...array_keys(self::FIELDS), self::TARGET_NOTE, self::TARGET_IGNORE];
        $clean   = [];
        $used    = [];

        foreach (array_keys($headers) as $i) {
            $target = (string) ($mapping[$i] ?? self::TARGET_IGNORE);
            if (! in_array($target, $allowed, true)) {
                throw ValidationException::withMessages(['mapping' => "Unknown field \"{$target}\" for column \"{$headers[$i]}\"."]);
            }
            if ($target !== self::TARGET_NOTE && $target !== self::TARGET_IGNORE) {
                if (isset($used[$target])) {
                    throw ValidationException::withMessages(['mapping' => self::FIELDS[$target] . ' is chosen for more than one column.']);
                }
                $used[$target] = true;
            }
            $clean[] = $target;
        }

        if (! isset($used['phone']) && ! isset($used['email'])) {
            throw ValidationException::withMessages(['mapping' => 'Choose the column that holds the phone number or the email.']);
        }

        return $clean;
    }

    private function normalizeHeader(string $header): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($header)));
    }
}
