<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\CRM\Http\Controllers\Concerns\ResolvesCrmBusiness;
use Modules\CRM\Services\CrmAccess;
use Modules\CRM\Services\CustomerImportService;
use Modules\CRM\Support\LeadImportReader;
use Symfony\Component\HttpFoundation\Response;

/**
 * Customer import on the web: upload a CSV / XLSX → preview every row with the duplicate
 * choice (skip or update) → run → download the rows that were not imported.
 *
 * The uploaded file waits on the local disk; the session remembers it (and the error rows
 * after the run) under a random token, so only the person who uploaded it can continue.
 * The desktop runs the same checks through POST /api/pos/customers/import.
 */
class CustomerImportController extends Controller
{
    use ResolvesCrmBusiness;

    private const SESSION_KEY = 'crm_customer_imports';

    public function __construct(private readonly CustomerImportService $imports) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireImporter($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return view('crm::contacts.import', [
            'business'   => $business,
            'step'       => 'upload',
            'options'    => $this->imports->options(),
            'extensions' => LeadImportReader::EXTENSIONS,
        ]);
    }

    public function template(Request $request): Response
    {
        $business = $this->requireImporter($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return $this->csvResponse($this->imports->templateCsv(), 'customer-import-template.csv');
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
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, LeadImportReader::EXTENSIONS, true)) {
            return $this->backToIndex(['file' => 'Upload a .csv, .xlsx or .xls file.']);
        }
        if ($file->getSize() > 10 * 1024 * 1024) {
            return $this->backToIndex(['file' => 'The file is larger than 10 MB. Split it into smaller files.']);
        }

        try {
            $table = $this->imports->readFile($file->getRealPath(), $extension);
        } catch (ValidationException $e) {
            return $this->backToIndex($e->errors());
        }

        $token = (string) Str::uuid();
        $path  = $file->storeAs('crm/customer-imports/' . $business->id, $token . '.' . $extension, 'local');

        $request->session()->put(self::SESSION_KEY . '.' . $token, [
            'business_id' => $business->id,
            'path'        => $path,
            'file_name'   => mb_substr($file->getClientOriginalName(), 0, 255),
            'rows'        => count($table['rows']),
        ]);

        return redirect()->route('crm.contacts.import.preview', $token);
    }

    /** Every row with what importing it would do; the duplicate choice re-checks the rows. */
    public function preview(Request $request, string $token): View|RedirectResponse
    {
        $business = $this->requireImporter($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $upload = $this->upload($request, $business, $token);
        if (isset($upload['result'])) {
            return redirect()->route('crm.contacts.import.result', $token);
        }

        $mode = $this->duplicateMode($request->query('duplicates'));

        try {
            $table = $this->imports->readFile(Storage::disk('local')->path($upload['path']), pathinfo($upload['path'], PATHINFO_EXTENSION));
        } catch (ValidationException $e) {
            $this->forget($request, $token);

            return $this->backToIndex($e->errors());
        }

        return view('crm::contacts.import', [
            'business'   => $business,
            'step'       => 'preview',
            'token'      => $token,
            'upload'     => $upload,
            'mode'       => $mode,
            'preview'    => $this->imports->preview($business, $table['rows'], $mode),
            'options'    => $this->imports->options(),
        ]);
    }

    public function run(Request $request, string $token): RedirectResponse
    {
        $business = $this->requireImporter($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $upload = $this->upload($request, $business, $token);
        if (isset($upload['result'])) {
            return redirect()->route('crm.contacts.import.result', $token);
        }

        $mode = $this->duplicateMode($request->input('duplicates'));
        $disk = Storage::disk('local');

        try {
            $table = $this->imports->readFile($disk->path($upload['path']), pathinfo($upload['path'], PATHINFO_EXTENSION));
        } catch (ValidationException $e) {
            $this->forget($request, $token);

            return $this->backToIndex($e->errors());
        }

        $result = $this->imports->import($business, $table['rows'], $mode);

        // Keep the rows that did not go in for the download, then drop the upload
        $notImported = array_values(array_filter($result['rows'], fn ($r) => in_array($r['status'], ['skip', 'error'], true)));
        $errorsPath  = null;
        if ($notImported !== []) {
            $errorsPath = 'crm/customer-imports/' . $business->id . '/' . $token . '-not-imported.csv';
            $disk->put($errorsPath, $this->imports->errorRowsCsv($table['headers'], $notImported));
        }
        $disk->delete($upload['path']);

        $request->session()->put(self::SESSION_KEY . '.' . $token, $upload + [
            'result' => ['mode' => $mode, 'totals' => $result['totals'], 'errors_path' => $errorsPath],
        ]);

        return redirect()->route('crm.contacts.import.result', $token);
    }

    public function result(Request $request, string $token): View|RedirectResponse
    {
        $business = $this->requireImporter($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $upload = $this->upload($request, $business, $token);
        if (! isset($upload['result'])) {
            return redirect()->route('crm.contacts.import.preview', $token);
        }

        return view('crm::contacts.import', [
            'business' => $business,
            'step'     => 'result',
            'token'    => $token,
            'upload'   => $upload,
            'options'  => $this->imports->options(),
        ]);
    }

    /** The rows that were skipped or failed, as in the file plus the reason. */
    public function errors(Request $request, string $token): Response|RedirectResponse
    {
        $business = $this->requireImporter($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $path = $this->upload($request, $business, $token)['result']['errors_path'] ?? null;
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return $this->csvResponse(Storage::disk('local')->get($path), 'customers-not-imported.csv');
    }

    public function destroy(Request $request, string $token): RedirectResponse
    {
        $business = $this->requireImporter($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->upload($request, $business, $token);
        $this->forget($request, $token);

        return redirect()->route('crm.contacts.import.index')->with('status', 'Upload discarded.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /** Whether the contacts pages show "Import customers". */
    public static function canImport(CrmAccess $access): bool
    {
        return $access->hasCrm() && $access->can('contacts_view') && ! $access->isReadOnly();
    }

    private function requireImporter(Request $request): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless(self::canImport($this->crmAccess($request, $business)), 403);

        return $business;
    }

    /** This user's upload for the token, in this business (404 otherwise). */
    private function upload(Request $request, Business $business, string $token): array
    {
        $upload = Str::isUuid($token) ? $request->session()->get(self::SESSION_KEY . '.' . $token) : null;
        abort_unless(is_array($upload) && (int) $upload['business_id'] === (int) $business->id, 404);

        return $upload;
    }

    private function forget(Request $request, string $token): void
    {
        $upload = $request->session()->pull(self::SESSION_KEY . '.' . $token);
        if (is_array($upload)) {
            Storage::disk('local')->delete(array_filter([$upload['path'] ?? null, $upload['result']['errors_path'] ?? null]));
        }
    }

    private function duplicateMode(mixed $mode): string
    {
        return is_string($mode) && array_key_exists($mode, CustomerImportService::DUPLICATE_MODES) ? $mode : 'skip';
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
        return redirect()->route('crm.contacts.import.index')->withErrors($errors);
    }
}
