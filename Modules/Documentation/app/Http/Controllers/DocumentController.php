<?php

namespace Modules\Documentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Documentation\Models\Document;
use Modules\Documentation\Models\DocumentCategory;
use Modules\Documentation\Services\DocumentService;

class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentService $documentService,
    ) {}

    public function index(Request $request): View
    {
        $business      = $this->resolveBusinessOptional($request);
        $publishedOnly = $business === null;
        $search        = trim((string) $request->query('q', ''));

        $categories = $this->documentService->categoriesWithCount($publishedOnly);
        $totalDocs  = $categories->sum('documents_count');

        // Search always over published docs (platform docs are public).
        $searchResults = filled($search)
            ? $this->documentService->listPublished($search)
            : null;

        return view('documentation::documents.index', [
            'business'      => $business,
            'categories'    => $categories,
            'totalDocs'     => $totalDocs,
            'search'        => $search,
            'searchResults' => $searchResults,
        ]);
    }

    public function category(Request $request, DocumentCategory $category): View
    {
        $business = $this->resolveBusinessOptional($request);
        $docSlug  = (string) $request->query('doc', '');

        // Always show all published docs — platform documentation is public.
        // Business owners additionally see their own drafts mixed in.
        $sidebarDocs = $this->documentService->sidebarDocsForCategory($category->id, $business);

        // Active document: requested slug or first in list
        $activeDocument = null;
        if ($sidebarDocs->isNotEmpty()) {
            $stub = filled($docSlug)
                ? ($sidebarDocs->firstWhere('slug', $docSlug) ?? $sidebarDocs->first())
                : $sidebarDocs->first();

            $activeDocument = Document::with(['author', 'category'])->find($stub->id);
        }

        return view('documentation::documents.category', [
            'business'       => $business,
            'category'       => $category,
            'sidebarDocs'    => $sidebarDocs,
            'statusFilter'   => 'all',
            'activeDocument' => $activeDocument,
        ]);
    }

    public function show(Request $request, Document $document): View
    {
        $business = $this->resolveBusinessOptional($request);

        // Published docs are public — any visitor (guest or logged-in) may read them.
        // Drafts are only visible to the owning business.
        if ($document->status !== Document::STATUS_PUBLISHED) {
            abort_if(
                $business === null || $document->business_id !== $business->id,
                403,
            );
        }

        $document->load(['author', 'category']);

        $related = $document->category
            ? $this->documentService->relatedDocuments($document, $business)
            : collect();

        return view('documentation::documents.show', [
            'business' => $business,
            'document' => $document,
            'related'  => $related,
        ]);
    }

    private function resolveBusinessOptional(Request $request): ?Business
    {
        return $request->user()?->businesses()->first();
    }
}
