<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Support\LeadImportReader;
use Modules\Pos\Models\Customer;
use Modules\Pos\Models\CustomerCategory;
use Modules\Pos\Services\PosSettingsService;

/**
 * Customer import, for the web (CSV / Excel file: upload → preview → run) and the desktop
 * (rows parsed on the client, POST /api/pos/customers/import).
 *
 * Every row is checked the same way: required fields, a valid email, and whether the contact
 * is already a customer (phone compared in normalised form, email case-insensitively) or
 * already appeared higher up in the same file. A duplicate is skipped or, when the user chose
 * so, updates the existing customer with the row's non-empty values.
 */
class CustomerImportService
{
    /** What to do with a row whose phone / email belongs to an existing customer. */
    public const DUPLICATE_MODES = [
        'skip'   => 'Skip the row and keep the existing customer as it is',
        'update' => 'Update the existing customer with the row\'s details',
    ];

    public const COLUMNS = ['name', 'phone', 'email', 'address', 'notes', 'category', 'customer_type'];

    /** Header (normalised) => column. Same aliases as the desktop import. */
    private const HEADER_COLUMNS = [
        'name' => 'name', 'customer name' => 'name', 'customer' => 'name', 'full name' => 'name',
        'phone' => 'phone', 'phone number' => 'phone', 'mobile' => 'phone', 'contact' => 'phone', 'contact number' => 'phone',
        'email' => 'email', 'email address' => 'email', 'e mail' => 'email',
        'address' => 'address',
        'notes' => 'notes', 'note' => 'notes', 'remarks' => 'notes',
        'category' => 'category', 'customer category' => 'category',
        'customer type' => 'customer_type', 'type' => 'customer_type',
    ];

    public function __construct(
        private readonly CustomerService $customers,
        private readonly CrmSettingsService $settings,
        private readonly PosSettingsService $posSettings,
        private readonly LeadImportReader $reader,
    ) {}

    // ── Files ─────────────────────────────────────────────────────────────

    /**
     * Read an uploaded file into import rows.
     *
     * @return array{headers: list<string>, rows: list<array>} each row has the COLUMNS, `row` (its line in the file) and `cells`
     */
    public function readFile(string $path, string $extension): array
    {
        try {
            $table = $this->reader->read($path, $extension);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['file' => str_replace('no leads', 'no customers', $e->validator->errors()->first())]);
        }

        $columns = [];
        foreach ($table['headers'] as $i => $header) {
            $column = self::HEADER_COLUMNS[trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($header)))] ?? null;
            if ($column !== null && ! isset($columns[$column])) {
                $columns[$column] = $i;
            }
        }
        if (! isset($columns['name'])) {
            throw ValidationException::withMessages(['file' => 'The file needs a "name" column (see the template).']);
        }

        $rows = [];
        foreach ($table['rows'] as $line => $cells) {
            $row = ['row' => $line, 'cells' => $cells];
            foreach ($columns as $column => $i) {
                $row[$column] = $cells[$i] ?? '';
            }
            $rows[] = $row;
        }

        return ['headers' => $table['headers'], 'rows' => $rows];
    }

    public function templateCsv(): string
    {
        return $this->toCsv([
            self::COLUMNS,
            ['Kasun Perera', '0771234567', 'kasun@example.com', '12 Galle Rd, Colombo', 'Prefers WhatsApp', 'VIP', 'retail'],
            ['Perera Traders', '+94 71 555 0123', '', '45 Main St, Kandy', '', 'Wholesale', 'wholesale'],
        ]);
    }

    /** The rows that were not imported, as they were in the file plus the reason. */
    public function errorRowsCsv(array $headers, array $rows): string
    {
        return $this->toCsv([
            ['Row', ...$headers, 'Error'],
            ...array_map(fn ($r) => [$r['row'], ...($r['cells'] ?? []), $r['reason']], $rows),
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

    // ── Check & run ───────────────────────────────────────────────────────

    /**
     * What importing the rows would do, without saving anything.
     *
     * @param  list<array>  $rows  the COLUMNS plus an optional `row` number
     * @return array{rows: list<array>, totals: array<string, int>}
     */
    public function preview(Business $business, array $rows, string $duplicateMode): array
    {
        return $this->process($business, $rows, $duplicateMode, false);
    }

    /** As preview(), but creates and updates the customers. */
    public function import(Business $business, array $rows, string $duplicateMode): array
    {
        return $this->process($business, $rows, $duplicateMode, true);
    }

    /**
     * @return array{rows: list<array{row: int, name: ?string, phone: ?string, email: ?string, status: string, reason: ?string, customer_id: ?int, cells?: list<string>}>, totals: array<string, int>}
     *         status: create | update | skip | error
     */
    private function process(Business $business, array $rows, string $duplicateMode, bool $save): array
    {
        if (! array_key_exists($duplicateMode, self::DUPLICATE_MODES)) {
            throw ValidationException::withMessages(['duplicates' => 'Choose whether duplicates are skipped or update the existing customer.']);
        }

        $required = $this->posSettings->forBusiness($business);
        $seen     = [];
        $catCache = [];
        $results  = [];
        $totals   = ['total' => 0, 'create' => 0, 'update' => 0, 'skip' => 0, 'error' => 0];

        foreach (array_values($rows) as $idx => $raw) {
            $line = (int) ($raw['row'] ?? $idx + 1);
            $data = $this->clean($raw);

            [$status, $reason, $existing] = $this->decide($business, $data, $line, $required, $seen, $duplicateMode);

            if ($save && in_array($status, ['create', 'update'], true)) {
                try {
                    $existing = $this->save($business, $data, $existing, $catCache);
                } catch (\Throwable $e) {
                    report($e);
                    [$status, $reason] = ['error', 'Could not be saved: ' . mb_substr($e->getMessage(), 0, 200)];
                }
            }

            $totals['total']++;
            $totals[$status]++;
            $result = [
                'row'         => $line,
                'name'        => $data['name'],
                'phone'       => $data['phone'],
                'email'       => $data['email'],
                'status'      => $status,
                'reason'      => $reason,
                'customer_id' => $existing?->id,
            ];
            // File imports keep the original cells for the error rows download
            if (isset($raw['cells'])) {
                $result['cells'] = $raw['cells'];
            }
            $results[] = $result;
        }

        return ['rows' => $results, 'totals' => $totals];
    }

    /** @return array{0: string, 1: ?string, 2: ?Customer} status, why, and the customer it matches */
    private function decide(Business $business, array $data, int $line, array $required, array &$seen, string $mode): array
    {
        $errors = Validator::make($data, [
            'name'          => ['required', 'string', 'max:255'],
            'phone'         => [! empty($required['customer_require_phone']) ? 'required' : 'nullable', 'string', 'max:50'],
            'email'         => [! empty($required['customer_require_email']) ? 'required' : 'nullable', 'email', 'max:255'],
            'address'       => [! empty($required['customer_require_address']) ? 'required' : 'nullable', 'string', 'max:500'],
            'notes'         => ['nullable', 'string', 'max:1000'],
            'category'      => ['nullable', 'string', 'max:120'],
            'customer_type' => ['nullable', 'in:retail,wholesale'],
        ], [
            'customer_type.in' => 'Customer type must be "retail" or "wholesale".',
        ])->errors()->all();
        if ($errors !== []) {
            return ['error', implode(' ', $errors), null];
        }

        // The same person twice in one file: the first row wins
        $normalized = $this->settings->normalizePhone($business, $data['phone']);
        $keys       = array_filter([$normalized !== null ? 'p:' . $normalized : null, $data['email'] !== null ? 'e:' . $data['email'] : null]);
        foreach ($keys as $key) {
            if (isset($seen[$key])) {
                return ['skip', "Same contact as row {$seen[$key]}.", null];
            }
        }
        foreach ($keys as $key) {
            $seen[$key] = $line;
        }

        $existing = $this->customers->duplicatesOf($business->id, $data['phone'], $data['email'])->first();
        if ($existing === null) {
            return ['create', null, null];
        }

        return $mode === 'update'
            ? ['update', "Updates the existing customer \"{$existing->name}\" (#{$existing->id}).", $existing]
            : ['skip', "Already a customer: \"{$existing->name}\" (#{$existing->id}).", $existing];
    }

    /** A new customer, or the existing one with the row's non-empty values. */
    private function save(Business $business, array $data, ?Customer $existing, array &$catCache): Customer
    {
        $categoryId = null;
        if ($data['category'] !== null) {
            $catCache[$data['category']] ??= CustomerCategory::firstOrCreate(['business_id' => $business->id, 'name' => $data['category']])->id;
            $categoryId = $catCache[$data['category']];
        }

        $values = array_filter([
            'name'                 => $data['name'],
            'phone'                => $data['phone'],
            'email'                => $data['email'],
            'address'              => $data['address'],
            'notes'                => $data['notes'],
            'customer_type'        => $data['customer_type'],
            'customer_category_id' => $categoryId,
        ], fn ($v) => $v !== null);

        if ($existing !== null) {
            $existing->update($values);

            return $existing;
        }

        return Customer::create($values + ['business_id' => $business->id, 'customer_type' => 'retail']);
    }

    /** Trimmed strings, with empty cells as null. */
    private function clean(array $raw): array
    {
        $data = [];
        foreach (self::COLUMNS as $column) {
            $value         = isset($raw[$column]) && is_scalar($raw[$column]) ? trim((string) $raw[$column]) : '';
            $data[$column] = $value === '' ? null : $value;
        }
        $data['email']         = $data['email'] !== null ? mb_strtolower($data['email']) : null;
        $data['customer_type'] = $data['customer_type'] !== null ? mb_strtolower($data['customer_type']) : null;

        return $data;
    }

    /** Duplicate choices for the import screens. */
    public function options(): array
    {
        return [
            'duplicate_modes' => collect(self::DUPLICATE_MODES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'columns'         => self::COLUMNS,
            'max_rows'        => LeadImportReader::MAX_ROWS,
        ];
    }
}
