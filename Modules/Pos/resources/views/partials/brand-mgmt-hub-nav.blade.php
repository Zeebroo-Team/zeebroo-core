<nav class="pcat-nav" aria-label="Event & staffing management navigation">
    <a href="{{ route('pos.brand-mgmt.brands.index') }}" @class(['is-active' => request()->routeIs('pos.brand-mgmt.brands.*')])><i class="fa fa-tag" style="margin-right:4px;"></i>Brands</a>
    <a href="{{ route('pos.brand-mgmt.reporters.index') }}" @class(['is-active' => request()->routeIs('pos.brand-mgmt.reporters.*')])><i class="fa fa-user-tie" style="margin-right:4px;"></i>Reporters</a>
    <a href="{{ route('pos.brand-mgmt.officers.index') }}" @class(['is-active' => request()->routeIs('pos.brand-mgmt.officers.*')])><i class="fa fa-user-shield" style="margin-right:4px;"></i>Officers</a>
    <a href="{{ route('pos.brand-mgmt.coordinators.index') }}" @class(['is-active' => request()->routeIs('pos.brand-mgmt.coordinators.*')])><i class="fa fa-people-arrows" style="margin-right:4px;"></i>Coordinators</a>
    <a href="{{ route('pos.brand-mgmt.promoters.index') }}" @class(['is-active' => request()->routeIs('pos.brand-mgmt.promoters.*')])><i class="fa fa-bullhorn" style="margin-right:4px;"></i>Promoters</a>
    <a href="{{ route('pos.brand-mgmt.promoter-positions.index') }}" @class(['is-active' => request()->routeIs('pos.brand-mgmt.promoter-positions.*')])><i class="fa fa-list" style="margin-right:4px;"></i>Positions</a>
    <a href="{{ route('pos.brand-mgmt.jobs.index') }}" @class(['is-active' => request()->routeIs('pos.brand-mgmt.jobs.*')])><i class="fa fa-briefcase" style="margin-right:4px;"></i>Jobs</a>
    <a href="{{ route('pos.brand-mgmt.agencies.index') }}" @class(['is-active' => request()->routeIs('pos.brand-mgmt.agencies.*')])><i class="fa fa-building" style="margin-right:4px;"></i>Agencies</a>
    <a href="{{ route('pos.brand-mgmt.salary-sheets.index') }}" @class(['is-active' => request()->routeIs('pos.brand-mgmt.salary-sheets.*')])><i class="fa fa-money-check-dollar" style="margin-right:4px;"></i>Salary Sheets</a>
</nav>
