<?php

namespace Modules\CRM\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Business\Models\Business;
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
