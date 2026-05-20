@php
    /** @var \Modules\Product\Models\ProductCategory $row */
    $depth = (int) ($depth ?? 0);
    $isSub = $depth > 0;
    $breadcrumb = $breadcrumb ?? $row->name;
@endphp
<article
    class="pcat-card {{ $isSub ? 'pcat-card--sub' : 'pcat-card--parent' }}"
    style="--pcat-depth: {{ $depth }}"
    data-category-id="{{ $row->id }}"
    data-parent-id="{{ $row->parent_id ?? '' }}"
    data-depth="{{ $depth }}"
    data-children-count="{{ (int) $row->children_count }}"
>
    <span class="pcat-drag-handle" title="Drag to move or reorder" aria-hidden="true"><i class="fa fa-grip-vertical"></i></span>
    <div class="pcat-card__body">
        <div class="pcat-card__head">
            @if($isSub)
                <span class="pcat-subcat-indent" aria-hidden="true">↳</span>
            @endif
            <h3 class="pcat-card__title">{{ $row->name }}</h3>
        </div>
        @if($row->description)
            <p class="pcat-card__desc muted">{{ \Illuminate\Support\Str::limit($row->description, 72) }}</p>
        @endif
        <div class="pcat-card__meta">
            @if($isSub)
                <span class="muted" title="{{ $breadcrumb }}">{{ \Illuminate\Support\Str::limit($breadcrumb, 48) }}</span>
            @else
                <span class="pcat-badge">Top</span>
            @endif
            <span class="muted">{{ (int) $row->products_count }} prod.</span>
            <span class="muted pcat-order-cell">#{{ $order }}</span>
            @if($row->is_active)
                <span class="pcat-badge pcat-badge--on">Active</span>
            @else
                <span class="pcat-badge pcat-badge--off">Inactive</span>
            @endif
        </div>
    </div>
    <div class="pcat-card__actions">
        <button type="button" class="pcat-link pcat-add-sub" data-parent-id="{{ $row->id }}" style="border:none;background:transparent;cursor:pointer;padding:0;font:inherit;">
            <i class="fa fa-sitemap"></i> Sub
        </button>
        <a class="pcat-link" href="{{ route('product.categories.edit', $row) }}"><i class="fa fa-pen"></i> Edit</a>
        @if((int) $row->products_count > 0)
            <span class="muted pcat-card__note">In use</span>
        @elseif((int) $row->children_count > 0)
            <span class="muted pcat-card__note">Has subs</span>
        @else
            <form method="post" action="{{ route('product.categories.destroy', $row) }}" style="margin:0;" onsubmit="return confirm('Delete this category?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="pcat-btn-del" title="Delete"><i class="fa fa-trash-can"></i></button>
            </form>
        @endif
    </div>
</article>
