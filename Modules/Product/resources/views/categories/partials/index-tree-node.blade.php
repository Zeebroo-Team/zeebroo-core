@php
    /** @var \Modules\Product\Models\ProductCategory $row */
    $depth = (int) ($depth ?? 0);
    $isRoot = $depth === 0;
    static $pcatRenderOrder = 0;
    $pcatRenderOrder++;
    $order = $pcatRenderOrder;
    $breadcrumb = app(\Modules\Product\Services\ProductCategoryService::class)->breadcrumbLabel($row);
@endphp
@if($isRoot)
<div class="pcat-block-group" data-category-id="{{ $row->id }}">
    <div class="pcat-parent-slot" data-drop-hint="Top-level category">
        @include('product::categories.partials.index-card', ['row' => $row, 'order' => $order, 'depth' => $depth, 'breadcrumb' => $breadcrumb])
    </div>
    <div class="pcat-sublist-wrap">
        <div class="pcat-sublist-head">
            <span class="pcat-sublist-head__label">
                <i class="fa fa-level-down-alt fa-rotate-90" aria-hidden="true"></i>
                Subcategories under <strong class="pcat-sublist-parent-name">{{ $row->name }}</strong>
                <span class="muted">— drop here</span>
            </span>
        </div>
        <div class="pcat-sublist" data-parent-id="{{ $row->id }}">
@else
<div class="pcat-tree-item" style="--pcat-depth: {{ $depth }}">
    @include('product::categories.partials.index-card', ['row' => $row, 'order' => $order, 'depth' => $depth, 'breadcrumb' => $breadcrumb])
    <div class="pcat-sublist-wrap pcat-sublist-wrap--nested">
        <div class="pcat-sublist" data-parent-id="{{ $row->id }}">
@endif
            @foreach($row->children as $child)
                @include('product::categories.partials.index-tree-node', ['row' => $child, 'depth' => $depth + 1])
            @endforeach
@if($isRoot)
        </div>
    </div>
</div>
@else
        </div>
    </div>
</div>
@endif
