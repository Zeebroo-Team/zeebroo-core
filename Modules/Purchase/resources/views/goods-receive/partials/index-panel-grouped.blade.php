@php
    $hasGrns = $hasGrns ?? false;
    $hasActiveFilters = $hasActiveFilters ?? false;
@endphp
@if(!$hasGrns)
    <section class="pcat-inline">
        <h2 style="font-size:15px;">Record a goods receipt</h2>
        <p class="pcat-muted" style="font-size:12px;">Create a purchase order first, then record what arrived.</p>
        @if($openPurchaseOrders->isEmpty())
            <div class="pcat-banner" style="margin-top:10px;font-size:12px;">
                No purchase orders yet. <a href="{{ route('purchase.index') }}" class="pcat-link">Create a purchase order</a>.
            </div>
        @else
            <div class="grn-po-groups" style="margin-top:10px;">
                @foreach($openPurchaseOrders as $po)
                    @include('purchase::goods-receive.partials.po-group', [
                        'purchase' => $po,
                        'notes' => collect(),
                        'currency' => $currency,
                        'defaultOpen' => $loop->first,
                    ])
                @endforeach
            </div>
        @endif
    </section>
@elseif($purchaseGroups->isEmpty())
    <p class="muted" style="margin:0;font-size:12px;line-height:1.45;">
        @if($hasActiveFilters)
            No goods receive notes match your search or filters.
            <a href="{{ route('purchase.grn.index', $activeTab === 'all' ? ['view' => 'all'] : []) }}" class="pcat-link">Clear filters</a>
        @else
            No purchase orders to show.
        @endif
    </p>
@else
    <div class="grn-index-toolbar" data-grn-grouped-toolbar>
        <span class="muted" style="margin:0;font-size:12px;">
            {{ $purchaseGroups->count() }} {{ $purchaseGroups->count() === 1 ? 'PO' : 'POs' }}
        </span>
        <div class="grn-index-toolbar__actions">
            <button type="button" class="linkbtn grn-index-toolbar__btn" data-grn-expand-all style="background:transparent;border:1px solid var(--border);color:var(--text);">Expand all</button>
            <button type="button" class="linkbtn grn-index-toolbar__btn" data-grn-collapse-all style="background:transparent;border:1px solid var(--border);color:var(--text);">Collapse all</button>
        </div>
    </div>

    <div class="grn-po-groups" data-grn-po-groups>
        @foreach($purchaseGroups as $group)
            @include('purchase::goods-receive.partials.po-group', [
                'purchase' => $group['purchase'],
                'notes' => $group['notes'],
                'currency' => $currency,
                'defaultOpen' => $loop->first,
            ])
        @endforeach
    </div>
@endif
