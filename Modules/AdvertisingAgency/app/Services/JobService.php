<?php

namespace Modules\AdvertisingAgency\Services;

use Modules\AdvertisingAgency\Models\Brand;
use Modules\AdvertisingAgency\Models\Job;
use Modules\AdvertisingAgency\Models\Officer;
use Modules\AdvertisingAgency\Models\Reporter;
use Modules\Business\Models\Business;

class JobService
{
    public function list(Business $business, ?string $q = null, ?string $status = null, ?int $reporterId = null)
    {
        return Job::query()
            ->where('business_id', $business->id)
            ->with(['clientBrand:id,name,short_code', 'officer:id,name', 'reporter:id,name'])
            ->when($q, fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('job_ref', 'like', "%{$q}%")
                    ->orWhereHas('clientBrand', fn ($bq) => $bq->where('name', 'like', "%{$q}%"));
            }))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($reporterId, fn ($query) => $query->where('reporter_id', $reporterId))
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
    }

    public function create(Business $business, array $data): Job
    {
        $clientBrandId = $data['client_brand_id'] ?: null;
        $jobRef        = $this->generateJobRef($business, $clientBrandId);

        return Job::create([
            'business_id'     => $business->id,
            'name'            => $data['name'],
            'job_ref'         => $jobRef,
            'client_brand_id' => $clientBrandId,
            'officer_id'      => $data['officer_id']  ?: null,
            'reporter_id'     => $data['reporter_id'] ?: null,
            'description'     => $data['description'] ?? null,
            'status'          => $data['status']      ?? 'pending',
            'start_date'      => $data['start_date']  ?: null,
        ]);
    }

    public function update(Job $job, array $data): array
    {
        $job->update([
            'name'            => $data['name'],
            'client_brand_id' => $data['client_brand_id'] ?: null,
            'officer_id'      => $data['officer_id']      ?: null,
            'reporter_id'     => $data['reporter_id']     ?: null,
            'description'     => $data['description']     ?? null,
            'status'          => $data['status']          ?? 'pending',
            'start_date'      => $data['start_date']      ?: null,
            // job_ref is never changed after creation
        ]);

        $job->load(['clientBrand:id,name,short_code', 'officer:id,name', 'reporter:id,name']);

        return [
            'id'                => $job->id,
            'job_ref'           => $job->job_ref,
            'name'              => $job->name,
            'client_brand_id'   => $job->client_brand_id,
            'client_brand_name' => $job->clientBrand
                ? $job->clientBrand->name . ' (' . $job->clientBrand->short_code . ')'
                : null,
            'officer_id'        => $job->officer_id,
            'officer_name'      => $job->officer?->name,
            'reporter_id'       => $job->reporter_id,
            'reporter_name'     => $job->reporter?->name,
            'description'       => $job->description,
            'status'            => $job->status,
            'start_date'        => $job->start_date?->format('Y-m-d'),
        ];
    }

    public function delete(Job $job): void
    {
        $job->delete();
    }

    /**
     * Bulk-import jobs from a validated rows array.
     * Resolves brand by short_code, officer/reporter by name (case-insensitive).
     * All valid rows are created; unresolvable brand/officer/reporter references
     * are noted in the result but do not block creation.
     */
    public function import(Business $business, array $rows): array
    {
        $created    = 0;
        $rowResults = [];

        // Pre-load lookup maps scoped to this business
        $brands   = Brand::where('business_id', $business->id)->get()
            ->keyBy(fn ($b) => strtoupper($b->short_code));
        $officers = Officer::where('business_id', $business->id)->get()
            ->keyBy(fn ($o) => strtolower($o->name));
        $reporters = Reporter::where('business_id', $business->id)->get()
            ->keyBy(fn ($r) => strtolower($r->name));

        foreach ($rows as $i => $row) {
            $name         = trim($row['name']);
            $brandCode    = strtoupper(trim($row['brand_code'] ?? ''));
            $status       = ($row['status'] ?? '') ?: 'pending';
            $startDate    = ($row['start_date'] ?? '') ?: null;
            $description  = ($row['description'] ?? '') ?: null;
            $officerName  = strtolower(trim($row['officer']  ?? ''));
            $reporterName = strtolower(trim($row['reporter'] ?? ''));

            // Resolve brand
            $clientBrandId = null;
            $notes         = [];
            if ($brandCode !== '') {
                $brand = $brands->get($brandCode);
                $clientBrandId = $brand?->id;
                if (!$brand) {
                    $notes[] = "Brand '{$brandCode}' not found";
                }
            }

            // Resolve officer / reporter (soft — missing is just a note)
            $officerId  = $officerName  !== '' ? ($officers->get($officerName)?->id  ?? null) : null;
            $reporterId = $reporterName !== '' ? ($reporters->get($reporterName)?->id ?? null) : null;
            if ($officerName  !== '' && !$officerId)  $notes[] = "Officer '{$row['officer']}' not found";
            if ($reporterName !== '' && !$reporterId) $notes[] = "Reporter '{$row['reporter']}' not found";

            $jobRef = $this->generateJobRef($business, $clientBrandId);

            Job::create([
                'business_id'     => $business->id,
                'name'            => $name,
                'job_ref'         => $jobRef,
                'client_brand_id' => $clientBrandId,
                'officer_id'      => $officerId,
                'reporter_id'     => $reporterId,
                'description'     => $description,
                'status'          => $status,
                'start_date'      => $startDate,
            ]);

            $created++;
            $rowResults[] = [
                'row'     => $i + 1,
                'name'    => $name,
                'job_ref' => $jobRef,
                'status'  => 'created',
                'notes'   => $notes,
            ];
        }

        return [
            'created' => $created,
            'total'   => count($rows),
            'rows'    => $rowResults,
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Generate a job reference in the format YY/SHORT_CODE/NNN.
     *
     * Examples: 26/DIM/001  26/DIM/002  26/ABC/001
     *
     * The sequential number is scoped to this business + client brand (all years),
     * so it increments forever: 001, 002, …, 099, 100, …, 736, …
     */
    private function generateJobRef(Business $business, ?int $clientBrandId): string
    {
        $year = date('y'); // 2-digit year, e.g. "26"

        // Resolve client short code
        $shortCode = 'GEN';
        if ($clientBrandId !== null) {
            $brand     = Brand::find($clientBrandId);
            $shortCode = $brand ? strtoupper($brand->short_code) : 'GEN';
        }

        // Count existing jobs for this business + client (across all years)
        $count = Job::where('business_id', $business->id)
            ->where('client_brand_id', $clientBrandId)
            ->count() + 1;

        // Pad to at least 3 digits; grows naturally for large counts
        $seq = str_pad($count, 3, '0', STR_PAD_LEFT);

        return "{$year}/{$shortCode}/{$seq}";
    }
}
