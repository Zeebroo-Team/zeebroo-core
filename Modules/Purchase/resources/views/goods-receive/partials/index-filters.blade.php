@php
    $search = $search ?? '';
    $paymentFilter = $paymentFilter ?? 'all';
    $supplierFilter = $supplierFilter ?? null;
    $paymentTabs = $paymentTabs ?? [];
    $suppliers = $suppliers ?? collect();
    $notes = $notes ?? collect();
    $activeTab = $activeTab ?? 'grouped';
    $hasActiveFilters = $search !== '' || $paymentFilter !== 'all' || filled($supplierFilter);
    $filterQuery = static function (array $overrides = []) use ($search, $paymentFilter, $supplierFilter, $activeTab) {
        $params = array_filter([
            'view' => $activeTab !== 'grouped' ? $activeTab : null,
            'q' => $search !== '' ? $search : null,
            'payment' => ($paymentFilter !== '' && $paymentFilter !== 'all') ? $paymentFilter : null,
            'supplier_id' => filled($supplierFilter) ? $supplierFilter : null,
        ], fn ($value) => $value !== null && $value !== '');

        return array_filter(array_merge($params, $overrides), fn ($value) => $value !== null && $value !== '');
    };
@endphp
<style>
.grn-index-filters{margin:0 0 14px;}
.grn-index-search{
    display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;margin:0 0 10px;
}
.grn-index-search__field{flex:1;min-width:min(100%,220px);margin:0;}
.grn-index-search__field label{display:block;margin:0 0 4px;font-size:11px;font-weight:700;color:var(--muted);}
.grn-index-search__field input,
.grn-index-search__field select{
    width:100%;box-sizing:border-box;padding:8px 10px;font-size:13px;border-radius:8px;
    border:1px solid var(--border);background:var(--card);color:var(--text);
}
.grn-index-search__field input:focus-visible,
.grn-index-search__field select:focus-visible{
    outline:none;border-color:color-mix(in srgb,var(--primary) 45%,var(--border));
    box-shadow:0 0 0 2px color-mix(in srgb,var(--primary) 18%,transparent);
}
.grn-index-search__actions{display:flex;flex-wrap:wrap;gap:6px;align-items:center;}
.grn-index-payment-tabs{
    display:flex;flex-wrap:wrap;gap:6px;margin:0 0 8px;padding:0;
    border-bottom:1px solid color-mix(in srgb,var(--border) 80%,transparent);
}
.grn-index-payment-tabs__tab{
    display:inline-flex;align-items:center;gap:6px;
    padding:8px 14px 10px;margin:0 0 -1px;
    font-size:12px;font-weight:700;color:var(--muted);text-decoration:none;
    border:1px solid transparent;border-bottom:none;border-radius:8px 8px 0 0;
    background:transparent;
}
.grn-index-payment-tabs__tab:hover{color:var(--text);border-color:color-mix(in srgb,var(--border) 70%,transparent);background:color-mix(in srgb,var(--card) 90%,transparent);}
.grn-index-payment-tabs__tab.is-active{
    color:var(--text);
    border-color:color-mix(in srgb,var(--primary) 30%,var(--border));
    background:color-mix(in srgb,var(--primary) 8%,var(--card));
}
.grn-index-filters__meta{margin:0;font-size:12px;color:var(--muted);}
</style>

<div class="grn-index-filters">
    <form method="get" action="{{ route('purchase.grn.index') }}" class="grn-index-search" role="search">
        @if($activeTab === 'all')
            <input type="hidden" name="view" value="all">
        @endif
        <div class="grn-index-search__field">
            <label for="grn-index-q">Search</label>
            <input
                id="grn-index-q"
                type="search"
                name="q"
                value="{{ $search }}"
                placeholder="GRN #, PO #, supplier, reference…"
                autocomplete="off"
            >
        </div>
        <div class="grn-index-search__field" style="min-width:min(100%,180px);max-width:220px;">
            <label for="grn-index-supplier">Supplier</label>
            <select id="grn-index-supplier" name="supplier_id" onchange="this.form.submit()">
                <option value="">All suppliers</option>
                @foreach($suppliers as $supplierRow)
                    <option value="{{ $supplierRow->id }}" @selected((int) $supplierFilter === (int) $supplierRow->id)>{{ $supplierRow->name }}</option>
                @endforeach
            </select>
        </div>
        @if($paymentFilter !== 'all')
            <input type="hidden" name="payment" value="{{ $paymentFilter }}">
        @endif
        <div class="grn-index-search__actions">
            <button type="submit" class="linkbtn" style="padding:8px 14px;font-size:12px;"><i class="fa fa-search" aria-hidden="true"></i> Search</button>
            @if($hasActiveFilters)
                <a href="{{ route('purchase.grn.index', $activeTab === 'all' ? ['view' => 'all'] : []) }}" class="linkbtn" style="padding:8px 14px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">Clear</a>
            @endif
        </div>
    </form>

    <nav class="grn-index-payment-tabs" aria-label="GRN payment status">
        @foreach($paymentTabs as $tabKey => $tabLabel)
            @php
                $tabParams = $filterQuery(['payment' => $tabKey === 'all' ? null : $tabKey]);
            @endphp
            <a
                href="{{ route('purchase.grn.index', $tabParams) }}"
                class="grn-index-payment-tabs__tab @if($paymentFilter === $tabKey) is-active @endif"
                @if($paymentFilter === $tabKey) aria-current="page" @endif
            >{{ $tabLabel }}</a>
        @endforeach
    </nav>

    <p class="grn-index-filters__meta">
        @if($hasActiveFilters)
            Showing {{ $notes->count() }} {{ $notes->count() === 1 ? 'match' : 'matches' }}.
        @else
            {{ $notes->count() }} {{ $notes->count() === 1 ? 'receipt' : 'receipts' }}.
        @endif
    </p>
</div>
