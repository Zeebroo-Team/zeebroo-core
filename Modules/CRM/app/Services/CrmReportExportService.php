<?php

namespace Modules\CRM\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Business\Models\Business;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The dashboard as it is filtered on screen (CrmReportService::dashboard), as a PDF or an Excel workbook. */
class CrmReportExportService
{
    public function pdf(Business $business, array $report): Response
    {
        $binary = Pdf::loadView('crm::reports.dashboard-pdf', [
            'business' => $business,
            'report'   => $report,
            'kpis'     => $this->kpiRows($report),
            'filters'  => $this->filterLine($report),
            'attention'=> $this->attentionRows($report),
        ])->setPaper('a4')->output();

        return response($binary, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $this->fileName($report, 'pdf') . '"',
        ]);
    }

    public function excel(array $report): StreamedResponse
    {
        $book = new Spreadsheet;
        $book->getProperties()->setTitle('CRM dashboard');

        $this->sheet($book->getActiveSheet(), 'Summary', ['Measure', 'Value'], [
            ['Filters', $this->filterLine($report)],
            ...array_map(fn ($r) => [$r[0], $r[1]], $this->kpiRows($report)),
        ]);
        $this->sheet($book->createSheet(), 'By source', ['Source', 'Leads', 'Won'],
            array_map(fn ($r) => [$r['label'], $r['leads'], $r['won']], $report['by_source']));
        $this->sheet($book->createSheet(), 'By department', ['Department', 'Leads', 'Won'],
            array_map(fn ($r) => [$r['label'], $r['leads'], $r['won']], $report['by_department']));
        $this->sheet($book->createSheet(), 'By division', ['Division', 'Leads', 'Won'],
            array_map(fn ($r) => [$r['label'], $r['leads'], $r['won']], $report['by_division']));
        $this->sheet($book->createSheet(), 'Funnel', ['Status', 'Leads'],
            array_map(fn ($r) => [$r['label'], $r['leads']], $report['funnel']));
        $this->sheet($book->createSheet(), 'Open by status', ['Status', 'Open deals', 'Estimated value', 'Past due'],
            array_map(fn ($r) => [$r['label'], $r['leads'], $r['value'], $r['past_due']], $report['pipeline_stages']));
        $this->sheet($book->createSheet(), 'Expected to close', ['When', 'Open deals', 'Estimated value'],
            array_map(fn ($r) => [$r['label'], $r['leads'], $r['value']], $report['forecast']));
        $this->sheet($book->createSheet(), 'Needs attention', ['Issue', 'Customer', 'Owner', 'Phone', 'Days'], $this->attentionRows($report));
        $this->sheet($book->createSheet(), 'Staff ranking',
            ['#', 'Staff', 'New leads', 'Won', 'Won value', 'Conversion %', 'Open deals', 'Open value', 'Overdue follow-ups', 'First response (h)'],
            array_map(fn ($r, $i) => [$i + 1, $r['name'], $r['leads'], $r['won'], $r['won_value'], $r['conversion_rate'],
                $r['open_deals'], $r['open_value'], $r['overdue_follow_ups'], $r['avg_response_hours'] ?? '—'], $report['staff'], array_keys($report['staff'])));
        $this->sheet($book->createSheet(), 'Lost reasons', ['Reason', 'Leads'],
            array_map(fn ($r) => [$r['label'], $r['leads']], $report['lost_reasons']));
        $this->sheet($book->createSheet(), 'Trend', ['Period', 'Leads', 'Won'],
            array_map(fn ($r) => [$r['label'], $r['leads'], $r['won']], $report['trend']));
        $book->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $this->fileName($report, 'xlsx'), ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** The sales analysis report (CrmSalesAnalysisService::report) as a PDF table; landscape when it is wide. */
    public function salesAnalysisPdf(Business $business, array $report): Response
    {
        $binary = Pdf::loadView('crm::reports.sales-analysis-pdf', [
            'business' => $business,
            'report'   => $report,
            'title'    => $this->salesTitle($report),
            'filters'  => $this->salesFilterLine($report),
            'cell'     => fn (array $column, $value) => $this->formatCell($column['type'], $value),
        ])->setPaper('a4', count($report['columns']) > 7 ? 'landscape' : 'portrait')->output();

        return response($binary, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $this->salesFileName($report, 'pdf') . '"',
        ]);
    }

    /** The same table as CSV: raw numbers, so it opens straight into a spreadsheet for further analysis. */
    public function salesAnalysisCsv(array $report): StreamedResponse
    {
        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows non-ASCII names correctly
            fputcsv($out, [$this->salesTitle($report)]);
            fputcsv($out, [$this->salesFilterLine($report)]);
            fputcsv($out, []);
            fputcsv($out, array_column($report['columns'], 'label'));
            foreach ($report['rows'] as $row) {
                fputcsv($out, array_map(fn ($c) => $row[$c['key']] ?? '', $report['columns']));
            }
            fputcsv($out, array_map(fn ($c, $i) => $i === 0 ? 'Total' : ($report['totals'][$c['key']] ?? ''), $report['columns'], array_keys($report['columns'])));
            fclose($out);
        }, $this->salesFileName($report, 'csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The same table as an Excel workbook: raw numbers with number formats, a title, the
     * filters and a totals row.
     */
    public function salesAnalysisExcel(array $report): StreamedResponse
    {
        $book  = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $book->getProperties()->setTitle($this->salesTitle($report));
        $sheet->setTitle(mb_substr($report['group_label'], 0, 31));

        $columns = $report['columns'];
        $last    = Coordinate::stringFromColumnIndex(count($columns));
        $sheet->setCellValue('A1', $this->salesTitle($report));
        $sheet->setCellValue('A2', $this->salesFilterLine($report));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setItalic(true);

        $head = 4;
        $sheet->fromArray(array_column($columns, 'label'), null, 'A' . $head, true);
        $sheet->getStyle("A{$head}:{$last}{$head}")->getFont()->setBold(true);
        $sheet->getStyle("A{$head}:{$last}{$head}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');

        $line = $head;
        foreach ($report['rows'] as $row) {
            $sheet->fromArray(array_map(fn ($c) => $this->excelValue($c['type'], $row[$c['key']] ?? null), $columns), null, 'A' . ++$line, true);
        }
        $sheet->fromArray(array_map(fn ($c, $i) => $i === 0 ? 'Total' : $this->excelValue($c['type'], $report['totals'][$c['key']] ?? null),
            $columns, array_keys($columns)), null, 'A' . ++$line, true);
        $sheet->getStyle("A{$line}:{$last}{$line}")->getFont()->setBold(true);

        foreach ($columns as $i => $c) {
            $col    = Coordinate::stringFromColumnIndex($i + 1);
            $format = match ($c['type']) {
                'money' => '#,##0.00',
                'pct'   => '0.0"%"',
                'hours' => '0.0" h"',
                'num'   => '0.0',
                'qty'   => '#,##0.###',
                'int'   => '#,##0',
                default => null,
            };
            if ($format !== null) {
                $sheet->getStyle("{$col}" . ($head + 1) . ":{$col}{$line}")->getNumberFormat()->setFormatCode($format);
            }
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('B' . ($head + 1));

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $this->salesFileName($report, 'xlsx'), ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** "CRM report — KPIs by staff", "CRM report — sales by month" … */
    public function salesTitle(array $report): string
    {
        $by = $report['group_by'] === 'period' && $report['period']
            ? strtolower(collect($report['options']['periods'])->firstWhere('key', $report['period'])['label'] ?? 'period') . ' (by period)'
            : 'by ' . strtolower($report['group_label']);

        return 'CRM report — ' . match ($report['view'] ?? null) {
            'kpi'   => 'KPIs ',
            'sales' => 'sales ',
            default => '',
        } . $by;
    }

    private function excelValue(string $type, $value)
    {
        if ($value === null || $value === '') {
            return $type === 'text' ? '' : null;
        }

        return $type === 'text' ? (string) $value : (float) $value;
    }

    public function salesFilterLine(array $report): string
    {
        $f = $report['filters'];

        return implode(' · ', array_filter([
            $this->filterLine($report),
            $f['status'] ? 'Status: ' . (collect($report['options']['statuses'])->firstWhere('key', $f['status'])['label'] ?? $f['status']) : null,
            $f['search'] ? 'Customer: "' . $f['search'] . '"' : null,
            ($f['industry'] ?? null) ? 'Industry: ' . $f['industry'] : null,
            ($f['product_id'] ?? null) ? 'Product: ' . (collect($report['options']['products'] ?? [])->firstWhere('id', $f['product_id'])['name'] ?? '#' . $f['product_id']) : null,
        ]));
    }

    public function formatCell(string $type, $value): string
    {
        if ($value === null || $value === '') {
            return $type === 'text' ? '' : '—';
        }

        return match ($type) {
            'money' => number_format((float) $value, 2),
            'qty'   => rtrim(rtrim(number_format((float) $value, 3), '0'), '.'),
            'pct'   => $value . '%',
            'num'   => number_format((float) $value, 1),
            'hours' => number_format((float) $value, 1) . ' h',
            'int'   => number_format((int) $value),
            default => (string) $value,
        };
    }

    private function salesFileName(array $report, string $ext): string
    {
        $group = $report['group_by'] === 'period' && ($report['period'] ?? null) ? $report['period'] : $report['group_by'];

        return 'crm-' . (($report['view'] ?? null) === 'kpi' ? 'kpis' : 'sales') . '-by-' . str_replace('_', '-', $group)
            . '-' . $report['filters']['from'] . '-to-' . $report['filters']['to'] . '.' . $ext;
    }

    /** @return list<array{0: string, 1: string|int|float}> label, value */
    public function kpiRows(array $report): array
    {
        $k = $report['kpis'];
        $money = fn ($v) => number_format((float) $v, 2);

        return [
            ['Leads created', $k['leads']],
            ['Won (of those)', $k['won']],
            ['Lost (of those)', $k['lost']],
            ['Still open', $k['open']],
            ['Conversion rate', $k['conversion_rate'] . '%'],
            ['Pipeline value (estimated)', $money($k['pipeline_value'])],
            ['Won in the period', $k['won_in_range']],
            ['Won value in the period', $money($k['won_value_in_range'])],
            ['Lost in the period', $k['lost_in_range']],
            ['Quotations sent', $k['quotations_sent'] . ' · ' . $money($k['quotation_value_sent'])],
            ['Quotations won', $k['quotations_won'] . ' · ' . $money($k['quotation_value_won'])],
            ['Quotations lost', $k['quotations_lost'] . ' · ' . $money($k['quotation_value_lost'])],
            ['Pending approvals', $k['pending_approvals'] . ' · ' . $money($k['pending_approval_value'])],
            ['Overdue follow-ups', $k['overdue_follow_ups']],
            ['Average response time', $k['avg_response_hours'] !== null ? $k['avg_response_hours'] . ' h' : '—'],
            ['Average close time', $k['avg_close_days'] !== null ? $k['avg_close_days'] . ' days' : '—'],
            ['Win rate', $k['win_rate'] !== null ? $k['win_rate'] . '%' : '—'],
            ['Open deals now', $k['open_pipeline_count'] . ' · ' . $money($k['open_pipeline_value'])],
            ['Past-due deals now', $k['past_due_deals'] . ' · ' . $money($k['past_due_deals_value'])],
            ['Not contacted yet (now)', $k['uncontacted_leads']],
            ['Unassigned leads (now)', $k['unassigned_leads']],
            ...$this->inquiryKpiRows($report['inquiry_kpis'] ?? null),
        ];
    }

    /** The inquiry-handling KPIs (CrmKpiService) as label / value rows. */
    private function inquiryKpiRows(?array $k): array
    {
        if ($k === null) {
            return [];
        }
        $hours = fn ($h) => $h !== null ? $h . ' h' : '—';

        return [
            ['Inquiries received', $k['inquiries'] . ' (' . $k['per_day'] . ' per day)'],
            ['Average response time', $hours($k['avg_response_hours'])],
            ['Average time to first contact', $hours($k['avg_contact_hours'])],
            ['Follow-ups done', $k['follow_ups_done']],
            ['Quotations made', $k['quotations'] . ' · ' . number_format((float) $k['quotation_value'], 2)],
            ['Quotations converted to sales', $k['converted'] . ' · ' . number_format((float) $k['converted_value'], 2)],
            ['Inquiry → quotation rate', $k['inquiry_quote_rate'] . '%'],
            ['Quotation → sale rate', $k['quote_sale_rate'] . '%'],
            ['Pending inquiries', $k['pending']],
            ['Overdue inquiries', $k['overdue']],
            ['Sales from these inquiries', $k['won'] . ' · ' . number_format((float) $k['won_value'], 2)],
        ];
    }

    /** The live to-do list: oldest overdue follow-ups, then assigned leads still waiting for first contact. */
    public function attentionRows(array $report): array
    {
        return [
            ...array_map(fn ($r) => ['Overdue follow-up', $r['name'], $r['owner'] ?? 'Unassigned', $r['phone'] ?? '', $this->days($r['days_overdue']) . ' late'], $report['attention']['follow_ups']),
            ...array_map(fn ($r) => ['Not contacted yet', $r['name'], $r['owner'] ?? 'Unassigned', $r['phone'] ?? '', $this->days($r['age_days']) . ' waiting'], $report['attention']['uncontacted']),
        ];
    }

    public function filterLine(array $report): string
    {
        $f = $report['filters'];
        $o = $report['options'];
        $name = fn (array $list, $id) => collect($list)->firstWhere('id', $id)['name'] ?? null;

        return implode(' · ', array_filter([
            $f['from'] . ' → ' . $f['to'],
            $f['department_id'] ? 'Department: ' . $name($o['departments'], $f['department_id']) : null,
            $f['division_id'] ? 'Division: ' . $name($o['divisions'], $f['division_id']) : null,
            $f['user_id'] ? 'Staff: ' . $name($o['staff'], $f['user_id']) : null,
            $f['source'] ? 'Source: ' . (collect($o['sources'])->firstWhere('key', $f['source'])['label'] ?? $f['source']) : null,
        ]));
    }

    private function sheet($sheet, string $title, array $head, array $rows): void
    {
        $sheet->setTitle($title);
        $sheet->fromArray([$head, ...$rows], null, 'A1', true); // strict: write 0 as 0, not a blank cell
        $last = chr(ord('A') + count($head) - 1);
        $sheet->getStyle("A1:{$last}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$last}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
        foreach (range('A', $last) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function days(int $n): string
    {
        return $n . ($n === 1 ? ' day' : ' days');
    }

    private function fileName(array $report, string $ext): string
    {
        return 'crm-dashboard-' . $report['filters']['from'] . '-to-' . $report['filters']['to'] . '.' . $ext;
    }
}
