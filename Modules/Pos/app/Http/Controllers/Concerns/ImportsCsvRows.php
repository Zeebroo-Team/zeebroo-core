<?php

namespace Modules\Pos\Http\Controllers\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

trait ImportsCsvRows
{
    /**
     * Parse an uploaded CSV into an array of associative rows keyed by the expected headers.
     * Returns [rows, errorMessage|null].
     */
    protected function parseCsv(UploadedFile $file, array $expectedHeaders): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if (! $handle) {
            return [[], 'Could not read the uploaded file.'];
        }

        $headerRow = fgetcsv($handle, 0, ',', '"', '');
        if ($headerRow === false) {
            fclose($handle);
            return [[], 'The CSV file appears to be empty.'];
        }

        $headers = array_map(fn ($h) => strtolower(trim(str_replace(' ', '_', (string) $h))), $headerRow);

        $rows = [];
        while (($line = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if (count(array_filter($line, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $line = array_slice(array_pad($line, count($headers), null), 0, count($headers));
            $combined = array_combine($headers, $line);

            $row = [];
            foreach ($expectedHeaders as $key) {
                $value = trim((string) ($combined[$key] ?? ''));
                $row[$key] = $value === '' ? null : $value;
            }
            $rows[] = $row;

            if (count($rows) > 500) {
                fclose($handle);
                return [[], 'CSV files are limited to 500 data rows.'];
            }
        }

        fclose($handle);

        return [$rows, null];
    }

    /**
     * Validate each row individually; rows failing validation are excluded and
     * recorded separately so the caller never sends malformed data to the service.
     * Returns [validRows, originalRowNumberForEachValidRow, invalidResults] — the
     * middle array lets callers map the service's 1-based positional row numbers
     * (which only see the filtered subset) back to the original CSV line numbers.
     */
    protected function splitValidRows(array $rows, array $rules): array
    {
        $valid = [];
        $rowNumbers = [];
        $invalid = [];

        foreach ($rows as $i => $row) {
            $validator = Validator::make($row, $rules);
            if ($validator->fails()) {
                $invalid[] = [
                    'row'    => $i + 1,
                    'name'   => $row['name'] ?? '',
                    'status' => 'invalid',
                    'reason' => implode(' ', $validator->errors()->all()),
                ];
                continue;
            }
            $valid[] = $row;
            $rowNumbers[] = $i + 1;
        }

        return [$valid, $rowNumbers, $invalid];
    }

    /**
     * Merge a service import() result with the pre-validation failures, remapping
     * the service's positional row numbers back to original CSV line numbers.
     */
    protected function mergeImportResults(array $serviceResult, array $rowNumbers, array $invalidResults): array
    {
        $serviceRows = array_map(function ($row) use ($rowNumbers) {
            $row['row'] = $rowNumbers[$row['row'] - 1] ?? $row['row'];
            return $row;
        }, $serviceResult['rows'] ?? []);

        $rows = array_merge($serviceRows, $invalidResults);
        usort($rows, fn ($a, $b) => $a['row'] <=> $b['row']);

        return [
            'created' => $serviceResult['created'] ?? 0,
            'skipped' => $serviceResult['skipped'] ?? 0,
            'invalid' => count($invalidResults),
            'total'   => ($serviceResult['total'] ?? 0) + count($invalidResults),
            'rows'    => $rows,
        ];
    }
}
