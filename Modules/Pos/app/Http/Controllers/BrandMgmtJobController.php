<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\AdvertisingAgency\Models\Brand;
use Modules\AdvertisingAgency\Models\Job;
use Modules\AdvertisingAgency\Models\Officer;
use Modules\AdvertisingAgency\Models\Reporter;
use Modules\AdvertisingAgency\Services\JobService;
use Modules\Business\Models\Business;
use Modules\Pos\Http\Controllers\Concerns\ImportsCsvRows;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosBusiness;

class BrandMgmtJobController extends Controller
{
    use ResolvesPosBusiness, ImportsCsvRows;

    private const IMPORT_HEADERS = ['name', 'brand_code', 'status', 'start_date', 'description', 'officer', 'reporter'];

    public function __construct(private readonly JobService $service) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));

        return view('pos::brand-mgmt.jobs.index', array_merge([
            'business' => $business,
            'jobs'     => $this->service->list($business, $search ?: null, $status ?: null),
            'search'   => $search,
            'status'   => $status,
        ], $this->dropdownOptions($business)));
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $this->service->create($business, $this->validateJob($request));

        return redirect()->route('pos.brand-mgmt.jobs.index')->with('status', 'Job added.');
    }

    public function update(Request $request, Job $job): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $job->business_id === (int) $business->id, 403);

        $this->service->update($job, $this->validateJob($request));

        return redirect()->route('pos.brand-mgmt.jobs.index')->with('status', 'Job updated.');
    }

    public function destroy(Request $request, Job $job): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $job->business_id === (int) $business->id, 403);

        $this->service->delete($job);

        return redirect()->route('pos.brand-mgmt.jobs.index')->with('status', 'Job deleted.');
    }

    public function import(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        [$rows, $error] = $this->parseCsv($request->file('file'), self::IMPORT_HEADERS);
        if ($error) {
            return back()->withErrors(['file' => $error]);
        }

        [$validRows, $rowNumbers, $invalidResults] = $this->splitValidRows($rows, [
            'name'        => ['required', 'string', 'max:200'],
            'brand_code'  => ['nullable', 'string', 'max:10'],
            'status'      => ['nullable', 'string', 'in:pending,in_progress,completed,cancelled'],
            'start_date'  => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'officer'     => ['nullable', 'string', 'max:200'],
            'reporter'    => ['nullable', 'string', 'max:200'],
        ]);

        $serviceResult = $this->service->import($business, $validRows);
        $results = $this->mergeImportResults($serviceResult, $rowNumbers, $invalidResults);

        return redirect()->route('pos.brand-mgmt.jobs.index')->with('import_results', $results);
    }

    private function validateJob(Request $request): array
    {
        return $request->validate([
            'name'            => ['required', 'string', 'max:200'],
            'client_brand_id' => ['nullable', 'integer', 'exists:bm_brands,id'],
            'officer_id'      => ['nullable', 'integer'],
            'reporter_id'     => ['nullable', 'integer'],
            'description'     => ['nullable', 'string', 'max:2000'],
            'status'          => ['required', 'string', 'in:pending,in_progress,completed,cancelled'],
            'start_date'      => ['nullable', 'date'],
        ]);
    }

    private function dropdownOptions(Business $business): array
    {
        return [
            'brands'    => Brand::where('business_id', $business->id)->orderBy('name')->get(['id', 'name', 'short_code']),
            'officers'  => Officer::where('business_id', $business->id)->orderBy('name')->get(['id', 'name']),
            'reporters' => Reporter::where('business_id', $business->id)->orderBy('name')->get(['id', 'name']),
        ];
    }
}
