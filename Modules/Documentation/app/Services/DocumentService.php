<?php

namespace Modules\Documentation\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Business\Models\Business;
use Modules\Documentation\Models\Document;
use Modules\Documentation\Models\DocumentCategory;

class DocumentService
{
    public function allCategories(): Collection
    {
        return DocumentCategory::orderBy('sort_order')->get();
    }

    public function categoriesWithCount(bool $publishedOnly = false): Collection
    {
        return DocumentCategory::orderBy('sort_order')
            ->withCount([
                'documents' => fn ($q) => $publishedOnly
                    ? $q->where('status', Document::STATUS_PUBLISHED)
                    : $q,
            ])
            ->get();
    }

    public function listForBusiness(
        Business $business,
        ?string $search = null,
        ?int $categoryId = null,
        ?string $status = null,
    ): LengthAwarePaginator {
        $query = Document::query()
            ->where('business_id', $business->id)
            ->with(['author', 'category'])
            ->latest('updated_at');

        if (filled($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($categoryId) {
            $query->where('document_category_id', $categoryId);
        }

        if (filled($status) && $status !== 'all') {
            $query->where('status', $status);
        }

        return $query->paginate(20)->withQueryString();
    }

    public function listPublished(
        ?string $search = null,
        ?int $categoryId = null,
    ): LengthAwarePaginator {
        $query = Document::query()
            ->where('status', Document::STATUS_PUBLISHED)
            ->with(['author', 'category'])
            ->latest('updated_at');

        if (filled($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($categoryId) {
            $query->where('document_category_id', $categoryId);
        }

        return $query->paginate(20)->withQueryString();
    }

    /**
     * All published docs for the sidebar, plus the owning business's drafts if a
     * business owner is viewing — so platform docs stay visible to all users.
     */
    public function sidebarDocsForCategory(int $categoryId, ?Business $business): Collection
    {
        return Document::query()
            ->where('document_category_id', $categoryId)
            ->where(function ($q) use ($business) {
                $q->where('status', Document::STATUS_PUBLISHED);
                if ($business) {
                    $q->orWhere(fn ($inner) => $inner
                        ->where('business_id', $business->id)
                        ->where('status', Document::STATUS_DRAFT));
                }
            })
            ->orderBy('title')
            ->get(['id', 'title', 'slug', 'status', 'business_id', 'updated_at']);
    }

    public function sidebarDocsPublished(int $categoryId): Collection
    {
        return Document::query()
            ->where('status', Document::STATUS_PUBLISHED)
            ->where('document_category_id', $categoryId)
            ->orderBy('title')
            ->get(['id', 'title', 'slug', 'status', 'updated_at']);
    }

    public function relatedDocuments(Document $document, ?Business $business): Collection
    {
        return Document::query()
            ->where('document_category_id', $document->document_category_id)
            ->where('id', '!=', $document->id)
            ->when($business === null, fn (Builder $q) => $q->where('status', Document::STATUS_PUBLISHED))
            ->when($business !== null, fn (Builder $q) => $q->where('business_id', $business->id))
            ->latest('updated_at')
            ->limit(8)
            ->get(['id', 'title', 'slug', 'status', 'updated_at']);
    }

    public function businessHasDocuments(Business $business): bool
    {
        return Document::where('business_id', $business->id)->exists();
    }
}
