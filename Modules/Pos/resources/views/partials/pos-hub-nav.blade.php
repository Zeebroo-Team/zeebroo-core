<nav class="pcat-nav" aria-label="POS navigation">
    <a href="{{ route('pos.index') }}" @class(['is-active' => request()->routeIs('pos.index')])><i class="fa fa-gauge-high" style="margin-right:4px;"></i>Sales hub</a>
    <a href="{{ route('pos.online') }}" @class(['is-active' => request()->routeIs('pos.online')])><i class="fa fa-store" style="margin-right:4px;"></i>Online POS</a>
    <a href="{{ route('pos.register') }}" @class(['is-active' => request()->routeIs('pos.register')])><i class="fa fa-cash-register" style="margin-right:4px;"></i>Register</a>
    <a href="{{ route('pos.sales.index') }}" @class(['is-active' => request()->routeIs('pos.sales.*')])><i class="fa fa-receipt" style="margin-right:4px;"></i>Sales history</a>
    <a href="{{ route('pos.customers.index') }}" @class(['is-active' => request()->routeIs('pos.customers.*')])><i class="fa fa-users" style="margin-right:4px;"></i>Customers</a>
</nav>
