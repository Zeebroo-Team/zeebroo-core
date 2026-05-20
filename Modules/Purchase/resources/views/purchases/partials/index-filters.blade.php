@php
    $search = $search ?? '';
    $statusFilter = $statusFilter ?? 'all';
    $supplierFilter = $supplierFilter ?? null;
    $statusTabs = $statusTabs ?? [];
    $suppliers = $suppliers ?? collect();
    $purchases = $purchases ?? collect();
    $hasActiveFilters = $search !== '' || $statusFilter !== 'all' || filled($supplierFilter);
    $filterQuery = static function (array $overrides = []) use ($search, $statusFilter, $supplierFilter) {
        $params = array_filter([
            'q' => $search !== '' ? $search : null,
            'status' => ($statusFilter !== '' && $statusFilter !== 'all') ? $statusFilter : null,
            'supplier_id' => filled($supplierFilter) ? $supplierFilter : null,
        ], fn ($value) => $value !== null && $value !== '');

        return array_filter(array_merge($params, $overrides), fn ($value) => $value !== null && $value !== '');
    };
@endphp
<style>
.purchase-index-filters{margin:0 0 14px;}
.purchase-index-search{
    display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;margin:0 0 10px;
}
.purchase-index-search__field{flex:1;min-width:min(100%,220px);margin:0;}
.purchase-index-search__field label{display:block;margin:0 0 4px;font-size:11px;font-weight:700;color:var(--muted);}
.purchase-index-search__field input,
.purchase-index-search__field select{
    width:100%;box-sizing:border-box;padding:8px 10px;font-size:13px;border-radius:8px;
    border:1px solid var(--border);background:var(--card);color:var(--text);
}
.purchase-index-search__field input:focus-visible,
.purchase-index-search__field select:focus-visible{
    outline:none;border-color:color-mix(in srgb,var(--primary) 45%,var(--border));
    box-shadow:0 0 0 2px color-mix(in srgb,var(--primary) 18%,transparent);
}
.purchase-index-search__actions{display:flex;flex-wrap:wrap;gap:6px;align-items:center;}
.purchase-index-status-tabs{
    display:flex;flex-wrap:wrap;gap:6px;margin:0 0 8px;padding:0;
    border-bottom:1px solid color-mix(in srgb,var(--border) 80%,transparent);
}
.purchase-index-status-tabs__tab{
    display:inline-flex;align-items:center;gap:6px;
    padding:8px 14px 10px;margin:0 0 -1px;
    font-size:12px;font-weight:700;color:var(--muted);text-decoration:none;
    border:1px solid transparent;border-bottom:none;border-radius:8px 8px 0 0;
    background:transparent;
}
.purchase-index-status-tabs__tab:hover{color:var(--text);border-color:color-mix(in srgb,var(--border) 70%,transparent);background:color-mix(in srgb,var(--card) 90%,transparent);}
.purchase-index-status-tabs__tab.is-active{
    color:var(--text);
    border-color:color-mix(in srgb,var(--primary) 30%,var(--border));
    background:color-mix(in srgb,var(--primary) 8%,var(--card));
}
.purchase-index-filters__meta{margin:0;font-size:12px;color:var(--muted);}
</style>

<div class="purchase-index-filters">
    <form method="get" action="{{ route('purchase.index') }}" class="purchase-index-search" role="search">
        <div class="purchase-index-search__field">
            <label for="purchase-index-q">Search</label>
            <input
                id="purchase-index-q"
                type="search"
                name="q"
                value="{{ $search }}"
                placeholder="PO #, supplier, reference…"
                autocomplete="off"
            >
        </div>
        <div class="purchase-index-search__field" style="min-width:min(100%,180px);max-width:220px;">
            <label for="purchase-index-supplier">Supplier</label>
            <select id="purchase-index-supplier" name="supplier_id" onchange="this.form.submit()">
                <option value="">All suppliers</option>
                @foreach($suppliers as $supplierRow)
                    <option value="{{ $supplierRow->id }}" @selected((int) $supplierFilter === (int) $supplierRow->id)>{{ $supplierRow->name }}</option>
                @endforeach
            </select>
        </div>
        @if($statusFilter !== 'all')
            <input type="hidden" name="status" value="{{ $statusFilter }}">
        @endif
        <div class="purchase-index-search__actions">
            <button type="submit" class="linkbtn" style="padding:8px 14px;font-size:12px;"><i class="fa fa-search" aria-hidden="true"></i> Search</button>
            @if($hasActiveFilters)
                <a href="{{ route('purchase.index') }}" class="linkbtn" style="padding:8px 14px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">Clear</a>
            @endif
        </div>
    </form>

    <nav class="purchase-index-status-tabs" aria-label="Purchase order status">
        @foreach($statusTabs as $tabKey => $tabLabel)
            @php
                $tabParams = $filterQuery(['status' => $tabKey === 'all' ? null : $tabKey]);
            @endphp
            <a
                href="{{ route('purchase.index', $tabParams) }}"
                class="purchase-index-status-tabs__tab @if($statusFilter === $tabKey) is-active @endif"
                @if($statusFilter === $tabKey) aria-current="page" @endif
            >{{ $tabLabel }}</a>
        @endforeach
    </nav>

    <p class="purchase-index-filters__meta">
        @if($hasActiveFilters)
            Showing {{ $purchases->count() }} {{ $purchases->count() === 1 ? 'match' : 'matches' }}.
        @else
            {{ $purchases->count() }} {{ $purchases->count() === 1 ? 'order' : 'orders' }}.
        @endif
    </p>
</div>
