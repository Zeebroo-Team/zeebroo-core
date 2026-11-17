<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\PosCashier;
use Modules\Pos\Services\Backup\BackupNotFound;
use Modules\Pos\Services\Backup\PosBackupExportService;
use Modules\Pos\Services\Backup\PosBackupImportService;
use Modules\Pos\Services\Backup\PosBackupRegistry;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Desktop "Database Backup & Import" modal (profile menu).
 * Owner / admin members only — a backup holds the whole business's data.
 */
class PosBackupApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly PosBackupExportService $exporter,
        private readonly PosBackupImportService $importer,
        private readonly PosBackupRegistry $registry,
    ) {
    }

    public function summary(Request $request): JsonResponse
    {
        $business = $this->ownerBusiness($request);

        return response()->json(['data' => [
            'groups' => $this->exporter->summary($business),
            'active_import' => $this->importer->active($business),
            'limits' => [
                'max_upload_bytes' => PosBackupImportService::MAX_UPLOAD_BYTES,
                'chunk_bytes' => min(PosBackupImportService::MAX_CHUNK_BYTES, $this->postMaxBytes()),
            ],
        ]]);
    }

    public function export(Request $request): JsonResponse
    {
        $business = $this->ownerBusiness($request);
        $validated = $request->validate([
            'full' => ['sometimes', 'boolean'],
            'groups' => ['required_without:full', 'array'],
            'groups.*' => ['string', 'in:'.implode(',', $this->registry->groupKeys())],
            'app_version' => ['sometimes', 'nullable', 'string', 'max:40'],
        ]);
        $groups = ($validated['full'] ?? false) ? $this->registry->groupKeys() : ($validated['groups'] ?? []);

        return $this->guard(fn () => response()->json(['data' => $this->exporter->export($business, $groups, (string) ($validated['app_version'] ?? ''))], 201));
    }

    public function download(Request $request, string $token): BinaryFileResponse|JsonResponse
    {
        $business = $this->ownerBusiness($request);
        $path = $this->exporter->exportPath($business, $token);
        if (! $path) {
            return response()->json(['message' => 'Backup file not found or expired. Export it again.'], 404);
        }

        return response()->download($path, (string) $request->query('filename', 'zeebroo-backup.zip'), ['Content-Type' => 'application/zip']);
    }

    public function startImport(Request $request): JsonResponse
    {
        $business = $this->ownerBusiness($request);
        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
        ]);

        return $this->guard(fn () => response()->json(['data' => $this->importer->start($business, (int) $request->user()->id, $validated['filename'], (int) $validated['size'])], 201));
    }

    public function uploadChunk(Request $request, string $import): JsonResponse
    {
        $business = $this->ownerBusiness($request);
        $index = (int) $request->query('index', -1);

        return $this->guard(fn () => response()->json(['data' => $this->importer->appendChunk($business, $import, $index, $request->getContent(true))]));
    }

    public function validateImport(Request $request, string $import): JsonResponse
    {
        $business = $this->ownerBusiness($request);

        return $this->guard(fn () => response()->json(['data' => $this->importer->validate($business, $import)]));
    }

    public function runImport(Request $request, string $import): JsonResponse
    {
        $business = $this->ownerBusiness($request);

        return $this->guard(fn () => response()->json(['data' => $this->importer->run($business, $import)]));
    }

    public function showImport(Request $request, string $import): JsonResponse
    {
        $business = $this->ownerBusiness($request);

        return $this->guard(fn () => response()->json(['data' => $this->importer->get($business, $import)]));
    }

    public function cancelImport(Request $request, string $import): JsonResponse
    {
        $business = $this->ownerBusiness($request);

        return $this->guard(function () use ($business, $import) {
            $this->importer->cancel($business, $import);

            return response()->json(['message' => 'Import cancelled.']);
        });
    }

    public function downloadSafety(Request $request, string $import): BinaryFileResponse|JsonResponse
    {
        $business = $this->ownerBusiness($request);

        return $this->guard(function () use ($business, $import) {
            $file = $this->importer->safetyPath($business, $import);
            if (! $file) {
                return response()->json(['message' => 'No safety backup is available for this import.'], 404);
            }

            return response()->download($file['path'], $file['filename'], ['Content-Type' => 'application/zip']);
        });
    }

    private function ownerBusiness(Request $request): Business
    {
        $business = $this->businessOrAbort($request);
        $member = $this->resolveMember($request, $business);
        if ($request->user() instanceof PosCashier || ($member !== null && $member->role !== 'admin')) {
            throw new HttpResponseException(response()->json(['message' => 'Only the business owner or an admin can back up or import data.'], 403));
        }

        return $business;
    }

    private function guard(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (BackupNotFound $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\PDOException $e) {
            throw $e;   // SQL errors are not user messages — let the handler log them
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function postMaxBytes(): int
    {
        $v = trim((string) ini_get('post_max_size'));
        if ($v === '' || $v === '0') {
            return PHP_INT_MAX;
        }
        $n = (int) $v;
        $bytes = match (strtolower(substr($v, -1))) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => $n,
        };

        // Leave headroom for request overhead.
        return max(256 * 1024, (int) ($bytes * 0.9));
    }
}
