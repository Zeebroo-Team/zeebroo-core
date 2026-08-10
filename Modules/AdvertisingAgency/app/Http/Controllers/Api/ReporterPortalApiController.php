<?php

namespace Modules\AdvertisingAgency\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AdvertisingAgency\Models\Job;
use Modules\AdvertisingAgency\Models\Reporter;
use Modules\AdvertisingAgency\Models\SalarySheet;
use Modules\AdvertisingAgency\Services\SalarySheetService;
use Modules\Business\Models\Business;

class ReporterPortalApiController extends Controller
{
    public function __construct(private readonly SalarySheetService $salarySheetService) {}

    /**
     * Resolve the Reporter record for the authenticated user (via user_id link).
     * Aborts 403 if the user is not linked to any reporter.
     */
    private function reporterOrAbort(Request $request): Reporter
    {
        $user     = $request->user();
        $reporter = Reporter::where('user_id', $user->id)->first();
        abort_if(! $reporter, 403, 'Not linked to a reporter account.');
        return $reporter;
    }

    /**
     * GET /brand-mgmt/reporter/jobs
     * Returns only jobs where reporter_id = the authenticated user's linked reporter.
     */
    public function jobs(Request $request): JsonResponse
    {
        $reporter = $this->reporterOrAbort($request);
        $q        = $request->query('q') ?: null;

        $jobs = Job::query()
            ->where('business_id', $reporter->business_id)
            ->where('reporter_id', $reporter->id)
            ->with(['clientBrand:id,name,short_code', 'officer:id,name', 'reporter:id,name'])
            ->when($q, fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('job_ref', 'like', "%{$q}%")
                    ->orWhereHas('clientBrand', fn ($bq) => $bq->where('name', 'like', "%{$q}%"));
            }))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($j) => [
                'id'                => $j->id,
                'job_ref'           => $j->job_ref,
                'name'              => $j->name,
                'client_brand_id'   => $j->client_brand_id,
                'client_brand_name' => $j->clientBrand
                    ? $j->clientBrand->name . ' (' . $j->clientBrand->short_code . ')'
                    : null,
                'officer_id'        => $j->officer_id,
                'officer_name'      => $j->officer?->name,
                'reporter_id'       => $j->reporter_id,
                'reporter_name'     => $j->reporter?->name,
                'description'       => $j->description,
                'status'            => $j->status,
                'start_date'        => $j->start_date?->format('Y-m-d'),
                'created_at'        => $j->created_at,
            ]);

        return response()->json(['data' => $jobs]);
    }

    /**
     * GET /brand-mgmt/reporter/salary-sheets
     * Returns salary sheets belonging to jobs assigned to the authenticated reporter.
     */
    public function salarySheets(Request $request): JsonResponse
    {
        $reporter = $this->reporterOrAbort($request);
        $q        = $request->query('q') ?: null;

        $sheets = SalarySheet::query()
            ->where('business_id', $reporter->business_id)
            ->whereHas('job', fn ($jq) => $jq->where('reporter_id', $reporter->id))
            ->when($q, fn ($qry) => $qry->where(function ($sub) use ($q) {
                $sub->where('sheet_ref', 'like', "%{$q}%")
                    ->orWhere('location', 'like', "%{$q}%")
                    ->orWhereHas('job', fn ($j) => $j->where('name', 'like', "%{$q}%"));
            }))
            ->with('job:id,name,job_ref')
            ->withCount('rows')
            ->orderByRaw('CASE WHEN job_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('job_id')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($s) => [
                'id'         => $s->id,
                'sheet_ref'  => $s->sheet_ref,
                'location'   => $s->location,
                'job_id'     => $s->job_id,
                'job_name'   => $s->job?->name,
                'job_ref'    => $s->job?->job_ref,
                'date_from'  => $s->date_from?->format('Y-m-d'),
                'date_to'    => $s->date_to?->format('Y-m-d'),
                'status'     => $s->status,
                'rows_count' => $s->rows_count,
            ]);

        return response()->json(['data' => $sheets]);
    }

    /**
     * GET /brand-mgmt/reporter/salary-sheets/{id}
     * Full salary sheet detail — only if the sheet's job belongs to this reporter.
     */
    public function showSalarySheet(Request $request, int $id): JsonResponse
    {
        $reporter = $this->reporterOrAbort($request);
        $business = Business::findOrFail($reporter->business_id);

        $sheet = SalarySheet::where('business_id', $reporter->business_id)
            ->where('id', $id)
            ->whereHas('job', fn ($jq) => $jq->where('reporter_id', $reporter->id))
            ->firstOrFail();

        return response()->json(['data' => $this->salarySheetService->show($business, $sheet->id)]);
    }
}
