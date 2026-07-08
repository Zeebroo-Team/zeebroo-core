<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\HRManagement\Models\PayrollCycle;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosPayrollOverviewApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function show(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $bid      = $business->id;

        // ── Employees ─────────────────────────────────────────────────────────
        $empTotal     = 0;
        $empFullTime  = 0;
        $empPartTime  = 0;
        $empContract  = 0;
        $deptCount    = 0;
        $deptBreakdown = [];

        if (Schema::hasTable('hr_employees')) {
            $employees = DB::table('hr_employees')
                ->where('business_id', $bid)
                ->select('employment_type')
                ->get();

            $empTotal    = $employees->count();
            $empFullTime = $employees->where('employment_type', 'full_time')->count();
            $empPartTime = $employees->where('employment_type', 'part_time')->count();
            $empContract = $employees->where('employment_type', 'contract')->count();
        }

        if (Schema::hasTable('hr_departments') && Schema::hasTable('hr_employees')) {
            $deptCount = DB::table('hr_departments')
                ->where('business_id', $bid)
                ->count();

            $deptRows = DB::table('hr_departments as d')
                ->leftJoin('hr_employees as e', function ($j) use ($bid) {
                    $j->on('e.department_id', '=', 'd.id')
                      ->where('e.business_id', '=', $bid);
                })
                ->where('d.business_id', $bid)
                ->selectRaw('d.name, COUNT(e.id) as emp_count, SUM(COALESCE(e.salary, e.basic_salary, 0)) as total_salary')
                ->groupBy('d.id', 'd.name')
                ->orderByDesc('total_salary')
                ->get();

            foreach ($deptRows as $r) {
                $deptBreakdown[] = [
                    'name'         => $r->name,
                    'emp_count'    => (int) $r->emp_count,
                    'total_salary' => round((float) $r->total_salary, 2),
                ];
            }
        }

        // ── Payroll cycles ────────────────────────────────────────────────────
        $cycleStatusCounts  = ['draft' => 0, 'computed' => 0, 'finalized' => 0];
        $lastCycleLabel     = null;
        $lastCycleGross     = 0.0;
        $lastCycleNet       = 0.0;
        $lastCycleDeductions= 0.0;
        $lastCycleEmpCount  = 0;
        $topEarners         = [];
        $recentCycles       = [];
        $trend              = ['labels' => [], 'gross' => [], 'net' => []];

        if (Schema::hasTable('hr_payroll_cycles')) {
            // Status counts
            $allCycles = DB::table('hr_payroll_cycles')
                ->where('business_id', $bid)
                ->select('id', 'name', 'year', 'month', 'status', 'period_start', 'period_end', 'finalized_at')
                ->orderByDesc('year')->orderByDesc('month')
                ->get();

            foreach ($allCycles as $c) {
                if (isset($cycleStatusCounts[$c->status])) {
                    $cycleStatusCounts[$c->status]++;
                }
            }

            // ── Cycle item sums ──────────────────────────────────────────────
            $allCycleIds = $allCycles->pluck('id');
            $itemSumsByCycle = collect();
            if ($allCycleIds->isNotEmpty() && Schema::hasTable('hr_payroll_items')) {
                $itemSumsByCycle = DB::table('hr_payroll_items')
                    ->whereIn('payroll_cycle_id', $allCycleIds)
                    ->selectRaw('payroll_cycle_id, COUNT(*) as emp_count, SUM(gross_earnings) as gross, SUM(total_deductions) as deductions, SUM(net_pay) as net')
                    ->groupBy('payroll_cycle_id')
                    ->get()
                    ->keyBy('payroll_cycle_id');
            }

            // ── Recent cycles list (latest 8) ────────────────────────────────
            foreach ($allCycles->take(8) as $c) {
                $sums = $itemSumsByCycle[$c->id] ?? null;
                $recentCycles[] = [
                    'id'         => $c->id,
                    'name'       => $c->name ?? (date('F Y', mktime(0, 0, 0, (int)$c->month, 1, (int)$c->year))),
                    'status'     => $c->status,
                    'gross'      => round((float)($sums?->gross ?? 0), 2),
                    'net'        => round((float)($sums?->net ?? 0), 2),
                    'emp_count'  => (int)($sums?->emp_count ?? 0),
                    'period_start' => $c->period_start,
                    'period_end'   => $c->period_end,
                    'finalized_at' => $c->finalized_at,
                ];
            }

            // ── Last finalized cycle KPIs ────────────────────────────────────
            $lastFinalized = $allCycles->where('status', PayrollCycle::STATUS_FINALIZED)->first();
            if ($lastFinalized) {
                $sums = $itemSumsByCycle[$lastFinalized->id] ?? null;
                $lastCycleLabel      = $lastFinalized->name ?? date('F Y', mktime(0, 0, 0, (int)$lastFinalized->month, 1, (int)$lastFinalized->year));
                $lastCycleGross      = round((float)($sums?->gross ?? 0), 2);
                $lastCycleNet        = round((float)($sums?->net ?? 0), 2);
                $lastCycleDeductions = round((float)($sums?->deductions ?? 0), 2);
                $lastCycleEmpCount   = (int)($sums?->emp_count ?? 0);

                // Top earners from last finalized cycle
                if (Schema::hasTable('hr_payroll_items') && Schema::hasTable('hr_employees')) {
                    $earnerRows = DB::table('hr_payroll_items as pi')
                        ->join('hr_employees as e', 'e.id', '=', 'pi.employee_id')
                        ->where('pi.payroll_cycle_id', $lastFinalized->id)
                        ->selectRaw('e.full_name, pi.gross_earnings, pi.total_deductions, pi.net_pay')
                        ->orderByDesc('pi.net_pay')
                        ->limit(8)
                        ->get();

                    foreach ($earnerRows as $r) {
                        $topEarners[] = [
                            'name'       => $r->full_name,
                            'gross'      => round((float)$r->gross_earnings, 2),
                            'deductions' => round((float)$r->total_deductions, 2),
                            'net'        => round((float)$r->net_pay, 2),
                        ];
                    }
                }
            }

            // ── 12-month trend ───────────────────────────────────────────────
            $slotKeys   = [];
            $slotLabels = [];
            for ($i = 11; $i >= 0; $i--) {
                $m = now()->subMonths($i);
                $key         = (int)$m->year . '-' . (int)$m->month;
                $slotKeys[]  = $key;
                $slotLabels[] = $m->format("M 'y");
            }
            $trendBuckets = array_fill_keys($slotKeys, ['gross' => 0.0, 'net' => 0.0]);

            foreach ($allCycles->where('status', PayrollCycle::STATUS_FINALIZED) as $c) {
                $key = (int)$c->year . '-' . (int)$c->month;
                if (isset($trendBuckets[$key])) {
                    $sums = $itemSumsByCycle[$c->id] ?? null;
                    $trendBuckets[$key]['gross'] += (float)($sums?->gross ?? 0);
                    $trendBuckets[$key]['net']   += (float)($sums?->net ?? 0);
                }
            }

            $bv = array_values($trendBuckets);
            $trend = [
                'labels' => $slotLabels,
                'gross'  => array_map(fn ($b) => round($b['gross'], 2), $bv),
                'net'    => array_map(fn ($b) => round($b['net'], 2),   $bv),
            ];
        }

        return response()->json([
            'data' => [
                'summary' => [
                    'emp_total'          => $empTotal,
                    'emp_full_time'      => $empFullTime,
                    'emp_part_time'      => $empPartTime,
                    'emp_contract'       => $empContract,
                    'dept_count'         => $deptCount,
                    'pending_cycles'     => $cycleStatusCounts['draft'] + $cycleStatusCounts['computed'],
                    'last_cycle_label'   => $lastCycleLabel,
                    'last_cycle_gross'   => $lastCycleGross,
                    'last_cycle_net'     => $lastCycleNet,
                    'last_cycle_deductions' => $lastCycleDeductions,
                    'last_cycle_emp_count'  => $lastCycleEmpCount,
                ],
                'trend'          => $trend,
                'recent_cycles'  => $recentCycles,
                'top_earners'    => $topEarners,
                'dept_breakdown' => $deptBreakdown,
            ],
        ]);
    }
}
