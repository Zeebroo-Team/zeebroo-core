<nav class="pcat-nav" style="margin-bottom:14px;">
    <a href="{{ route('service.catalog.index') }}" @class(['is-active' => request()->routeIs('service.catalog.*')])>
        <i class="fa fa-list-check"></i> Services
    </a>
    <a href="{{ route('service.categories.index') }}" @class(['is-active' => request()->routeIs('service.categories.*')])>
        <i class="fa fa-folder-tree"></i> Categories
    </a>
    <a href="{{ route('service.bundles.index') }}" @class(['is-active' => request()->routeIs('service.bundles.*')])>
        <i class="fa fa-layer-group"></i> Bulk Services
    </a>
    <a href="{{ route('service.requests.index') }}" @class(['is-active' => request()->routeIs('service.requests.*')])>
        <i class="fa fa-inbox"></i> Requests
    </a>
</nav>
