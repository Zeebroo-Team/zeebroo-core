<nav class="pcat-nav" style="margin-bottom:14px;">
    <a href="{{ route('advertising-agency.clients.index') }}"
       @class(['is-active' => request()->routeIs('advertising-agency.clients.*')])>
        <i class="fa fa-users"></i> Clients
    </a>
    <a href="{{ route('advertising-agency.campaigns.index') }}"
       @class(['is-active' => request()->routeIs('advertising-agency.campaigns.*')])>
        <i class="fa fa-bullhorn"></i> Campaigns
    </a>
</nav>
