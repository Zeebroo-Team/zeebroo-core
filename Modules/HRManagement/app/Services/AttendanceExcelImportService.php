<?php

declare(strict_types=1);

namespace Modules\HRManagement\Services;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\AttendanceRecord;
use Modules\HRManagement\Models\Employee;
use PhpOffice\PhpSpreadsheet\IOFactory;

final class AttendanceExcelImportService
{
    /**
     * @return array{
     *   imported: int,
     *   skipped: int,
     *   errors: array<int, string>
     * }
     */
    public function import(Business $business, UploadedFile $file, int $recordedByUserId): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (count($rows) < 2) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => [__('The Excel sheet is empty.')]];
        }

        /** @var array<string, string> $headerMap */
        $headerMap = $this->buildHeaderMap((array) array_shift($rows));
        $errors = [];
        $imported = 0;
        $skipped = 0;

        foreach ($rows as $rowNumber => $row) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            try {
                $employee = $this->resolveEmployee($business, $row, $headerMap);
                $workDate = $this->cell($row, $headerMap, ['work_date', 'date']);
                $status = strtolower(trim($this->cell($row, $headerMap, ['status'])));

                if (! in_array($status, AttendanceRecord::STATUSES, true)) {
                    throw new \RuntimeException('Invalid status value.');
                }

                $workedMinutesRaw = trim($this->cell($row, $headerMap, ['worked_minutes', 'minutes']));
                $workedMinutes = $workedMinutesRaw === '' ? null : (int) $workedMinutesRaw;
                if ($workedMinutes !== null && ($workedMinutes < 0 || $workedMinutes > 1440)) {
                    throw new \RuntimeException('Worked minutes must be between 0 and 1440.');
                }

                $notes = trim($this->cell($row, $headerMap, ['notes', 'note']));
                $checkInRaw = trim($this->cell($row, $headerMap, ['check_in_at', 'check_in']));
                $checkOutRaw = trim($this->cell($row, $headerMap, ['check_out_at', 'check_out']));

                AttendanceRecord::query()->updateOrCreate(
                    [
                        'business_id' => $business->id,
                        'employee_id' => $employee->id,
                        'work_date' => Carbon::parse($workDate)->toDateString(),
                    ],
                    [
                        'status' => $status,
                        'worked_minutes' => $workedMinutes,
                        'check_in_at' => $checkInRaw !== '' ? Carbon::parse($checkInRaw) : null,
                        'check_out_at' => $checkOutRaw !== '' ? Carbon::parse($checkOutRaw) : null,
                        'notes' => $notes !== '' ? $notes : null,
                        'source' => 'excel_import',
                        'recorded_by_user_id' => $recordedByUserId,
                    ]
                );

                $imported++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = __('Row :row skipped: :message', [
                    'row' => (int) $rowNumber,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $headerRow
     * @return array<string, string>
     */
    private function buildHeaderMap(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $column => $label) {
            $key = strtolower(trim((string) $label));
            if ($key !== '') {
                $map[$key] = (string) $column;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $headerMap
     */
    private function resolveEmployee(Business $business, array $row, array $headerMap): Employee
    {
        $employeeCode = trim($this->cell($row, $headerMap, ['employee_code', 'employee_id']));
        $employeePk = trim($this->cell($row, $headerMap, ['employee_pk', 'employee_db_id']));

        $query = Employee::query()->where('business_id', $business->id);
        if ($employeeCode !== '') {
            $match = (clone $query)->where('employee_id', $employeeCode)->first();
            if ($match) {
                return $match;
            }
        }

        if ($employeePk !== '' && is_numeric($employeePk)) {
            $match = (clone $query)->whereKey((int) $employeePk)->first();
            if ($match) {
                return $match;
            }
        }

        throw new \RuntimeException('Employee not found. Use employee_code or employee_pk.');
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $headerMap
     * @param  array<int, string>  $keys
     */
    private function cell(array $row, array $headerMap, array $keys): string
    {
        foreach ($keys as $key) {
            $column = $headerMap[$key] ?? null;
            if ($column === null) {
                continue;
            }

            return trim((string) ($row[$column] ?? ''));
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
