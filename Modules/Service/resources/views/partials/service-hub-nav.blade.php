<nav class="pcat-nav" style="margin-bottom:14px;">
    <a href="{{ route('service.catalog.index') }}" @class(['is-active' => request()->routeIs('service.catalog.*')])>
        <i class="fa fa-list-check"></i> Catalog
    </a>
    <a href="{{ route('service.requests.index') }}" @class(['is-active' => request()->routeIs('service.requests.*')])>
        <i class="fa fa-inbox"></i> Requests
    </a>
</nav>
