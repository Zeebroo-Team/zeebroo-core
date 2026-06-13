<?php

namespace Modules\Documentation\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;
use Modules\Documentation\Models\Document;
use Modules\Documentation\Services\DocumentService;

class DocumentApiController extends Controller
{
    public function __construct(
        private readonly DocumentService $documentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->resolveBusiness($request);

        if ($business) {
            $documents = $this->documentService->listForBusiness(
                $business,
                $request->query('q'),
                $request->query('category'),
                $request->query('status'),
            );
        } else {
            $documents = $this->documentService->listPublished(
                $request->query('q'),
                $request->query('category'),
            );
        }

        return response()->json($documents);
    }

    public function show(Request $request, Document $document): JsonResponse
    {
        $business = $this->resolveBusiness($request);

        if ($business) {
            if ($document->business_id !== $business->id) {
                return response()->json(['message' => 'Not found.'], 404);
            }
        } else {
            if ($document->status !== Document::STATUS_PUBLISHED) {
                return response()->json(['message' => 'Not found.'], 404);
            }
        }

        return response()->json(['data' => $document->load('author')]);
    }

    private function resolveBusiness(Request $request): ?Business
    {
        return $request->user()?->businesses()->first();
    }
}
