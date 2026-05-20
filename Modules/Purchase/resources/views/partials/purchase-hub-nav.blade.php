<nav class="pcat-nav" aria-label="Purchase navigation">
    <a href="{{ route('purchase.index') }}" @class(['is-active' => request()->routeIs('purchase.index', 'purchase.store', 'purchase.show', 'purchase.edit', 'purchase.update', 'purchase.place-order', 'purchase.receive', 'purchase.cancel', 'purchase.destroy')])><i class="fa fa-file-invoice" style="margin-right:4px;"></i>Purchase orders</a>
    <a href="{{ route('purchase.grn.index') }}" @class(['is-active' => request()->routeIs('purchase.grn.*')])><i class="fa fa-truck-ramp-box" style="margin-right:4px;"></i>Goods receive</a>
    <a href="{{ route('purchase.suppliers.index') }}" @class(['is-active' => request()->routeIs('purchase.suppliers.*')])><i class="fa fa-truck-field" style="margin-right:4px;"></i>Suppliers</a>
    @if(Route::has('purchase.cheques.index'))
        <a href="{{ route('purchase.cheques.index') }}" @class(['is-active' => request()->routeIs('purchase.cheques.*')])><i class="fa fa-money-check" style="margin-right:4px;"></i>Cheques</a>
    @endif
</nav>
