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
use Modules\CRM\Models\Division;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadImport;
use Modules\CRM\Models\Project;
use Modules\CRM\Support\LeadImportReader;
use Modules\CRM\Support\PhoneNormalizer;
use Modules\HRManagement\Models\Department;
use Modules\HRManagement\Services\DepartmentService;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Bulk lead upload: upload → map columns → preview → run.
 *
 * The file can be our CSV template, a Google Forms responses export or a Facebook Lead Ads
 * export; the format is detected from the header row and each column gets a suggested field,
 * which the user can change. Columns that are not a lead field are kept as "Question: answer"
 * lines in the lead's notes. Every row goes through LeadIntakeService, so duplicates follow the
 * intake rule (an open lead gets the enquiry logged on it, a closed lead or a customer flags the
 * new lead for review). Each imported row is remembered by a key per pipeline, so uploading a
 * newer export of the same form only brings in the new responses, while the same people can
 * still be imported into another pipeline.
 *
 * A file with a department column can be split: the rows are bundled per department value, and
 * each bundle goes into a department (an existing one, or one created for it) and one of that
 * department's relations (or a new one, named after the department and the file). Nothing is
 * created until the import runs; split rows land straight in their relation, without the
 * marketing intake routing, and count as duplicates only within it.
 *
 * A file with a division column is split too: without a department column all its rows are one
 * bundle, and the user says which department they belong to (one is suggested from the division
 * names). The division values of each bundle are matched to that department's divisions; a value
 * with no match can be given a new division, made when the import runs, or left without one.
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
        'month'            => 'Enquiry month',
        'estimated_value'  => 'Estimated value',
        'notes'            => 'Notes',
        'external_id'      => 'Lead ID (from the source)',
        'department'       => 'Department',
        'division'         => 'Division',
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
        'department' => 'department', 'dept' => 'department', 'department name' => 'department',
        'division' => 'division', 'devision' => 'division', 'divison' => 'division', 'division name' => 'division', 'sub department' => 'division',
        'month' => 'month', 'enquiry month' => 'month', 'inquiry month' => 'month',
    ];

    /** Row numbers of the sheet itself ("Sl.No.", "No"), not about the lead. */
    private const IGNORE_COLUMNS = ['sl no', 's no', 'sr no', 'serial no', 'serial number', 'no', 'row', 'row no'];

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
        private readonly CrmOrgService $org,
        private readonly ProjectService $projects,
        private readonly DepartmentService $departments,
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

            if (in_array($h, self::IGNORE_COLUMNS, true) || ($format === 'facebook' && in_array($h, self::FACEBOOK_IGNORE_COLUMNS, true))) {
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
     * With a department plan (see resolvePlan) the rows are checked against the relation of
     * their department bundle and $project is not used.
     *
     * @param  list<string>      $mapping
     * @param  list<array>|null  $plan
     * @return array{rows: list<array>, totals: array<string, int>, groups: ?list<array>}
     */
    public function preview(LeadImport $import, array $mapping, string $source, ?Project $project, string $duplicateMode, ?array $plan = null): array
    {
        $table   = $this->readImport($import);
        $mapping = $this->validMapping($mapping, $table['headers']);
        if ($plan === null && $project === null) {
            throw ValidationException::withMessages(['project_id' => 'Choose the relation to import into.']);
        }

        $import->update([
            'mapping'    => $mapping,
            'source'     => $source,
            'project_id' => $plan === null ? $project->id : $this->planProjectId($plan),
            'options'    => array_filter([
                'duplicates' => $duplicateMode,
                'day_first'  => $this->dayFirst($table['rows'], $mapping, $import->format),
                'groups'     => $plan,
            ], fn ($v) => $v !== null),
            'status'     => LeadImport::STATUS_PREVIEWED,
            'total_rows' => count($table['rows']),
        ]);

        $context   = $this->context($import, $table['headers']);
        $evaluated = array_map(fn ($cells) => $this->evaluate($cells, $context), $table['rows']);
        $groups    = $plan === null ? [] : array_column($plan, null, 'key');

        // Each relation the rows go into: what is already in it, and the rows and contacts seen so far
        $targets = [];
        foreach ($evaluated as $line => $row) {
            $group = $plan === null ? null : ($groups[$this->groupKey($row['department'])] ?? null);
            if ($plan !== null && ($group === null || $group['skip'])) {
                continue;
            }
            $target = $plan === null ? '*' : $this->planTarget($group);
            $targets[$target] ??= ['keys' => [], 'project_id' => $plan === null ? $project->id : $group['project_id']];
            $targets[$target]['keys'][] = $row['key'];
        }
        foreach ($targets as &$t) {
            // A relation created by this import holds nothing yet
            $t['imported']  = $t['project_id'] === null ? [] : $this->importedKeys($import->business_id, $t['project_id'], $t['keys']);
            $t['pipelines'] = $t['project_id'] === null ? [] : ($plan === null ? $this->intake->duplicatePipelines($project) : [$t['project_id']]);
            $t['seen']      = [];
            $t['contacts']  = [];
        }
        unset($t);

        $rows        = [];
        $empty       = ['total' => 0, 'ok' => 0, 'duplicate' => 0, 'error' => 0, 'skipped' => 0];
        $totals      = $empty;
        $groupTotals = [];
        foreach ($evaluated as $line => $row) {
            $group = $plan === null ? null : ($groups[$this->groupKey($row['department'])] ?? null);

            if ($plan !== null && ($group === null || $group['skip'])) {
                [$status, $reason] = ['skipped', 'Department not imported.'];
            } else {
                $t = &$targets[$plan === null ? '*' : $this->planTarget($group)];
                [$status, $reason] = $this->previewStatus($import, $row, $line, $t['imported'], $t['seen'], $t['contacts'], $duplicateMode, $t['pipelines']);
                unset($t);
            }

            $rows[] = [
                'row'      => $line,
                'group'    => $group['key'] ?? null,
                'division' => $group === null || $row['division'] === '' ? null : ($this->planDivision($group, $row['division'])['division_name'] ?? null),
                'name'     => $row['data']['name'],
                'phone'    => $row['data']['phone'],
                'email'    => $row['data']['email'],
                'status'   => $status,
                'reason'   => $reason,
                'warnings' => $row['warnings'],
            ];

            $totals['total']++;
            $totals[$status]++;
            if ($group !== null) {
                $groupTotals[$group['key']] ??= $empty;
                $groupTotals[$group['key']]['total']++;
                $groupTotals[$group['key']][$status]++;
            }
        }

        return [
            'rows'   => $rows,
            'totals' => $totals,
            'groups' => $plan === null ? null : array_map(fn ($g) => $g + ['totals' => $groupTotals[$g['key']] ?? $empty], $plan),
        ];
    }

    // ── Department split ──────────────────────────────────────────────────

    /**
     * The rows bundled by the department column (one bundle when only a division column is
     * mapped), each with a suggested department (one whose name matches, else the one most of
     * its division values belong to) and relation (one named for this file, else the
     * department's main one), a name for a new relation the user can change, and its division
     * values. Every department comes with its relations and active divisions.
     *
     * @param  callable(Project): bool  $canUse  whether the user may add leads to a relation
     * @return array{column: ?int, division_column: ?int, groups: list<array>, departments: list<array>}
     */
    public function departmentGroups(LeadImport $import, array $mapping, callable $canUse): array
    {
        $table    = $this->readImport($import);
        $mapping  = $this->validMapping($mapping, $table['headers']);
        $column   = $this->departmentColumn($mapping);
        $division = $this->divisionColumn($mapping);

        $departments = $this->departmentChoices($import->business_id, $canUse);
        $taken       = Project::query()->where('business_id', $import->business_id)->pluck('name')
            ->mapWithKeys(fn ($name) => [mb_strtolower($name) => true])->all();

        $groups = [];
        foreach ($this->fileGroups($table['rows'], $column, $division) as $key => $group) {
            $department = $this->matchDepartment($group['value'], $departments)
                ?? ($column === null ? $this->departmentOfDivisions($group['divisions'], $departments) : null);
            $base       = $this->relationNameFor($group['value'] !== '' ? $group['value'] : ($department['name'] ?? ''), $import->file_name);
            $relations  = $department['relations'] ?? [];
            $relation   = collect($relations)->first(fn ($r) => mb_strtolower($r['name']) === mb_strtolower($base)) ?? ($relations[0] ?? null);
            $name       = $this->freeRelationName($base, $taken);
            $taken[mb_strtolower($name)] = true;

            $groups[] = [
                'key'           => $key,
                'value'         => $group['value'],
                'rows'          => $group['rows'],
                'department_id' => $department['id'] ?? null,
                'project_id'    => $relation['id'] ?? null,
                'relation_name' => $name,
                'divisions'     => array_values($group['divisions']),
            ];
        }

        return ['column' => $column, 'division_column' => $division, 'groups' => $groups, 'departments' => $departments];
    }

    /**
     * Check the department and relation chosen for each bundle of the file and turn them into
     * the plan kept on the import. A bundle either goes into an existing department or a new
     * one (by name), and into an existing relation of that department or a new one (by name);
     * a bundle with no department can go into any relation. "skip" leaves the bundle out.
     * Each division value of a bundle with a department goes into one of its divisions or a
     * new one (by name); a value left out of "divisions" gets no division.
     *
     * @param  list<array>              $choices  {key, skip?, department_id?, new_department?, project_id?, new_relation?, divisions?: list<{key, division_id?, new_division?}>}
     * @param  callable(Project): bool  $canUse
     * @return list<array>
     */
    public function resolvePlan(LeadImport $import, array $mapping, array $choices, callable $canUse): array
    {
        $table    = $this->readImport($import);
        $mapping  = $this->validMapping($mapping, $table['headers']);
        $column   = $this->departmentColumn($mapping);
        $division = $this->divisionColumn($mapping);

        $choices     = collect($choices)->keyBy(fn ($c) => (string) ($c['key'] ?? ''));
        $departments = Department::query()->where('business_id', $import->business_id)->get(['id', 'name'])->keyBy('id');
        $projects    = Project::query()->where('business_id', $import->business_id)->get()->keyBy('id');
        $divisions   = Division::query()->where('business_id', $import->business_id)->get(['id', 'department_id', 'name'])->keyBy('id');
        $fail        = fn (string $message) => throw ValidationException::withMessages(['groups' => $message]);

        $plan         = [];
        $newRelations = [];   // lower-case new relation name => lower-case department name
        foreach ($this->fileGroups($table['rows'], $column, $division) as $key => $group) {
            $label  = $column === null ? 'rows' : ($group['value'] === '' ? 'rows with no department' : '"' . $group['value'] . '" rows');
            $choice = $choices->get($key) ?? $fail("Choose where the {$label} go.");
            $entry  = [
                'key' => $key, 'value' => $group['value'], 'rows' => $group['rows'], 'skip' => false,
                'department_id' => null, 'department_name' => null, 'new_department' => false,
                'project_id' => null, 'relation_name' => null, 'new_relation' => false,
                'divisions' => [],
            ];

            if (! empty($choice['skip'])) {
                $plan[] = ['skip' => true] + $entry;
                continue;
            }

            $newDepartment = mb_substr(trim((string) ($choice['new_department'] ?? '')), 0, 100);
            if ($newDepartment !== '') {
                if ($same = $departments->first(fn (Department $d) => mb_strtolower($d->name) === mb_strtolower($newDepartment))) {
                    $fail("A department named \"{$same->name}\" already exists — choose it for the {$label}.");
                }
                $entry['department_name'] = $newDepartment;
                $entry['new_department']  = true;
            } elseif (! empty($choice['department_id'])) {
                $department = $departments->get((int) $choice['department_id']) ?? $fail("The department chosen for the {$label} no longer exists.");
                $entry['department_id']   = $department->id;
                $entry['department_name'] = $department->name;
            }

            $newRelation = mb_substr(trim((string) ($choice['new_relation'] ?? '')), 0, 150);
            if ($newRelation !== '') {
                if ($projects->contains(fn (Project $p) => mb_strtolower($p->name) === mb_strtolower($newRelation))) {
                    $fail("A relation named \"{$newRelation}\" already exists — choose it, or give the new relation another name.");
                }
                $owner = mb_strtolower((string) $entry['department_name']);
                if (($newRelations[mb_strtolower($newRelation)] ?? $owner) !== $owner) {
                    $fail("The new relation \"{$newRelation}\" is used for two departments — give each its own name.");
                }
                $newRelations[mb_strtolower($newRelation)] = $owner;
                $entry['relation_name'] = $newRelation;
                $entry['new_relation']  = true;
            } else {
                $project = ! empty($choice['project_id']) ? $projects->get((int) $choice['project_id']) : null;
                if ($project === null) {
                    $fail("Choose the relation for the {$label}, or create one.");
                }
                if ($entry['new_department'] || ($entry['department_id'] !== null && (int) $project->department_id !== $entry['department_id'])) {
                    $fail("The relation \"{$project->name}\" is not in the {$entry['department_name']} department.");
                }
                if (! $canUse($project)) {
                    $fail("You cannot add leads to the relation \"{$project->name}\".");
                }
                $entry['project_id']    = $project->id;
                $entry['relation_name'] = $project->name;
                $entry['department_name'] ??= $project->department_id ? $departments->get($project->department_id)?->name : null;
            }

            $entry['divisions'] = $this->resolveDivisions($group['divisions'], (array) ($choice['divisions'] ?? []), $entry, $divisions, $label, $fail);

            $plan[] = $entry;
        }

        if (collect($plan)->every(fn ($g) => $g['skip'])) {
            $fail('Every department is set to "Don\'t import" — choose where at least one goes.');
        }

        return $plan;
    }

    /**
     * Bundles keyed by groupKey, biggest first, "no department" last — one bundle of every row
     * when there is no department column — each with its division values, biggest first.
     *
     * @return array<string, array{value: string, rows: int, divisions: array<string, array{key: string, value: string, rows: int}>}>
     */
    private function fileGroups(array $rows, ?int $column, ?int $divisionColumn = null): array
    {
        $order  = fn ($a, $b) => [$a['value'] === '', -$a['rows'], $a['value']] <=> [$b['value'] === '', -$b['rows'], $b['value']];
        $groups = [];
        foreach ($rows as $cells) {
            $value = $column === null ? '' : $this->cleanDepartment((string) ($cells[$column] ?? ''));
            $key   = $this->groupKey($value);
            $groups[$key] ??= ['value' => $value, 'rows' => 0, 'divisions' => []];
            $groups[$key]['rows']++;

            $division = $divisionColumn === null ? '' : $this->cleanDepartment((string) ($cells[$divisionColumn] ?? ''));
            if ($division !== '') {
                $dKey = $this->divisionKey($division);
                $groups[$key]['divisions'][$dKey] ??= ['key' => $dKey, 'value' => $division, 'rows' => 0];
                $groups[$key]['divisions'][$dKey]['rows']++;
            }
        }

        uasort($groups, $order);
        foreach ($groups as &$group) {
            uasort($group['divisions'], $order);
        }
        unset($group);

        return $groups;
    }

    /** Prefixed like groupKey. */
    private function divisionKey(string $division): string
    {
        return 'v:' . mb_strtolower($division);
    }

    /** The plan's division entry for a row's division value, if it gets one. */
    private function planDivision(array $group, string $division): ?array
    {
        return collect($group['divisions'] ?? [])->firstWhere('key', $this->divisionKey($division));
    }

    /**
     * Check the division chosen for each division value of a bundle: an active one of the
     * bundle's department, or a new one for a department that is chosen or created.
     *
     * @param  array<string, array{key: string, value: string, rows: int}>  $values
     * @return list<array{key: string, value: string, rows: int, division_id: ?int, division_name: string, new_division: bool}>
     */
    private function resolveDivisions(array $values, array $choices, array $entry, $divisions, string $label, callable $fail): array
    {
        if ($entry['skip'] || $values === []) {
            return [];
        }

        $departmentId = $entry['department_id'] ?? ($entry['project_id'] !== null ? Project::query()->find($entry['project_id'])?->department_id : null);
        $choices      = collect($choices)->keyBy(fn ($c) => (string) ($c['key'] ?? ''));
        $resolved     = [];

        foreach ($values as $key => $value) {
            $choice = $choices->get($key, []);
            $name   = mb_substr(trim((string) ($choice['new_division'] ?? '')), 0, 100);
            $id     = (int) ($choice['division_id'] ?? 0);
            if ($name === '' && $id === 0) {
                continue;
            }
            if ($departmentId === null && ! $entry['new_department']) {
                $fail("Choose a department for the {$label} before giving them divisions.");
            }

            if ($name !== '') {
                $same = $entry['new_department'] ? null : $divisions->first(fn (Division $d) => (int) $d->department_id === (int) $departmentId && mb_strtolower($d->name) === mb_strtolower($name));
                if ($same) {
                    $fail("The {$entry['department_name']} department already has a division named \"{$same->name}\" — choose it for \"{$value['value']}\".");
                }
                $resolved[] = $value + ['division_id' => null, 'division_name' => $name, 'new_division' => true];
                continue;
            }

            $division = $divisions->get($id);
            if ($division === null || $entry['new_department'] || (int) $division->department_id !== (int) $departmentId) {
                $fail("The division chosen for \"{$value['value']}\" is not in the {$entry['department_name']} department.");
            }
            $resolved[] = $value + ['division_id' => $division->id, 'division_name' => $division->name, 'new_division' => false];
        }

        return $resolved;
    }

    /** The department most of these division values are divisions of, when one is. */
    private function departmentOfDivisions(array $values, array $departments): ?array
    {
        $best  = null;
        $score = 0;
        foreach ($departments as $department) {
            $names = array_map(fn ($d) => $this->looseName($d['name']), $department['divisions']);
            $rows  = array_sum(array_map(fn ($v) => in_array($this->looseName($v['value']), $names, true) ? $v['rows'] : 0, $values));
            if ($rows > $score) {
                [$best, $score] = [$department, $rows];
            }
        }

        return $best;
    }

    private function cleanDepartment(string $value): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', $value)), 0, 100);
    }

    /** Prefixed so a department such as "2024" stays a string key. */
    private function groupKey(string $department): string
    {
        return 'd:' . mb_strtolower($department);
    }

    /** Rows going into the same relation share their duplicate checks. */
    private function planTarget(array $group): string
    {
        return $group['new_relation'] ? 'new:' . mb_strtolower($group['relation_name']) : 'p:' . $group['project_id'];
    }

    private function planProjectId(array $plan): ?int
    {
        return collect($plan)->first(fn ($g) => ! $g['skip'] && $g['project_id'] !== null)['project_id'] ?? null;
    }

    /** The department column; null when only a division column splits the file. */
    private function departmentColumn(array $mapping): ?int
    {
        $column = array_search('department', $mapping, true);
        if ($column === false && $this->divisionColumn($mapping) === null) {
            throw ValidationException::withMessages(['mapping' => 'Choose the column that holds the department or the division.']);
        }

        return $column === false ? null : $column;
    }

    private function divisionColumn(array $mapping): ?int
    {
        $column = array_search('division', $mapping, true);

        return $column === false ? null : $column;
    }

    /** Every department, with its relations the user may add leads to (main one first) and its active divisions. */
    private function departmentChoices(int $businessId, callable $canUse): array
    {
        $divisions = Division::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'department_id', 'name'])
            ->groupBy('department_id');

        $relations = Project::query()
            ->where('business_id', $businessId)
            ->whereNotNull('department_id')
            ->orderBy('id')
            ->get()
            ->filter(fn (Project $p) => $canUse($p))
            ->groupBy('department_id');

        return Department::query()
            ->where('business_id', $businessId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Department $d) => [
                'id'        => $d->id,
                'name'      => $d->name,
                'relations' => $relations->get($d->id, collect())->map(fn (Project $p) => ['id' => $p->id, 'name' => $p->name])->values()->all(),
                'divisions' => $divisions->get($d->id, collect())->map(fn (Division $v) => ['id' => $v->id, 'name' => $v->name])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /** The same name ignoring case and punctuation, else the longest close one ("Finance" for "Financial"). */
    private function matchDepartment(string $value, array $departments): ?array
    {
        $v = $this->looseName($value);
        if ($v === '') {
            return null;
        }

        $best      = null;
        $bestScore = 0.0;
        foreach ($departments as $department) {
            $n = $this->looseName($department['name']);
            if ($n === $v) {
                return $department;
            }
            $prefix = strspn($n ^ $v, "\0");
            $score  = $prefix / max(1, min(strlen($n), strlen($v)));
            if ($prefix >= 5 && $score >= 0.8 && $score > $bestScore) {
                [$best, $bestScore] = [$department, $score];
            }
        }

        return $best;
    }

    private function looseName(string $name): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower($name));
    }

    /** "Operations – September Leads" for the Operations rows of September_Leads.csv. */
    private function relationNameFor(string $department, string $fileName): string
    {
        $file = trim(preg_replace('/[\s_]+/u', ' ', pathinfo($fileName, PATHINFO_FILENAME)));
        $head = $department === '' ? 'No department' : $department;

        return mb_substr($file === '' ? $head : $head . ' – ' . $file, 0, 150);
    }

    /** @param array<string, true> $taken lower-case names in use */
    private function freeRelationName(string $base, array $taken): string
    {
        $name = $base;
        for ($i = 2; isset($taken[mb_strtolower($name)]); $i++) {
            $name = mb_substr($base, 0, 140) . ' (' . $i . ')';
        }

        return $name;
    }

    /**
     * Create the departments, relations and divisions the plan asks for (once — a name already
     * created is reused) and point the plan at them.
     */
    private function createPlanTargets(LeadImport $import): void
    {
        $business = Business::query()->findOrFail($import->business_id);
        $plan     = $import->options['groups'];
        $made     = ['departments' => [], 'relations' => [], 'divisions' => []];

        foreach ($plan as $i => $g) {
            if ($g['skip']) {
                continue;
            }

            if ($g['new_department']) {
                $department = $made['departments'][mb_strtolower($g['department_name'])] ??=
                    Department::query()->where('business_id', $business->id)->where('name', $g['department_name'])->first()
                    ?? $this->departments->create($business, $g['department_name']);
                $g = array_merge($g, ['department_id' => $department->id, 'department_name' => $department->name, 'new_department' => false, 'created_department' => true]);
            }

            if ($g['new_relation']) {
                $project = $made['relations'][mb_strtolower($g['relation_name'])] ??=
                    Project::query()->where('business_id', $business->id)->where('name', $g['relation_name'])->first()
                    ?? $this->createRelation($business, $import, $g);
                $g = array_merge($g, ['project_id' => $project->id, 'relation_name' => $project->name, 'new_relation' => false, 'created_relation' => true]);
            }

            // A bundle into an existing relation takes the divisions of that relation's department
            $departmentId = $g['department_id'] ?? Project::query()->find($g['project_id'])?->department_id;
            foreach ($g['divisions'] ?? [] as $j => $d) {
                if (! $d['new_division'] || $departmentId === null) {
                    continue;
                }
                $division = $made['divisions'][$departmentId . ':' . mb_strtolower($d['division_name'])] ??=
                    Division::query()->where('department_id', $departmentId)->where('name', $d['division_name'])->first()
                    ?? $this->org->createDivision($business, Department::query()->findOrFail($departmentId), ['name' => $d['division_name']]);
                $g['divisions'][$j] = array_merge($d, ['division_id' => $division->id, 'division_name' => $division->name, 'new_division' => false, 'created_division' => true]);
            }

            $plan[$i] = $g;
        }

        $import->update([
            'options'    => array_merge($import->options, ['groups' => $plan]),
            'project_id' => $this->planProjectId($plan),
        ]);
    }

    private function createRelation(Business $business, LeadImport $import, array $group): Project
    {
        $project = $this->projects->create($business, [
            'name'        => $group['relation_name'],
            'description' => 'Leads imported from ' . $import->file_name . '.',
        ]);

        return $group['department_id'] === null
            ? $project
            : $this->org->linkPipeline($business, $this->org->departmentForBusiness($business, $group['department_id']), $project->id);
    }

    /** @return array{0: string, 1: ?string} status (ok|duplicate|error|skipped) and why */
    private function previewStatus(LeadImport $import, array $row, int $line, array $imported, array &$seenKeys, array &$contacts, string $mode, array $pipelines): array
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

        $matches = $this->duplicates->matchingLeads($import->business_id, $row['data']['phone'], $row['data']['email'], null, $pipelines);
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

        DB::transaction(function () use ($import, $status) {
            $locked = LeadImport::query()->whereKey($import->id)->lockForUpdate()->first();
            if ($locked === null || $locked->status !== LeadImport::STATUS_PREVIEWED) {
                throw ValidationException::withMessages(['import' => 'This import has already been started.']);
            }

            // Departments and relations a split import asked for are made now, not at preview
            if (! empty($locked->options['groups'])) {
                $this->createPlanTargets($locked);
            }

            $locked->update(['status' => $status, 'started_at' => now()]);
        });

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
            $plan     = $import->options['groups'] ?? null;
            $groups   = $plan === null ? [] : array_column($plan, null, 'key');
            $projects = $this->importProjects($import, $plan);

            $table   = $this->readImport($import);
            $context = $this->context($import, $table['headers']);
            $skipDuplicates = ($import->options['duplicates'] ?? 'log') === 'skip';
            $pipelines = [];   // project id => where a row counts as a duplicate

            $counts      = ['created_count' => 0, 'duplicate_count' => 0, 'skipped_count' => 0, 'failed_count' => 0];
            $groupCounts = [];
            $errorRows   = [];
            $processed   = 0;

            foreach ($table['rows'] as $line => $cells) {
                $row   = $this->evaluate($cells, $context);
                $group = $plan === null ? null : ($groups[$this->groupKey($row['department'])] ?? null);

                if ($plan !== null && ($group === null || $group['skip'])) {
                    $outcome = ['skipped'];
                } else {
                    $project = $projects[$plan === null ? $import->project_id : $group['project_id']];
                    $pipelines[$project->id] ??= $plan === null ? $this->intake->duplicatePipelines($project) : [$project->id];
                    if ($group !== null && $row['division'] !== '') {
                        $row['data']['division_id'] = $this->planDivision($group, $row['division'])['division_id'] ?? null;
                    }
                    $outcome = $this->importRow($import, $project, $row, $skipDuplicates, $pipelines[$project->id], direct: $plan !== null);
                }

                $counts[$outcome[0] . '_count']++;
                if ($group !== null) {
                    $groupCounts[$group['key']][$outcome[0] . '_count'] = ($groupCounts[$group['key']][$outcome[0] . '_count'] ?? 0) + 1;
                }
                if ($outcome[0] === 'failed') {
                    $errorRows[] = ['row' => $line, 'cells' => $cells, 'reason' => $outcome[1]];
                }

                if (++$processed % 25 === 0) {
                    $import->update(['processed_rows' => $processed] + $counts);
                }
            }

            $options = $import->options;
            if ($plan !== null) {
                $options['groups'] = array_map(fn ($g) => array_merge($g, ['counts' => array_merge(
                    ['created_count' => 0, 'duplicate_count' => 0, 'skipped_count' => 0, 'failed_count' => 0],
                    $groupCounts[$g['key']] ?? [],
                )]), $plan);
            }

            $import->update([
                'status'         => LeadImport::STATUS_COMPLETED,
                'processed_rows' => $processed,
                'options'        => $options,
                'error_rows'     => $errorRows === [] ? null : ['headers' => $table['headers'], 'rows' => $errorRows],
                'finished_at'    => now(),
            ] + $counts);
        } catch (\Throwable $e) {
            $import->update(['status' => LeadImport::STATUS_FAILED, 'error' => mb_substr($e->getMessage(), 0, 500), 'finished_at' => now()]);
            report($e);
        }
    }

    /** @return array<int, Project> the relations the import's rows go into, by id */
    private function importProjects(LeadImport $import, ?array $plan): array
    {
        $ids = $plan === null
            ? [$import->project_id]
            : array_column(array_filter($plan, fn ($g) => ! $g['skip']), 'project_id');
        $ids = array_values(array_unique(array_map('intval', array_filter($ids))));

        $projects = Project::query()->where('business_id', $import->business_id)->whereIn('id', $ids)->get()->keyBy('id')->all();
        if ($ids === [] || count($projects) !== count($ids)) {
            throw new \RuntimeException($plan === null ? 'The pipeline for this import no longer exists.' : 'A relation for this import no longer exists.');
        }

        return $projects;
    }

    /** @return array{0: string, 1?: string} created|duplicate|skipped|failed, and why it failed */
    private function importRow(LeadImport $import, Project $project, array $row, bool $skipDuplicates, array $pipelines, bool $direct = false): array
    {
        if ($row['errors'] !== []) {
            return ['failed', implode(' ', $row['errors'])];
        }

        $alreadyIn = DB::table('crm_lead_import_keys')
            ->where('business_id', $import->business_id)
            ->where('project_id', $project->id)
            ->where('row_key', $row['key'])
            ->exists();
        if ($alreadyIn) {
            return ['skipped'];
        }

        if ($skipDuplicates && $this->duplicates->matchingLeads($import->business_id, $row['data']['phone'], $row['data']['email'], null, $pipelines)->contains(fn (Lead $l) => $l->isOpen())) {
            return ['skipped'];
        }

        try {
            $result = $this->intake->capture($row['data'], $row['source'], $project, $import->user_id, $direct);
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
            'project_id'  => $project->id,
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
            // Department bundles of a split import, with what each did once run
            'groups'          => $import->options['groups'] ?? null,
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
            'split'        => ! empty($import->options['groups']),
        ];
    }

    /**
     * Turn the cells of a row into lead data and say what is wrong with it.
     *
     * @return array{data: array, source: string, department: string, errors: list<string>, warnings: list<string>, key: string, date: ?CarbonImmutable, normalized_phone: ?string}
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
            // The department and division only route a split import; otherwise they are worth keeping in the notes
            if ($target === self::TARGET_NOTE || (in_array($target, ['department', 'division'], true) && ! $context['split'])) {
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

        $date     = null;
        $dateText = $this->withMonth($values['date'] ?? null, $values['month'] ?? null);
        if ($dateText !== null) {
            $date = $this->parseDate($dateText, $context['day_first']);
            if ($date === null) {
                $warnings[] = "Date \"{$dateText}\" not recognised — today's date is used.";
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
            : 'row|' . implode('|', [$dateText ?? '', $data['name'] ?? '', $normalized ?? $phone ?? '', $email ?? '', $data['product_interest_note'] ?? '']);

        return [
            'data'             => $data,
            'source'           => $this->sourceFrom($values['source'] ?? null, $context['source']),
            'department'       => $this->cleanDepartment($values['department'] ?? ''),
            'division'         => $this->cleanDepartment($values['division'] ?? ''),
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

    /** @param list<string> $keys @return array<string, true> the keys already imported into the pipeline */
    private function importedKeys(int $businessId, int $projectId, array $keys): array
    {
        $found = [];
        foreach (array_chunk(array_values(array_unique($keys)), 500) as $chunk) {
            foreach (DB::table('crm_lead_import_keys')->where('business_id', $businessId)->where('project_id', $projectId)->whereIn('row_key', $chunk)->pluck('row_key') as $key) {
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

    /**
     * The date cell, or a full date when the file keeps the month in its own column: day "5"
     * and month "September" (or "9") → "2026-09-05", in the latest year that is not ahead of
     * today. Unrecognised parts come back joined, to show in the warning.
     */
    private function withMonth(?string $date, ?string $month): ?string
    {
        if ($month === null || ($date !== null && ! preg_match('/^\d{1,2}$/', $date))) {
            return $date;
        }

        $m = trim(mb_strtolower($month));
        $n = ctype_digit($m) ? (int) $m : 0;
        for ($i = 1; $n === 0 && $i <= 12 && strlen($m) >= 3; $i++) {
            if (str_starts_with(strtolower(date('F', mktime(0, 0, 0, $i, 1))), $m)) {
                $n = $i;
            }
        }

        $day  = (int) ($date ?? 1);
        $year = (int) now()->year;
        if ($n < 1 || $n > 12 || ! checkdate($n, $day, $year)) {
            return trim(($date ?? '') . ' ' . $month);
        }
        if (CarbonImmutable::create($year, $n, $day)->isFuture()) {
            $year--;
        }

        return checkdate($n, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $n, $day) : trim(($date ?? '') . ' ' . $month);
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
