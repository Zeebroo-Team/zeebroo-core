<?php

declare(strict_types=1);

namespace Modules\HRManagement\Services;

use Illuminate\Support\Str;
use Modules\HRManagement\Models\PayrollCycle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PayrollSalarySheetExcelExportService
{
    private const MONEY_FORMAT = '#,##0.00';

    /**
     * @param  list<array<string, mixed>>  $sheetColumns
     * @param  list<array<string, mixed>>  $rows
     */
    public function streamResponse(
        PayrollCycle $cycle,
        array $sheetColumns,
        array $rows,
        string $currency,
    ): StreamedResponse {
        $spreadsheet = $this->buildSpreadsheet($cycle, $sheetColumns, $rows, $currency);
        $filename = $this->safeFilename($cycle);

        return response()->streamDownload(function () use ($spreadsheet): void {
            try {
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $sheetColumns
     * @param  list<array<string, mixed>>  $rows
     */
    public function buildSpreadsheet(
        PayrollCycle $cycle,
        array $sheetColumns,
        array $rows,
        string $currency,
    ): Spreadsheet {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $tabTitle = Str::slug($cycle->name ?: 'salary-sheet', '_');
        if ($tabTitle === '') {
            $tabTitle = 'salary_sheet';
        }
        $sheet->setTitle(Str::substr($tabTitle, 0, 31));

        $colCount = count($sheetColumns);
        if ($colCount === 0) {
            $sheet->setCellValue('A1', __('Salary sheet'));

            return $spreadsheet;
        }

        $lastLetter = Coordinate::stringFromColumnIndex($colCount);

        $metaR = 1;
        $sheet->setCellValue('A'.$metaR, __('Salary sheet').' — '.$cycle->name);
        $sheet->mergeCells('A'.$metaR.':'.$lastLetter.$metaR);
        $sheet->getStyle('A'.$metaR)->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A'.$metaR)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $metaR++;

        $period = '';
        if ($cycle->period_start !== null && $cycle->period_end !== null) {
            $period = $cycle->period_start->format('M j, Y').' — '.$cycle->period_end->format('M j, Y');
        }
        $sheet->setCellValue('A'.$metaR, __('Period').': '.$period.'  |  '.__('Currency').': '.$currency.'  |  '.__('Rule set').': '.($cycle->ruleSet?->name ?? '—'));
        $sheet->mergeCells('A'.$metaR.':'.$lastLetter.$metaR);
        $sheet->getStyle('A'.$metaR)->getFont()->setSize(10);
        $metaR++;

        $sheet->setCellValue(
            'A'.$metaR,
            __('Totals row uses Excel SUM() formulas. Net pay uses Gross − Deductions when those columns exist, so edits recalculate.'),
        );
        $sheet->mergeCells('A'.$metaR.':'.$lastLetter.$metaR);
        $sheet->getStyle('A'.$metaR)->getFont()->setItalic(true)->setSize(9);
        $sheet->getStyle('A'.$metaR)->getFont()->getColor()->setRGB('666666');

        $metaR++;

        $headerRow = $metaR;

        /** @var list<array{key: string, kind: string, letter: string}> $columnMeta */
        $columnMeta = [];
        $moneyLetters = [];

        foreach ($sheetColumns as $idx => $colDef) {
            $colIdx = $idx + 1;
            $letter = Coordinate::stringFromColumnIndex($colIdx);
            $kind = (string) ($colDef['kind'] ?? '');
            $key = (string) ($colDef['key'] ?? '');
            $label = strip_tags(html_entity_decode((string) ($colDef['label'] ?? $key)));

            $cell = $letter.$headerRow;
            $sheet->setCellValue($cell, $label);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E8EEFF');
            if ($kind === 'money') {
                $moneyLetters[$key] = $letter;
                $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            } elseif ($kind === 'employee') {
                $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            } elseif ($kind === 'status') {
                $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            $columnMeta[] = ['key' => $key, 'kind' => $kind, 'letter' => $letter];
        }

        $dataStartRow = $headerRow + 1;
        $currentRow = $dataStartRow;

        foreach ($rows as $row) {
            $values = is_array($row['values'] ?? null) ? $row['values'] : [];
            foreach ($columnMeta as $meta) {
                $letter = $meta['letter'];
                $addr = $letter.$currentRow;

                if ($meta['kind'] === 'employee') {
                    $name = (string) ($row['employee_name'] ?? '');
                    $eid = (string) ($row['employee_id'] ?? '');
                    $sheet->setCellValue($addr, $eid !== '' ? $name.' ('.$eid.')' : $name);
                    $sheet->getStyle($addr)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                } elseif ($meta['kind'] === 'status') {
                    $sheet->setCellValue($addr, (string) ($row['status'] ?? ''));
                    $sheet->getStyle($addr)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                } else {
                    $k = $meta['key'];
                    $num = round((float) ($values[$k] ?? 0), 2);
                    $sheet->setCellValue($addr, $num);
                    $sheet->getStyle($addr)->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
                    $sheet->getStyle($addr)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
            }

            $grossL = $moneyLetters['item_gross_earnings'] ?? null;
            $dedL = $moneyLetters['item_total_deductions'] ?? null;
            $netL = $moneyLetters['item_net_pay'] ?? null;
            if ($grossL !== null && $dedL !== null && $netL !== null) {
                $sheet->setCellValue($netL.$currentRow, '='.$grossL.$currentRow.'-'.$dedL.$currentRow);
                $sheet->getStyle($netL.$currentRow)->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
            }

            $currentRow++;
        }

        $dataEndRow = $currentRow - 1;
        $totalRow = $dataEndRow + 1;

        if ($dataEndRow >= $dataStartRow) {
            $firstSummary = true;
            foreach ($columnMeta as $meta) {
                $letter = $meta['letter'];
                $addr = $letter.$totalRow;
                if ($meta['kind'] === 'employee') {
                    $sheet->setCellValue($addr, $firstSummary ? __('Column totals') : '');
                    $sheet->getStyle($addr)->getFont()->setBold(true);
                    $firstSummary = false;
                } elseif ($meta['kind'] === 'money') {
                    $formula = '=SUM('.$letter.$dataStartRow.':'.$letter.$dataEndRow.')';
                    $sheet->setCellValue($addr, $formula);
                    $sheet->getStyle($addr)->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
                    $sheet->getStyle($addr)->getFont()->setBold(true);
                    $sheet->getStyle($addr)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                } elseif ($meta['kind'] === 'status') {
                    $sheet->setCellValue($addr, '');
                }
            }
            $freezeRow = $dataStartRow;
            $sheet->freezePane(Coordinate::stringFromColumnIndex(2).$freezeRow);
        } else {
            $sheet->setCellValue('A'.$dataStartRow, __('No payroll lines for this cycle.'));
            $sheet->mergeCells('A'.$dataStartRow.':'.$lastLetter.$dataStartRow);
        }

        foreach (range(1, $colCount) as $c) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    private function safeFilename(PayrollCycle $cycle): string
    {
        $slug = Str::slug($cycle->name ?? 'salary-sheet', '_');
        if ($slug === '') {
            $slug = 'salary_sheet';
        }

        return $slug.'_'.$cycle->year.'_'.str_pad((string) $cycle->month, 2, '0', STR_PAD_LEFT).'.xlsx';
    }
}
