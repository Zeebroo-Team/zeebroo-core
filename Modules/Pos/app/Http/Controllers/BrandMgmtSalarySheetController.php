<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\AdvertisingAgency\Models\Coordinator;
use Modules\AdvertisingAgency\Models\Job;
use Modules\AdvertisingAgency\Models\Promoter;
use Modules\AdvertisingAgency\Models\SalarySheet;
use Modules\AdvertisingAgency\Services\SalarySheetService;
use Modules\Business\Models\Business;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosBusiness;

class BrandMgmtSalarySheetController extends Controller
{
    use ResolvesPosBusiness;

    /**
     * Allowed status transitions, keyed by current status. Lives here (not in
     * SalarySheetService) because that service is still used unmodified by the
     * existing JSON API, which has no transition guard.
     */
    private const TRANSITIONS = [
        'draft'     => ['completed' => 'Submit for Approval'],
        'completed' => ['approved' => 'Approve', 'rejected' => 'Reject', 'draft' => 'Back to Draft'],
        'approved'  => ['paid' => 'Mark as Paid', 'rejected' => 'Reject'],
        'rejected'  => ['draft' => 'Reopen to Draft'],
        'paid'      => [],
    ];

    public function __construct(private readonly SalarySheetService $service) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $search = trim((string) $request->query('q', ''));
        $sheets = $this->service->list($business, $search ?: null);

        return view('pos::brand-mgmt.salary-sheets.index', [
            'business' => $business,
            'sheets'   => $sheets,
            'search'   => $search,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        return view('pos::brand-mgmt.salary-sheets.create', [
            'business' => $business,
            'jobs'     => Job::where('business_id', $business->id)->orderBy('name')->get(['id', 'name', 'job_ref']),
            'nextRef'  => $this->service->previewNextRef($business),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $data = $request->validate([
            'location'  => ['nullable', 'string', 'max:200'],
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date'],
            'job_id'    => ['nullable', 'integer'],
            'notes'     => ['nullable', 'string', 'max:2000'],
        ]);

        $sheet = $this->service->create($business, $data);

        return redirect()->route('pos.brand-mgmt.salary-sheets.show', $sheet)
            ->with('status', "Salary sheet {$sheet->sheet_ref} created.");
    }

    public function show(Request $request, SalarySheet $salarySheet): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $salarySheet->business_id === (int) $business->id, 403);

        $sheet = $this->service->show($business, $salarySheet->id);

        $dates = $sheet['custom_dates'] ?: $this->dateRange($sheet['date_from'], $sheet['date_to']);

        return view('pos::brand-mgmt.salary-sheets.show', [
            'business'           => $business,
            'sheet'              => $sheet,
            'dates'              => $dates,
            'jobs'               => Job::where('business_id', $business->id)->orderBy('name')->get(['id', 'name', 'job_ref']),
            'promoters'          => Promoter::where('business_id', $business->id)->orderBy('name')
                ->get(['id', 'name', 'position', 'bank_name', 'bank_branch', 'bank_account']),
            'coordinators'       => Coordinator::where('business_id', $business->id)->orderBy('name')
                ->get(['id', 'name', 'bank_name', 'bank_branch', 'bank_account']),
            'allowedTransitions' => self::TRANSITIONS[$sheet['status']] ?? [],
        ]);
    }

    public function update(Request $request, SalarySheet $salarySheet): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $salarySheet->business_id === (int) $business->id, 403);

        $data = $request->validate([
            'job_id'                          => ['sometimes', 'nullable', 'integer'],
            'location'                        => ['nullable', 'string', 'max:200'],
            'date_from'                       => ['sometimes', 'nullable', 'date'],
            'date_to'                         => ['sometimes', 'nullable', 'date'],
            'custom_dates'                    => ['sometimes', 'nullable', 'array'],
            'custom_dates.*'                  => ['date'],
            'notes'                           => ['nullable', 'string', 'max:2000'],
            'default_coordinator_fee'         => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'allowances'                           => ['sometimes', 'array'],
            'allowances.*.id'                      => ['nullable', 'integer'],
            'allowances.*.allowance_type'          => ['required_with:allowances', 'string', 'max:150'],
            'allowances.*.amount'                  => ['nullable', 'numeric', 'min:0'],
            'allowances.*.description'             => ['nullable', 'string', 'max:1000'],
            'position_rules'                       => ['sometimes', 'array'],
            'position_rules.*.id'                  => ['nullable', 'integer'],
            'position_rules.*.position_name'       => ['required_with:position_rules', 'string', 'max:150'],
            'position_rules.*.daily_rate'          => ['nullable', 'numeric', 'min:0'],
            'position_rules.*.transport_allowance' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->service->update($salarySheet, $data);

        return redirect()->route('pos.brand-mgmt.salary-sheets.show', $salarySheet)->with('status', 'Salary sheet updated.');
    }

    public function saveRows(Request $request, SalarySheet $salarySheet): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $salarySheet->business_id === (int) $business->id, 403);

        $data = $request->validate([
            'rows'                            => ['required', 'array'],
            'rows.*.id'                       => ['nullable', 'integer'],
            'rows.*.item_number'              => ['nullable', 'string', 'max:30'],
            'rows.*.location'                 => ['nullable', 'string', 'max:100'],
            'rows.*.position'                 => ['nullable', 'string', 'max:100'],
            'rows.*.promoter_id'              => ['nullable', 'integer'],
            'rows.*.promoter_name'            => ['nullable', 'string', 'max:150'],
            'rows.*.bank_name'                => ['nullable', 'string', 'max:150'],
            'rows.*.bank_branch'              => ['nullable', 'string', 'max:150'],
            'rows.*.bank_account'             => ['nullable', 'string', 'max:100'],
            'rows.*.daily_rate'               => ['nullable', 'numeric', 'min:0'],
            'rows.*.transport_allowance'      => ['nullable', 'numeric', 'min:0'],
            'rows.*.expenses'                 => ['nullable', 'numeric', 'min:0'],
            'rows.*.hold_amount'              => ['nullable', 'numeric', 'min:0'],
            'rows.*.total_days'               => ['nullable', 'integer', 'min:0'],
            'rows.*.attendance_amount'        => ['nullable', 'numeric', 'min:0'],
            'rows.*.base_amount'              => ['nullable', 'numeric', 'min:0'],
            'rows.*.net_amount'               => ['nullable', 'numeric'],
            'rows.*.coordinator_id'           => ['nullable', 'integer'],
            'rows.*.coordinator_name'         => ['nullable', 'string', 'max:150'],
            'rows.*.coordination_fee'         => ['nullable', 'numeric', 'min:0'],
            'rows.*.coordinator_bank_details' => ['nullable', 'string', 'max:250'],
            'rows.*.attendances'              => ['nullable', 'array'],
            'rows.*.attendances.*.date'       => ['required', 'date'],
            'rows.*.attendances.*.status'     => ['required', 'in:P,A'],
        ]);

        $this->service->saveRows($salarySheet, $data['rows']);

        return redirect()->route('pos.brand-mgmt.salary-sheets.show', $salarySheet)->with('status', 'Line items saved.');
    }

    public function transitionStatus(Request $request, SalarySheet $salarySheet): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $salarySheet->business_id === (int) $business->id, 403);

        $data = $request->validate(['to_status' => ['required', 'string']]);
        $allowed = self::TRANSITIONS[$salarySheet->status] ?? [];
        abort_unless(array_key_exists($data['to_status'], $allowed), 422);

        $this->service->update($salarySheet, ['status' => $data['to_status']]);

        return redirect()->route('pos.brand-mgmt.salary-sheets.show', $salarySheet)
            ->with('status', "Salary sheet marked {$data['to_status']}.");
    }

    public function destroy(Request $request, SalarySheet $salarySheet): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $salarySheet->business_id === (int) $business->id, 403);

        $this->service->delete($salarySheet);

        return redirect()->route('pos.brand-mgmt.salary-sheets.index')->with('status', 'Salary sheet deleted.');
    }

    private function dateRange(?string $from, ?string $to): array
    {
        if (! $from || ! $to) {
            return [];
        }

        $dates = [];
        $cursor = strtotime($from);
        $end = strtotime($to);
        if ($cursor === false || $end === false || $cursor > $end) {
            return [];
        }

        while ($cursor <= $end) {
            $dates[] = date('Y-m-d', $cursor);
            $cursor = strtotime('+1 day', $cursor);
        }

        return $dates;
    }
}
