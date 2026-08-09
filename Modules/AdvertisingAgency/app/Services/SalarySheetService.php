<?php

namespace Modules\AdvertisingAgency\Services;

use Illuminate\Support\Facades\DB;
use Modules\AdvertisingAgency\Models\SalarySheet;
use Modules\AdvertisingAgency\Models\SalarySheetAllowance;
use Modules\AdvertisingAgency\Models\SalarySheetAttendance;
use Modules\AdvertisingAgency\Models\SalarySheetPositionRule;
use Modules\AdvertisingAgency\Models\SalarySheetRow;
use Modules\Business\Models\Business;

class SalarySheetService
{
    // ── List ─────────────────────────────────────────────────────────────────

    public function list(Business $business, ?string $q = null): \Illuminate\Support\Collection
    {
        return SalarySheet::query()
            ->where('business_id', $business->id)
            ->when($q, fn ($qry) => $qry->where(function ($sub) use ($q) {
                $sub->where('sheet_ref', 'like', "%{$q}%")
                    ->orWhere('location', 'like', "%{$q}%");
            }))
            ->withCount('rows')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($s) => [
                'id'         => $s->id,
                'sheet_ref'  => $s->sheet_ref,
                'location'   => $s->location,
                'job_id'     => $s->job_id,
                'date_from'  => $s->date_from->format('Y-m-d'),
                'date_to'    => $s->date_to->format('Y-m-d'),
                'status'     => $s->status,
                'rows_count' => $s->rows_count,
            ]);
    }

    // ── Create ───────────────────────────────────────────────────────────────

    public function create(Business $business, array $data): SalarySheet
    {
        $ref = $this->generateRef($business);
        // Auto-generate a title from location + date range
        $location = $data['location'] ?? null;
        $title    = trim(($location ?: 'Salary Sheet') . ' — ' . $data['date_from'] . ' to ' . $data['date_to']);

        return SalarySheet::create([
            'business_id' => $business->id,
            'job_id'      => $data['job_id']   ?? null,
            'sheet_ref'   => $ref,
            'title'       => $title,
            'location'    => $location,
            'date_from'   => $data['date_from'],
            'date_to'     => $data['date_to'],
            'status'      => 'draft',
            'notes'       => $data['notes']    ?? null,
        ]);
    }

    // ── Preview next ref (for the create modal) ───────────────────────────────

    public function previewNextRef(Business $business): string
    {
        return $this->generateRef($business);
    }

    // ── Show ─────────────────────────────────────────────────────────────────

    public function show(Business $business, int $id): array
    {
        $sheet      = SalarySheet::where('business_id', $business->id)->findOrFail($id);
        $rows       = SalarySheetRow::where('sheet_id', $sheet->id)
            ->with('attendances')
            ->orderBy('sort_order')
            ->get();
        $allowances     = SalarySheetAllowance::where('sheet_id', $sheet->id)
            ->orderBy('sort_order')
            ->get();
        $positionRules  = SalarySheetPositionRule::where('sheet_id', $sheet->id)
            ->orderBy('sort_order')
            ->get();

        return [
            'id'                      => $sheet->id,
            'sheet_ref'               => $sheet->sheet_ref,
            'title'                   => $sheet->title,
            'location'                => $sheet->location,
            'job_id'                  => $sheet->job_id,
            'date_from'               => $sheet->date_from->format('Y-m-d'),
            'date_to'                 => $sheet->date_to->format('Y-m-d'),
            'status'                  => $sheet->status,
            'notes'                   => $sheet->notes,
            'default_coordinator_fee' => $sheet->default_coordinator_fee ?? 0,
            'allowances' => $allowances->map(fn ($a) => [
                'id'             => $a->id,
                'allowance_type' => $a->allowance_type,
                'amount'         => $a->amount,
                'description'    => $a->description,
            ])->toArray(),
            'position_rules' => $positionRules->map(fn ($p) => [
                'id'                  => $p->id,
                'position_name'       => $p->position_name,
                'daily_rate'          => $p->daily_rate,
                'transport_allowance' => $p->transport_allowance,
            ])->toArray(),
            'rows'      => $rows->map(fn ($r) => [
                'id'                       => $r->id,
                'item_number'              => $r->item_number,
                'location'                 => $r->location,
                'position'                 => $r->position,
                'promoter_id'              => $r->promoter_id,
                'promoter_name'            => $r->promoter_name,
                'bank_name'                => $r->bank_name,
                'bank_branch'              => $r->bank_branch,
                'bank_account'             => $r->bank_account,
                'daily_rate'               => $r->daily_rate,
                'transport_allowance'      => $r->transport_allowance,
                'expenses'                 => $r->expenses,
                'hold_amount'              => $r->hold_amount,
                'coordinator_id'           => $r->coordinator_id,
                'coordinator_name'         => $r->coordinator_name,
                'coordination_fee'         => $r->coordination_fee,
                'coordinator_bank_details' => $r->coordinator_bank_details,
                'attendances'              => $r->attendances->map(fn ($a) => [
                    'attendance_date'   => $a->attendance_date->format('Y-m-d'),
                    'attendance_status' => $a->attendance_status,
                ])->toArray(),
            ])->toArray(),
        ];
    }

    // ── Update header ─────────────────────────────────────────────────────────

    public function update(SalarySheet $sheet, array $data): SalarySheet
    {
        $location = array_key_exists('location', $data) ? $data['location'] : $sheet->location;

        // Auto-regenerate title when location or dates change via setup modal
        $dateFrom = $data['date_from'] ?? $sheet->date_from;
        $dateTo   = $data['date_to']   ?? $sheet->date_to;
        $newTitle = trim(($location ?: 'Salary Sheet') . ' — ' . (is_string($dateFrom) ? $dateFrom : $dateFrom->format('Y-m-d')) . ' to ' . (is_string($dateTo) ? $dateTo : $dateTo->format('Y-m-d')));

        $sheet->update([
            'job_id'                  => array_key_exists('job_id', $data) ? $data['job_id'] : $sheet->job_id,
            'location'                => $location,
            'title'                   => $newTitle,
            'date_from'               => $dateFrom,
            'date_to'                 => $dateTo,
            'status'                  => $data['status']                    ?? $sheet->status,
            'notes'                   => array_key_exists('notes', $data) ? $data['notes'] : $sheet->notes,
            'default_coordinator_fee' => array_key_exists('default_coordinator_fee', $data)
                                            ? $data['default_coordinator_fee']
                                            : $sheet->default_coordinator_fee,
        ]);

        // Upsert allowances if provided
        if (array_key_exists('allowances', $data)) {
            $submitted    = $data['allowances'] ?? [];
            $submittedIds = array_values(array_filter(array_column($submitted, 'id')));

            // Remove allowances not in the new payload
            SalarySheetAllowance::where('sheet_id', $sheet->id)
                ->when(!empty($submittedIds), fn ($q) => $q->whereNotIn('id', $submittedIds))
                ->delete();

            foreach ($submitted as $sortOrder => $item) {
                $existing = $item['id']
                    ? SalarySheetAllowance::find($item['id'])
                    : null;

                $row = $existing ?? new SalarySheetAllowance();
                $row->fill([
                    'sheet_id'       => $sheet->id,
                    'allowance_type' => $item['allowance_type'] ?? '',
                    'amount'         => $item['amount']         ?? 0,
                    'description'    => $item['description']    ?? null,
                    'sort_order'     => $sortOrder,
                ]);
                $row->save();
            }
        }

        // Upsert position rules if provided
        if (array_key_exists('position_rules', $data)) {
            $submitted    = $data['position_rules'] ?? [];
            $submittedIds = array_values(array_filter(array_column($submitted, 'id')));

            SalarySheetPositionRule::where('sheet_id', $sheet->id)
                ->when(!empty($submittedIds), fn ($q) => $q->whereNotIn('id', $submittedIds))
                ->delete();

            foreach ($submitted as $sortOrder => $item) {
                $existing = $item['id']
                    ? SalarySheetPositionRule::find($item['id'])
                    : null;

                $row = $existing ?? new SalarySheetPositionRule();
                $row->fill([
                    'sheet_id'            => $sheet->id,
                    'position_name'       => $item['position_name']       ?? '',
                    'daily_rate'          => $item['daily_rate']          ?? 0,
                    'transport_allowance' => $item['transport_allowance'] ?? 0,
                    'sort_order'          => $sortOrder,
                ]);
                $row->save();
            }
        }

        return $sheet->fresh();
    }

    // ── Save rows (full upsert) ───────────────────────────────────────────────

    public function saveRows(SalarySheet $sheet, array $rows): void
    {
        DB::transaction(function () use ($sheet, $rows) {
            $submittedIds = array_values(array_filter(array_column($rows, 'id')));

            // Remove rows no longer in the payload
            SalarySheetRow::where('sheet_id', $sheet->id)
                ->when(!empty($submittedIds), fn ($q) => $q->whereNotIn('id', $submittedIds))
                ->delete();

            foreach ($rows as $sortOrder => $rowData) {
                $rowId    = $rowData['id'] ?? null;
                $rowModel = $rowId
                    ? SalarySheetRow::find($rowId) ?? new SalarySheetRow()
                    : new SalarySheetRow();

                $rowModel->fill([
                    'sheet_id'                 => $sheet->id,
                    'item_number'              => $rowData['item_number']              ?? null,
                    'location'                 => $rowData['location']                 ?? 'N/A',
                    'position'                 => $rowData['position']                 ?? null,
                    'promoter_id'              => $rowData['promoter_id']              ?: null,
                    'promoter_name'            => $rowData['promoter_name']            ?? null,
                    'bank_name'                => $rowData['bank_name']                ?? null,
                    'bank_branch'              => $rowData['bank_branch']              ?? null,
                    'bank_account'             => $rowData['bank_account']             ?? null,
                    'daily_rate'               => $rowData['daily_rate']               ?? 0,
                    'transport_allowance'      => $rowData['transport_allowance']      ?? 0,
                    'expenses'                 => $rowData['expenses']                 ?? 0,
                    'hold_amount'              => $rowData['hold_amount']              ?? 0,
                    'coordinator_id'           => $rowData['coordinator_id']           ?: null,
                    'coordinator_name'         => $rowData['coordinator_name']         ?? 'N/A',
                    'coordination_fee'         => $rowData['coordination_fee']         ?? 0,
                    'coordinator_bank_details' => $rowData['coordinator_bank_details'] ?? 'N/A',
                    'sort_order'               => $sortOrder,
                ]);
                $rowModel->save();

                // Replace attendances
                SalarySheetAttendance::where('row_id', $rowModel->id)->delete();
                foreach ($rowData['attendances'] ?? [] as $att) {
                    SalarySheetAttendance::create([
                        'row_id'            => $rowModel->id,
                        'attendance_date'   => $att['date'],
                        'attendance_status' => $att['status'],
                    ]);
                }
            }
        });
    }

    // ── Delete ───────────────────────────────────────────────────────────────

    public function delete(SalarySheet $sheet): void
    {
        $sheet->delete(); // cascades to rows and attendances
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function generateRef(Business $business): string
    {
        $year  = date('Y');
        $month = date('m');
        // Sequential counter resets each calendar month
        $count = SalarySheet::where('business_id', $business->id)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count() + 1;
        return 'SAL/' . $year . '/' . $month . '/' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }
}
