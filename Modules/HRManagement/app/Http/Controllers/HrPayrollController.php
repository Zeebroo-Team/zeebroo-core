<?php

namespace Modules\HRManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Account\Models\Account;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\PayrollCustomTemplate;
use Modules\HRManagement\Models\PayrollCycle;
use Modules\HRManagement\Models\PayrollItem;
use Modules\HRManagement\Models\PayrollRule;
use Modules\HRManagement\Models\PayrollRuleSet;
use Modules\HRManagement\Payroll\RegionalTemplates\PayrollRegionalTemplateRegistry;
use Modules\HRManagement\Payroll\RegionalTemplates\SriLankanEmployeeStandardPayrollTemplate;
use Modules\HRManagement\Services\HrPayslipLeaveService;
use Modules\HRManagement\Services\HrPayrollSettingsService;
use Modules\HRManagement\Services\PayrollComputationService;
use Modules\HRManagement\Services\PayrollCustomTemplateService;
use Modules\HRManagement\Services\PayrollCyclePaymentService;
use Modules\HRManagement\Services\PayrollSalarySheetExcelExportService;
use Modules\HRManagement\Services\PayrollSalarySheetPresentationService;
use Modules\DesignStudio\Models\Design;
use Modules\Settings\Services\SettingsService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrPayrollController extends Controller
{
    public function __construct(
        private readonly HrPayrollSettingsService $hrPayrollSettings,
        private readonly PayrollComputationService $payrollComputation,
        private readonly PayrollRegionalTemplateRegistry $payrollRegionalTemplates,
        private readonly PayrollCustomTemplateService $payrollCustomTemplates,
        private readonly PayrollSalarySheetPresentationService $salarySheetPresentation,
        private readonly PayrollSalarySheetExcelExportService $salarySheetExcelExport,
        private readonly PayrollCyclePaymentService $payrollCyclePayment,
        private readonly HrPayslipLeaveService $hrPayslipLeave,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request): RedirectResponse|View
    {
        $business = $this->resolveBusiness($request);
        $ruleSets = $this->loadRuleSets($business);

        $cycles = $business->payrollCycles()
            ->with(['ruleSet', 'finalizedBy'])
            ->withCount(['items', 'ledgerTransactions'])
            ->withSum('items', 'net_pay')
            ->get();

        return view('hrmanagement::payroll.index', [
            'business' => $business,
            'ruleSets' => $ruleSets,
            'cycles' => $cycles,
            'defaultRuleSetId' => optional($this->payrollComputation->resolveRuleSetForCycle($business))->id,
        ]);
    }

    public function regionalTemplate(Request $request): RedirectResponse|View
    {
        $business = $this->resolveBusiness($request);

        return view('hrmanagement::payroll.regional-template', [
            'business' => $business,
            ...$this->payrollTemplateViewData($business),
        ]);
    }

    public function ruleSets(Request $request): RedirectResponse|View
    {
        $business = $this->resolveBusiness($request);
        $ruleSets = $this->loadRuleSets($business);
        $ruleSets->load([
            'rules' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
        ]);

        $flowBills = $this->loadFlowBills($business);

        return view('hrmanagement::payroll.rule-sets', [
            'business'  => $business,
            'ruleSets'  => $ruleSets,
            'flowBills' => $flowBills,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function loadFlowBills(Business $business): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('bills')) {
            return [];
        }

        $today = \Illuminate\Support\Carbon::today();

        $bills = \Modules\Account\Models\Bill::query()
            ->where('business_id', $business->id)
            ->with(['employee:id,full_name', 'department:id,name', 'warehouse:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'bill_category', 'recurring_cost', 'payment_mode',
                   'due_date', 'first_installment_due_date', 'employee_id', 'department_id', 'branch_id']);

        return $bills->map(function ($b) use ($today) {
            $dueAt  = $b->due_date ?? $b->first_installment_due_date;
            $overdue = $dueAt instanceof \Illuminate\Support\Carbon && $dueAt->lt($today);

            $assign = $b->employee?->full_name
                ?? $b->department?->name
                ?? $b->warehouse?->name
                ?? null;

            return [
                'id'      => $b->id,
                'name'    => $b->name,
                'cat'     => $b->bill_category ?? 'other',
                'amount'  => round((float) $b->recurring_cost, 2),
                'mode'    => $b->payment_mode,
                'due'     => $dueAt?->toDateString(),
                'overdue' => $overdue,
                'assign'  => $assign,
            ];
        })->values()->all();
    }

    public function applyTemplate(Request $request): RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        $request->validate([
            'template' => ['required', 'string', Rule::in($this->allowedPayrollTemplateKeys($business))],
        ]);
        $templateKey = (string) $request->input('template');

        if (PayrollCustomTemplate::matchesKey($templateKey)) {
            $id = PayrollCustomTemplate::idFromKey($templateKey);
            $custom = PayrollCustomTemplate::query()
                ->where('business_id', $business->id)
                ->whereKey($id)
                ->first();
            if ($custom === null) {
                return back()->withErrors(['template' => __('Unknown custom payroll template.')]);
            }

            return redirect()
                ->route('hr.payroll.regional-template')
                ->with('status', $this->payrollCustomTemplates->apply($business, $custom));
        }

        $installer = $this->payrollRegionalTemplates->get($templateKey);
        if ($installer === null) {
            return back()->withErrors(['template' => __('Unsupported payroll template.')]);
        }

        return redirect()
            ->route('hr.payroll.regional-template')
            ->with('status', $installer->install($business));
    }

    public function importPayrollTemplate(Request $request): RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        $request->validate([
            'definition' => ['required', 'string', 'max:500000'],
        ]);

        $decoded = json_decode((string) $request->input('definition'), true);
        if (! is_array($decoded)) {
            return back()->withErrors(['definition' => __('Paste valid JSON (object with title, rule_set_name, rules, …).')])->withInput();
        }

        try {
            $validated = $this->payrollCustomTemplates->validateImportPayload($decoded);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $template = $this->payrollCustomTemplates->store($business, $validated);
        $message = $this->payrollCustomTemplates->apply($business, $template);

        return redirect()->route('hr.payroll.regional-template')->with('status', $message);
    }

    public function storeRuleSet(Request $request): RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:140'],
            'currency' => ['nullable', 'string', 'max:16'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_default' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $ruleSet = new PayrollRuleSet;
        $ruleSet->fill([
            'business_id' => $business->id,
            'name' => $validated['name'],
            'currency' => (string) ($validated['currency'] ?? 'LKR'),
            'effective_from' => $validated['effective_from'],
            'effective_to' => $validated['effective_to'] ?? null,
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'is_active' => true,
            'notes' => $validated['notes'] ?? null,
        ]);
        $ruleSet->save();

        return redirect()->route('hr.payroll.rule-sets.index')->with('status', __('Payroll rule set created.'));
    }

    public function storeRule(Request $request, PayrollRuleSet $ruleSet): RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        abort_if((int) $ruleSet->business_id !== (int) $business->id, 404);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:140'],
            'component_type' => ['required', 'string', 'max:32'],
            'calculation_mode' => ['required', 'string', 'max:24'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_taxable' => ['nullable', 'boolean'],
            'is_statutory' => ['nullable', 'boolean'],
            'config_json' => ['nullable', 'string'],
        ]);

        $config = [];
        if (isset($validated['config_json']) && trim((string) $validated['config_json']) !== '') {
            $decoded = json_decode((string) $validated['config_json'], true);
            if (! is_array($decoded)) {
                return back()->withErrors(['config_json' => __('Config JSON must be valid JSON object/array.')])->withInput();
            }
            $config = $decoded;
        }

        $ruleSet->rules()->create([
            'code' => strtoupper((string) $validated['code']),
            'name' => $validated['name'],
            'component_type' => $validated['component_type'],
            'calculation_mode' => $validated['calculation_mode'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_taxable' => (bool) ($validated['is_taxable'] ?? false),
            'is_statutory' => (bool) ($validated['is_statutory'] ?? false),
            'is_active' => true,
            'config_json' => $config,
        ]);

        return redirect()->route('hr.payroll.rule-sets.index')->with('status', __('Payroll rule added.'));
    }

    public function updateRule(Request $request, PayrollRule $payrollRule): RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        abort_if((int) $payrollRule->ruleSet?->business_id !== (int) $business->id, 404);

        $validated = $request->validate([
            'config_json' => ['required', 'string'],
        ]);

        $decoded = json_decode((string) $validated['config_json'], true);
        if (! is_array($decoded)) {
            return back()->withErrors(['config_json' => __('Config JSON must be valid JSON object/array.')])->withInput();
        }

        $payrollRule->config_json = $decoded;
        $payrollRule->save();

        return redirect()->route('hr.payroll.rule-sets.index')->with('status', __('Rule configuration updated.'));
    }

    public function storeCycle(Request $request): RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        $validated = $request->validate([
            'rule_set_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:140'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => [
                'required',
                'integer',
                'min:1',
                'max:12',
                Rule::unique('hr_payroll_cycles', 'month')->where(
                    fn ($query) => $query
                        ->where('business_id', $business->id)
                        ->where('year', (int) $request->input('year'))
                ),
            ],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ], [
            'month.unique' => __('A payroll cycle already exists for this business in that month and year. Open it from the list or choose a different period.'),
        ]);

        $ruleSet = PayrollRuleSet::query()
            ->where('business_id', $business->id)
            ->whereKey((int) $validated['rule_set_id'])
            ->firstOrFail();

        PayrollCycle::query()->create([
            'business_id' => $business->id,
            'rule_set_id' => $ruleSet->id,
            'name' => $validated['name'],
            'year' => (int) $validated['year'],
            'month' => (int) $validated['month'],
            'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'],
            'status' => PayrollCycle::STATUS_DRAFT,
        ]);

        return redirect()->route('hr.payroll.index')->with('status', __('Payroll cycle created.'));
    }

    public function destroyCycle(Request $request, PayrollCycle $cycle): RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        abort_if((int) $cycle->business_id !== (int) $business->id, 404);

        if ($cycle->ledgerTransactions()->exists()) {
            return redirect()
                ->route('hr.payroll.index')
                ->with('warning', __('This cycle has a recorded bank payment. Remove or reverse the ledger entry before deleting the cycle.'));
        }

        $cycle->delete();

        return redirect()->route('hr.payroll.index')->with('status', __('Payroll cycle deleted.'));
    }

    public function showCycle(Request $request, PayrollCycle $cycle): RedirectResponse|View
    {
        $business = $this->resolveBusiness($request);
        abort_if((int) $cycle->business_id !== (int) $business->id, 404);

        $cycle->load(['ruleSet', 'items.employee', 'items.components.rule']);
        $cycle->loadCount('ledgerTransactions');

        $summary = $this->buildCycleSummary($cycle);

        $paymentAccounts = Account::query()
            ->where('user_id', $request->user()->id)
            ->where('business_id', $business->id)
            ->with(['bank'])
            ->orderBy('account_name')
            ->get();

        $payrollPayment = null;
        if ($cycle->ledger_transactions_count > 0) {
            $payrollPayment = $cycle->ledgerTransactions()->with('deductAccount.bank')->first();
        }

        return view('hrmanagement::payroll.cycle', [
            'business' => $business,
            'cycle' => $cycle,
            'summary' => $summary,
            'paymentAccounts' => $paymentAccounts,
            'totalNetPay' => round((float) ($summary['total_net'] ?? 0), 2),
            'payrollPaymentRecorded' => $cycle->ledger_transactions_count > 0,
            'payrollPayment' => $payrollPayment,
        ]);
    }

    public function recordPayrollPayment(Request $request, PayrollCycle $cycle): RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        abort_if((int) $cycle->business_id !== (int) $business->id, 404);

        $validated = $request->validate([
            'deduct_account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where(fn ($q) => $q
                    ->where('user_id', $request->user()->id)
                    ->where('business_id', $business->id)),
            ],
        ]);

        try {
            $this->payrollCyclePayment->recordPayment(
                $request->user(),
                $business,
                $cycle,
                (int) $validated['deduct_account_id'],
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('status', __('Payment recorded. Total net pay was deducted from the selected account.'));
    }

    public function generateSalarySheet(Request $request, PayrollCycle $cycle): RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        abort_if((int) $cycle->business_id !== (int) $business->id, 404);

        if ($cycle->items()->count() === 0 || ! $cycle->isFinalized()) {
            $result = $this->payrollComputation->computeCycle($cycle->fresh('business'));
            if ($result['errors'] !== []) {
                return back()->with('warning', __('Salary sheet generated with some computation warnings. Please review this cycle.'));
            }
        }

        return redirect()
            ->route('hr.payroll.cycles.salary-sheet', $cycle)
            ->with('status', __('Monthly salary sheet generated for all employees.'));
    }

    public function showSalarySheet(Request $request, PayrollCycle $cycle): RedirectResponse|View
    {
        $business = $this->resolveBusiness($request);
        abort_if((int) $cycle->business_id !== (int) $business->id, 404);

        $cycle->load([
            'ruleSet.rules' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            'items.employee',
            'items.components',
        ]);
        $sheet      = $this->salarySheetPresentation->forCycle($cycle, $business);
        $summary    = $this->buildCycleSummary($cycle);
        $mainBranch = $business->branches()->first();
        $lh         = $this->letterheadViewData($business, 'hr_salary_sheet');

        return view('hrmanagement::payroll.salary-sheet', [
            'business'             => $business,
            'cycle'                => $cycle,
            'sheetColumns'         => $sheet['columns'],
            'rows'                 => $sheet['rows'],
            'varianceMeta'         => $sheet['variance'] ?? [],
            'summary'              => $summary,
            'mainBranch'           => $mainBranch,
            'accentColor'          => $lh['accentColor'],
            'letterheadCanvasJson' => $lh['letterheadCanvasJson'],
        ]);
    }

    public function exportSalarySheetExcel(Request $request, PayrollCycle $cycle): StreamedResponse
    {
        $business = $this->resolveBusiness($request);
        abort_if((int) $cycle->business_id !== (int) $business->id, 404);

        $cycle->load([
            'ruleSet.rules' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            'items.employee',
            'items.components',
        ]);
        $sheet = $this->salarySheetPresentation->forCycle($cycle, $business);
        $currency = (string) ($cycle->ruleSet?->currency ?: ($business->currency ?? 'LKR'));

        return $this->salarySheetExcelExport->streamResponse(
            $cycle,
            $sheet['columns'],
            $sheet['rows'],
            $currency,
        );
    }

    public function computeCycle(Request $request, PayrollCycle $cycle): RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        abort_if((int) $cycle->business_id !== (int) $business->id, 404);
        if ($cycle->isFinalized()) {
            return back()->withErrors(['cycle' => __('Finalized payroll cycle cannot be recomputed.')]);
        }

        $result = $this->payrollComputation->computeCycle($cycle->fresh('business'));
        if ($result['errors'] !== []) {
            return back()->with('warning', __('Computed with some errors. Review cycle details.'));
        }

        return back()->with('status', __('Payroll cycle computed for :count employees.', ['count' => $result['computed']]));
    }

    public function recomputeEmployee(Request $request, PayrollCycle $cycle, PayrollItem $item): RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        abort_if((int) $cycle->business_id !== (int) $business->id, 404);
        abort_if((int) $item->payroll_cycle_id !== (int) $cycle->id, 404);
        if ($cycle->isFinalized()) {
            return back()->withErrors(['cycle' => __('Finalized payroll cycle cannot be changed.')]);
        }

        if (! $this->payrollComputation->employeeEligibleForPayrollCycle($cycle, $item->employee)) {
            return back()->withErrors([
                'employee' => __('This employee cannot be paid in this cycle: joining date is after the cycle period ends. Run Compute all to refresh payroll lines.'),
            ]);
        }

        $validated = $request->validate([
            'overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'overtime_rate' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'attendance_days' => ['nullable', 'numeric', 'min:0', 'max:31'],
            'working_days' => ['nullable', 'numeric', 'min:0', 'max:31'],
            'leave_without_pay_days' => ['nullable', 'numeric', 'min:0', 'max:31'],
            'salary_advance' => ['nullable', 'numeric', 'min:0', 'max:99999999999.99'],
            'stamp_duty' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $prev = is_array($item->inputs_json) ? $item->inputs_json : [];
        $this->payrollComputation->computeEmployee($cycle, $item->employee, [...$prev, ...$validated]);

        return back()->with('status', __('Employee payroll recomputed.'));
    }

    public function finalizeCycle(Request $request, PayrollCycle $cycle): RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        abort_if((int) $cycle->business_id !== (int) $business->id, 404);

        try {
            $this->payrollComputation->finalizeCycle($cycle->fresh('items'), (int) $request->user()->id);
        } catch (\Throwable $e) {
            return back()->withErrors(['cycle' => $e->getMessage()]);
        }

        return back()->with('status', __('Payroll cycle finalized.'));
    }

    public function showPayslip(Request $request, PayrollCycle $cycle, PayrollItem $item): RedirectResponse|View
    {
        $business = $this->resolveBusiness($request);
        abort_if((int) $cycle->business_id !== (int) $business->id, 404);
        abort_if((int) $item->payroll_cycle_id !== (int) $cycle->id, 404);
        $item->load(['employee', 'components']);
        $leaveContext = $this->hrPayslipLeave->payslipLeaveContext($cycle, $item->employee);
        $mainBranch   = $business->branches()->first();
        $lh           = $this->letterheadViewData($business, 'hr_payslip');

        return view('hrmanagement::payroll.payslip', [
            'business'             => $business,
            'cycle'                => $cycle,
            'item'                 => $item,
            'leaveContext'         => $leaveContext,
            'mainBranch'           => $mainBranch,
            'accentColor'          => $lh['accentColor'],
            'letterheadCanvasJson' => $lh['letterheadCanvasJson'],
        ]);
    }

    public function downloadPayslip(Request $request, PayrollCycle $cycle, PayrollItem $item)
    {
        $business = $this->resolveBusiness($request);
        abort_if((int) $cycle->business_id !== (int) $business->id, 404);
        abort_if((int) $item->payroll_cycle_id !== (int) $cycle->id, 404);

        $item->load(['employee', 'components']);
        $leaveContext = $this->hrPayslipLeave->payslipLeaveContext($cycle, $item->employee);
        $mainBranch   = $business->branches()->first();
        $lh           = $this->letterheadViewData($business, 'hr_payslip');
        $html = view('hrmanagement::payroll.payslip', [
            'business'             => $business,
            'cycle'                => $cycle,
            'item'                 => $item,
            'leaveContext'         => $leaveContext,
            'isDownload'           => true,
            'mainBranch'           => $mainBranch,
            'accentColor'          => $lh['accentColor'],
            'letterheadCanvasJson' => $lh['letterheadCanvasJson'],
        ])->render();

        $filename = sprintf(
            'payslip-%s-%s-%s.html',
            $item->employee?->employee_id ?: 'employee',
            $cycle->year,
            str_pad((string) $cycle->month, 2, '0', STR_PAD_LEFT)
        );

        return response($html)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    /**
     * @return array{payrollTemplateCards: list<array{key: string, title: string, description: string, highlights: list<string>}>, selectedPayrollTemplate: string}
     */
    private function payrollTemplateViewData(Business $business): array
    {
        $keys = $this->allowedPayrollTemplateKeys($business);
        $fallback = $keys[0] ?? SriLankanEmployeeStandardPayrollTemplate::KEY;
        $selected = (string) ($this->settings->get($business, 'hr.payroll.template', $fallback) ?: $fallback);

        return [
            'payrollTemplateCards' => array_merge(
                $this->payrollRegionalTemplates->cards(),
                $this->customPayrollTemplateCards($business),
            ),
            'selectedPayrollTemplate' => $selected,
        ];
    }

    /** @return list<string> */
    private function allowedPayrollTemplateKeys(Business $business): array
    {
        $custom = $business->payrollCustomTemplates()
            ->pluck('id')
            ->map(static fn ($id): string => PayrollCustomTemplate::KEY_PREFIX.$id)
            ->all();

        return array_values(array_merge($this->payrollRegionalTemplates->registeredKeys(), $custom));
    }

    /**
     * @return list<array{key: string, title: string, description: string, highlights: list<string>}>
     */
    private function customPayrollTemplateCards(Business $business): array
    {
        $out = [];
        foreach ($business->payrollCustomTemplates()->reorder()->orderBy('id')->get() as $row) {
            $highlights = is_array($row->highlights) ? array_values(array_map(static fn ($h) => (string) $h, $row->highlights)) : [];
            $out[] = [
                'key' => $row->templateKey(),
                'title' => $row->title,
                'description' => (string) ($row->description ?? ''),
                'highlights' => $highlights !== [] ? $highlights : [__('Imported template for this business.')],
                'is_custom' => true,
            ];
        }

        return $out;
    }

    /** @return array{letterheadCanvasJson: string|null, accentColor: string} */
    private function letterheadViewData(Business $business, string $docType = ''): array
    {
        $accentColor          = '#3B82F6';
        $letterheadCanvasJson = null;

        if ($docType !== '') {
            $lhLinks = (array) get_settings('design_studio.lh_links', ['po', 'grn', 'hr_payslip', 'hr_salary_sheet'], $business);
            if (!in_array($docType, $lhLinks)) {
                return compact('letterheadCanvasJson', 'accentColor');
            }
        }

        $letterhead = Design::query()
            ->where('business_id', $business->id)
            ->where('type', 'letterhead')
            ->latest('updated_at')
            ->first();

        if ($letterhead) {
            try {
                $raw     = (string) $letterhead->canvas_json;
                $decoded = json_decode($raw, true);
                $objs    = [];
                if (is_array($decoded)) {
                    if (isset($decoded['objects']) && is_array($decoded['objects'])) {
                        $letterheadCanvasJson = $raw;
                        $objs                 = $decoded['objects'];
                    } elseif (!empty($decoded[0]['json'])) {
                        $letterheadCanvasJson = $decoded[0]['json'];
                        $objs                 = json_decode((string) $decoded[0]['json'], true)['objects'] ?? [];
                    }
                }
                foreach ($objs as $obj) {
                    if (($obj['type'] ?? '') === 'rect'
                        && (float) ($obj['top'] ?? 99) < 4
                        && (float) ($obj['height'] ?? 99) <= 8
                        && !empty($obj['fill'])
                        && preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $obj['fill'])) {
                        $accentColor = $obj['fill'];
                        break;
                    }
                }
            } catch (\Throwable) {}
        }

        return compact('letterheadCanvasJson', 'accentColor');
    }

    private function resolveBusiness(Request $request): Business
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            abort(403);
        }
        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);

        return $business;
    }

    private function loadRuleSets(Business $business)
    {
        $ruleSets = $business->payrollRuleSets()->withCount('rules')->get();
        if ($ruleSets->isEmpty()) {
            $this->payrollRegionalTemplates->seedEmptyBusinessDefaults($business);
            $ruleSets = $business->payrollRuleSets()->withCount('rules')->get();
        }

        return $ruleSets;
    }

    /**
     * @return array{
     *     total_gross: float,
     *     total_deductions: float,
     *     total_net: float,
     *     epf: float,
     *     etf: float,
     *     apit: float,
     *     employee_rows: array<int, array<string, mixed>>,
     *     status_counts: array{computed: int, finalized: int, error: int, other: int}
     * }
     */
    private function buildCycleSummary(PayrollCycle $cycle): array
    {
        $items = $cycle->items;
        $totals = [
            'total_gross' => round((float) $items->sum('gross_earnings'), 2),
            'total_deductions' => round((float) $items->sum('total_deductions'), 2),
            'total_net' => round((float) $items->sum('net_pay'), 2),
            'epf' => 0.0,
            'etf' => 0.0,
            'apit' => 0.0,
            'employee_rows' => [],
            'status_counts' => [
                'computed' => 0,
                'finalized' => 0,
                'error' => 0,
                'other' => 0,
            ],
        ];

        foreach ($items as $item) {
            $status = strtolower((string) $item->status);
            if (array_key_exists($status, $totals['status_counts'])) {
                $totals['status_counts'][$status]++;
            } else {
                $totals['status_counts']['other']++;
            }

            $row = [
                'item_id' => $item->id,
                'employee_name' => $item->employee?->full_name,
                'employee_id' => $item->employee?->employee_id,
                'net_pay' => round((float) $item->net_pay, 2),
            ];
            $totals['employee_rows'][] = $row;

            foreach ($item->components as $c) {
                $code = strtoupper((string) $c->code);
                $amount = abs((float) $c->amount);
                if ($code === 'EPF_EMPLOYEE') {
                    $totals['epf'] = round($totals['epf'] + $amount, 2);
                } elseif ($code === 'ETF_EMPLOYER') {
                    $totals['etf'] = round($totals['etf'] + $amount, 2);
                } elseif ($code === 'APIT') {
                    $totals['apit'] = round($totals['apit'] + $amount, 2);
                }
            }
        }

        return $totals;
    }
}
