<nav class="pcat-nav" style="margin-bottom:14px;">
    <a href="{{ route('sales.quotations.index') }}" @class(['is-active' => request()->routeIs('sales.quotations.*')])>
        <i class="fa fa-file-lines"></i> Quotations
    </a>
    <a href="{{ route('sales.invoices.index') }}" @class(['is-active' => request()->routeIs('sales.invoices.*')])>
        <i class="fa fa-file-invoice"></i> Invoices
    </a>
    <a href="{{ route('sales.orders.index') }}" @class(['is-active' => request()->routeIs('sales.orders.*')])>
        <i class="fa fa-cart-shopping"></i> Orders
    </a>
    <a href="{{ route('sales.invoice-setup.edit') }}" @class(['is-active' => request()->routeIs('sales.invoice-setup.*')])>
        <i class="fa fa-swatchbook"></i> Invoice Setup
    </a>
</nav>
