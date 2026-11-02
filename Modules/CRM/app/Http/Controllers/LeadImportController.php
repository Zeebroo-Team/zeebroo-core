<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\CRM\Http\Controllers\Concerns\ResolvesCrmBusiness;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadImport;
use Modules\CRM\Models\Project;
use Modules\CRM\Services\CrmAccess;
use Modules\CRM\Services\LeadImportService;
use Modules\CRM\Services\LeadIntakeService;
use Modules\CRM\Support\LeadImportReader;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bulk lead upload on the web: upload a CSV / XLSX → map the columns → preview → run, plus the
 * import history. Same flow as PosCrmLeadImportApiController for the desktop.
 */
class LeadImportController extends Controller
{
    use ResolvesCrmBusiness;

    public function __construct(
        private readonly LeadImportService $imports,
        private readonly LeadIntakeService $intake,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireImporter($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $access = $this->crmAccess($request, $business);

        $imports = LeadImport::query()
            ->where('business_id', $business->id)
            ->when(! $access->viewsAllLeads(), fn ($q) => $q->where('user_id', $request->user()?->id))
            ->whereNotIn('status', LeadImport::PENDING_STATUSES)
            ->with(['user:id,name', 'project:id,name'])
            ->latest('id')
            ->limit(30)
            ->get();

        // Uploads of this user that were never run, so they can be finished or discarded
        $pending = LeadImport::query()
            ->where('business_id', $business->id)
            ->where('user_id', $request->user()?->id)
            ->whereIn('status', LeadImport::PENDING_STATUSES)
            ->latest('id')
            ->limit(10)
            ->get();

        return view('crm::imports.index', [
            'business'      => $business,
            'imports'       => $imports->map(fn (LeadImport $i) => $this->imports->present($i)),
            'pending'       => $pending,
            'intakeProject' => $this->intake->intakeProject($business),
            'options'       => $this->imports->options(),
            'extensions'    => LeadImportReader::EXTENSIONS,
        ]);
    }

    public function template(Request $request): Response
    {
        $business = $this->requireImporter($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return $this->csvResponse($this->imports->templateCsv(), 'lead-import-template.csv');
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireImporter($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $file = $request->file('file');
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return $this->backToIndex(['file' => 'Choose a CSV or Excel file to upload.']);
        }
        if (! in_array(strtolower($file->getClientOriginalExtension()), LeadImportReader::EXTENSIONS, true)) {
            return $this->backToIndex(['file' => 'Upload a .csv, .xlsx or .xls file.']);
        }
        if ($file->getSize() > 10 * 1024 * 1024) {
            return $this->backToIndex(['file' => 'The file is larger than 10 MB. Split it into smaller files.']);
        }

        try {
            $upload = $this->imports->upload($business, $file, $request->user()?->id);
        } catch (ValidationException $e) {
            return $this->backToIndex($e->errors());
        }

        return redirect()->route('crm.imports.show', $upload['import']->id)->with('status',
            'File read: ' . number_format($upload['import']->total_rows) . ' rows (' . LeadImport::formatLabel($upload['import']->format) . '). Check the columns, then preview.');
    }

    /** Pending: the column mapping (and the preview once checked). Run: the result, refreshed while it runs. */
    public function show(Request $request, int $importId): View|RedirectResponse
    {
        $business = $this->requireImporter($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $access = $this->crmAccess($request, $business);
        $import = $this->visibleImport($request, $business, $importId)->load(['project', 'user']);

        $data = [
            'business' => $business,
            'import'   => $import,
            'row'      => $this->imports->present($import),
            'options'  => $this->imports->options(),
        ];

        if (! $import->isPending()) {
            return view('crm::imports.show', $data);
        }

        abort_unless((int) $import->user_id === (int) $request->user()?->id, 404);

        try {
            $table = $this->imports->headersAndSample($import);
        } catch (ValidationException $e) {
            return $this->backToIndex($e->errors());
        }

        // A previewed import shows its row checks again (the preview saves nothing but the settings)
        $preview      = null;
        $previewError = null;
        if ($import->status === LeadImport::STATUS_PREVIEWED && $import->project !== null) {
            try {
                $preview = $this->imports->preview($import, $import->mapping ?? [], $import->source, $import->project, $import->options['duplicates'] ?? 'log');
            } catch (ValidationException $e) {
                $previewError = collect($e->errors())->flatten()->first();
            }
        }

        return view('crm::imports.show', $data + [
            'headers'      => $table['headers'],
            'sample'       => $table['sample'],
            'pipelines'    => $this->pipelines($business, $access),
            'preview'      => $preview,
            'previewError' => $previewError,
        ]);
    }

    public function preview(Request $request, int $importId): RedirectResponse
    {
        $business = $this->requireImporter($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $access = $this->crmAccess($request, $business);
        $import = $this->ownImport($request, $business, $importId);
        if (! $import->isPending()) {
            return $this->backToShow($import, ['import' => 'This import has already been run.']);
        }

        $data = $request->validate([
            'mapping'    => ['required', 'array'],
            'mapping.*'  => ['nullable', 'string', 'max:40'],
            'source'     => ['required', Rule::in(array_keys(Lead::SOURCES))],
            'project_id' => ['nullable', 'integer'],
            'duplicates' => ['required', Rule::in(array_keys(LeadImportService::DUPLICATE_MODES))],
        ]);

        try {
            $project = $this->targetPipeline($business, $access, isset($data['project_id']) ? (int) $data['project_id'] : null);
            $result  = $this->imports->preview(
                $import,
                array_map(fn ($v) => (string) $v, array_values($data['mapping'])),
                $data['source'],
                $project,
                $data['duplicates'],
            );
        } catch (ValidationException $e) {
            return $this->backToShow($import, $e->errors());
        }

        $t = $result['totals'];

        return redirect()->route('crm.imports.show', $import->id)->with('status',
            "Checked {$t['total']} rows: {$t['ok']} ready, {$t['duplicate']} duplicates, {$t['error']} with errors, {$t['skipped']} skipped. Nothing is saved until you run the import.");
    }

    public function run(Request $request, int $importId): RedirectResponse
    {
        $business = $this->requireImporter($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $access = $this->crmAccess($request, $business);
        $import = $this->ownImport($request, $business, $importId);
        if ($import->status !== LeadImport::STATUS_PREVIEWED) {
            return $this->backToShow($import, ['import' => $import->isPending() ? 'Preview the import first.' : 'This import has already been run.']);
        }

        try {
            // The pipeline is checked again: access may have changed since the preview
            $this->targetPipeline($business, $access, $import->project_id);
            $import = $this->imports->start($import);
        } catch (ValidationException $e) {
            return $this->backToShow($import, $e->errors());
        }

        return redirect()->route('crm.imports.show', $import->id)->with('status', $import->status === LeadImport::STATUS_QUEUED
            ? 'Import started — ' . number_format($import->total_rows) . ' rows are being imported in the background.'
            : $this->summary($import));
    }

    public function errors(Request $request, int $importId): Response
    {
        $business = $this->requireImporter($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $import = $this->visibleImport($request, $business, $importId);

        return $this->csvResponse($this->imports->errorRowsCsv($import), 'lead-import-' . $import->id . '-errors.csv');
    }

    public function destroy(Request $request, int $importId): RedirectResponse
    {
        $business = $this->requireImporter($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $import = $this->ownImport($request, $business, $importId);
        if (! $import->isPending()) {
            return $this->backToShow($import, ['import' => 'An import that has run is kept in the history.']);
        }

        $this->imports->discard($import);

        return redirect()->route('crm.imports.index')->with('status', 'Import discarded.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /** The current business, when the user may add leads (403 otherwise). */
    private function requireImporter(Request $request): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $access = $this->crmAccess($request, $business);
        abort_unless(self::canImport($access), 403);

        return $business;
    }

    /** Whether the CRM nav shows "Import leads". */
    public static function canImport(CrmAccess $access): bool
    {
        return $access->hasCrm() && $access->can('leads_create') && ! $access->isReadOnly();
    }

    /** @return Collection<int, Project> pipelines the user may add leads to */
    private function pipelines(Business $business, CrmAccess $access): Collection
    {
        return Project::query()
            ->where('business_id', $business->id)
            ->orderBy('name')
            ->get()
            ->filter(fn (Project $p) => $this->leadPolicy()->canCreateLeadIn($access, $p))
            ->values();
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
        abort_unless($this->leadPolicy()->canCreateLeadIn($access, $project), 403);

        return $project;
    }

    /** An import of this business the user started (or may see all of), 404 otherwise. */
    private function visibleImport(Request $request, Business $business, int $importId): LeadImport
    {
        $import = LeadImport::query()->where('business_id', $business->id)->findOrFail($importId);
        if ((int) $import->user_id !== (int) $request->user()?->id && ! $this->crmAccess($request, $business)->viewsAllLeads()) {
            abort(404);
        }

        return $import;
    }

    /** Only the person who uploaded the file previews, runs or discards it. */
    private function ownImport(Request $request, Business $business, int $importId): LeadImport
    {
        $import = LeadImport::query()->where('business_id', $business->id)->findOrFail($importId);
        abort_unless((int) $import->user_id === (int) $request->user()?->id, 404);

        return $import;
    }

    private function summary(LeadImport $import): string
    {
        if ($import->status === LeadImport::STATUS_FAILED) {
            return 'Import failed: ' . ($import->error ?: 'unknown error') . '.';
        }

        return "Import finished: {$import->created_count} new leads, {$import->duplicate_count} added to existing leads, "
            . "{$import->skipped_count} skipped, {$import->failed_count} failed.";
    }

    private function csvResponse(string $csv, string $filename): Response
    {
        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function backToIndex(array $errors): RedirectResponse
    {
        return redirect()->route('crm.imports.index')->withErrors($errors);
    }

    private function backToShow(LeadImport $import, array $errors): RedirectResponse
    {
        return redirect()->route('crm.imports.show', $import->id)->withErrors($errors)->withInput();
    }
}
