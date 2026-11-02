<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadImport;
use Modules\CRM\Models\Project;
use Modules\CRM\Policies\LeadPolicy;
use Modules\CRM\Services\CrmAccess;
use Modules\CRM\Services\CrmPermissionService;
use Modules\CRM\Services\LeadImportService;
use Modules\CRM\Services\LeadIntakeService;
use Modules\CRM\Support\LeadImportReader;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bulk lead upload for the desktop: upload a CSV / XLSX (our template, a Google Forms export or a
 * Facebook Lead Ads export) → map the columns → preview → run, plus the import history.
 */
class PosCrmLeadImportApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    private ?CrmAccess $crmAccess = null;

    public function __construct(
        private readonly LeadImportService $imports,
        private readonly LeadIntakeService $intake,
        private readonly CrmPermissionService $crmPermissions,
        private readonly LeadPolicy $policy,
    ) {}

    /** GET /api/v1/pos/crm/lead-imports — recent imports and the choices for a new one. */
    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $access   = $this->abortUnlessImporter($request, $business);

        $imports = LeadImport::query()
            ->where('business_id', $business->id)
            ->when(! $access->viewsAllLeads(), fn ($q) => $q->where('user_id', $request->user()?->id))
            ->whereNotIn('status', LeadImport::PENDING_STATUSES)
            ->with(['user:id,name', 'project:id,name'])
            ->latest('id')
            ->limit(30)
            ->get();

        $intake = $this->intake->intakeProject($business);

        return response()->json([
            'data' => [
                'imports'        => $imports->map(fn (LeadImport $i) => $this->imports->present($i))->values(),
                'pipelines'      => $this->pipelines($business, $access)->map(fn (Project $p) => ['id' => $p->id, 'name' => $p->name])->values(),
                'intake_project' => $intake ? ['id' => $intake->id, 'name' => $intake->name] : null,
            ] + $this->imports->options(),
        ]);
    }

    /** GET /api/v1/pos/crm/lead-imports/template — the sample CSV. */
    public function template(Request $request): Response
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessImporter($request, $business);

        return $this->csvResponse($this->imports->templateCsv(), 'lead-import-template.csv');
    }

    /** POST /api/v1/pos/crm/lead-imports — multipart "files[]" (desktop upload) or "file". */
    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessImporter($request, $business);

        $file = $request->file('file') ?? ($request->file('files')[0] ?? null);
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            throw ValidationException::withMessages(['file' => 'Choose a CSV or Excel file to upload.']);
        }
        if (! in_array(strtolower($file->getClientOriginalExtension()), LeadImportReader::EXTENSIONS, true)) {
            throw ValidationException::withMessages(['file' => 'Upload a .csv, .xlsx or .xls file.']);
        }
        if ($file->getSize() > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'The file is larger than 10 MB. Split it into smaller files.']);
        }

        $upload = $this->imports->upload($business, $file, $request->user()?->id);

        return response()->json([
            'message' => 'File read: ' . number_format($upload['import']->total_rows) . ' rows (' . LeadImport::formatLabel($upload['import']->format) . ').',
            'data'    => $this->withColumns($upload['import'], $upload['headers'], $upload['sample']),
        ], 201);
    }

    /** GET /api/v1/pos/crm/lead-imports/{import} — status (poll while it runs); columns while not run yet. */
    public function show(Request $request, int $importId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessImporter($request, $business);
        $import = $this->visibleImport($request, $business, $importId);

        if ($import->isPending()) {
            $table = $this->imports->headersAndSample($import);

            return response()->json(['data' => $this->withColumns($import, $table['headers'], $table['sample'])]);
        }

        return response()->json(['data' => $this->imports->present($import)]);
    }

    /** POST /api/v1/pos/crm/lead-imports/{import}/preview — { mapping[], source, project_id?, duplicates } */
    public function preview(Request $request, int $importId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $access   = $this->abortUnlessImporter($request, $business);
        $import   = $this->ownImport($request, $business, $importId);
        if (! $import->isPending()) {
            throw ValidationException::withMessages(['import' => 'This import has already been run.']);
        }

        $data = $request->validate([
            'mapping'    => ['required', 'array'],
            'mapping.*'  => ['nullable', 'string', 'max:40'],
            'source'     => ['required', Rule::in(array_keys(Lead::SOURCES))],
            'project_id' => ['nullable', 'integer'],
            'duplicates' => ['required', Rule::in(array_keys(LeadImportService::DUPLICATE_MODES))],
        ]);

        $project = $this->targetPipeline($business, $access, $data['project_id'] ?? null);
        $result  = $this->imports->preview(
            $import,
            array_map(fn ($v) => (string) $v, array_values($data['mapping'])),
            $data['source'],
            $project,
            $data['duplicates'],
        );

        return response()->json([
            'data' => [
                'import' => $this->imports->present($import->fresh(['project', 'user'])),
                'totals' => $result['totals'],
                'rows'   => $result['rows'],
            ],
        ]);
    }

    /** POST /api/v1/pos/crm/lead-imports/{import}/run — 200 when done, 202 when queued. */
    public function run(Request $request, int $importId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $access   = $this->abortUnlessImporter($request, $business);
        $import   = $this->ownImport($request, $business, $importId);
        if ($import->status !== LeadImport::STATUS_PREVIEWED) {
            throw ValidationException::withMessages(['import' => $import->isPending() ? 'Preview the import first.' : 'This import has already been run.']);
        }
        // The pipeline is checked again: access may have changed since the preview
        $this->targetPipeline($business, $access, $import->project_id);

        $import = $this->imports->start($import)->load(['project', 'user']);
        $queued = $import->status === LeadImport::STATUS_QUEUED;

        return response()->json([
            'message' => $queued
                ? 'Import started — ' . number_format($import->total_rows) . ' rows are being imported in the background.'
                : $this->summary($import),
            'data'    => $this->imports->present($import),
        ], $queued ? 202 : 200);
    }

    /** GET /api/v1/pos/crm/lead-imports/{import}/errors — the rows that failed, as CSV. */
    public function errors(Request $request, int $importId): Response
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessImporter($request, $business);
        $import = $this->visibleImport($request, $business, $importId);

        return $this->csvResponse($this->imports->errorRowsCsv($import), 'lead-import-' . $import->id . '-errors.csv');
    }

    /** DELETE /api/v1/pos/crm/lead-imports/{import} — discard an import that was not run. */
    public function destroy(Request $request, int $importId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessImporter($request, $business);
        $import = $this->ownImport($request, $business, $importId);
        if (! $import->isPending()) {
            throw ValidationException::withMessages(['import' => 'An import that has run is kept in the history.']);
        }

        $this->imports->discard($import);

        return response()->json(['message' => 'Import discarded.']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function withColumns(LeadImport $import, array $headers, array $sample): array
    {
        return $this->imports->present($import->loadMissing(['project', 'user'])) + [
            'headers' => $headers,
            'sample'  => $sample,
        ];
    }

    private function summary(LeadImport $import): string
    {
        if ($import->status === LeadImport::STATUS_FAILED) {
            return 'Import failed: ' . ($import->error ?: 'unknown error') . '.';
        }

        return "Import finished: {$import->created_count} new leads, {$import->duplicate_count} added to existing leads, "
            . "{$import->skipped_count} skipped, {$import->failed_count} failed.";
    }

    /** Pipelines the user may add leads to. */
    private function pipelines(Business $business, CrmAccess $access)
    {
        return Project::query()
            ->where('business_id', $business->id)
            ->orderBy('name')
            ->get()
            ->filter(fn (Project $p) => $this->policy->canCreateLeadIn($access, $p));
    }

    /** The chosen pipeline, or the marketing intake one; 403 when the user may not add leads to it. */
    private function targetPipeline(Business $business, CrmAccess $access, ?int $projectId): Project
    {
        $project = $projectId !== null
            ? Project::query()->where('business_id', $business->id)->find($projectId)
            : $this->intake->intakeProject($business);

        if ($project === null) {
            throw ValidationException::withMessages(['project_id' => $projectId !== null
                ? 'That pipeline no longer exists.'
                : 'Choose the pipeline to import into — no marketing intake pipeline is set up.']);
        }
        $this->abortUnlessAllowed($this->policy->canCreateLeadIn($access, $project));

        return $project;
    }

    private function access(Request $request, Business $business): CrmAccess
    {
        return $this->crmAccess ??= $this->crmPermissions->accessFor($business, $request->user());
    }

    private function abortUnlessImporter(Request $request, Business $business): CrmAccess
    {
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $access = $this->access($request, $business);
        $this->abortUnlessAllowed($access->can('leads_create') && ! $access->isReadOnly());

        return $access;
    }

    /** An import of this business the user started (or may see all of), 404 otherwise. */
    private function visibleImport(Request $request, Business $business, int $importId): LeadImport
    {
        $import = LeadImport::query()->where('business_id', $business->id)->findOrFail($importId);
        if ((int) $import->user_id !== (int) $request->user()?->id && ! $this->access($request, $business)->viewsAllLeads()) {
            abort(404);
        }

        return $import;
    }

    /** Only the person who uploaded the file previews, runs or discards it. */
    private function ownImport(Request $request, Business $business, int $importId): LeadImport
    {
        $import = LeadImport::query()->where('business_id', $business->id)->findOrFail($importId);
        if ((int) $import->user_id !== (int) $request->user()?->id) {
            abort(404);
        }

        return $import;
    }

    private function csvResponse(string $csv, string $filename): Response
    {
        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function abortUnlessAllowed(bool $allowed): void
    {
        if (! $allowed) {
            throw new HttpResponseException(
                response()->json(['message' => 'You do not have permission to perform this action.'], 403)
            );
        }
    }
}
