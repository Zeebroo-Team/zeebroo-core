<nav class="pcat-nav" style="margin-bottom:14px;">
    <a href="{{ route('sales.quotations.index') }}" @class(['is-active' => request()->routeIs('sales.quotations.*')])>
        <i class="fa fa-file-lines"></i> Quotations
    </a>
    <a href="{{ route('sales.invoices.index') }}" @class(['is-active' => request()->routeIs('sales.invoices.*')])>
        <i class="fa fa-file-invoice"></i> Invoices
    </a>
</nav>
