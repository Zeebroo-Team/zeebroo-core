<nav class="pcat-nav" style="margin-bottom:14px;">
    <a href="{{ route('crm.projects.index') }}" @class(['is-active' => request()->routeIs('crm.projects.*') || request()->routeIs('crm.leads.*')])>
        <i class="fa fa-diagram-project"></i> Projects
    </a>
    <a href="{{ route('crm.contacts.index') }}" @class(['is-active' => request()->routeIs('crm.contacts.*')])>
        <i class="fa fa-address-book"></i> Contacts
    </a>
    <a href="{{ route('crm.tasks.index') }}" @class(['is-active' => request()->routeIs('crm.tasks.*')])>
        <i class="fa fa-list-check"></i> Tasks
    </a>
</nav>
