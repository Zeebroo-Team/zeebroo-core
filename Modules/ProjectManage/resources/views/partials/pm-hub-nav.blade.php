<nav class="pcat-nav" style="margin-bottom:14px;">
    <a href="{{ route('pm.projects.index') }}"
       @class(['is-active' => request()->routeIs('pm.projects.*') || request()->routeIs('pm.tasks.*')])>
        <i class="fa fa-folder-open"></i> Projects
    </a>
    <a href="{{ route('pm.my-tasks') }}"
       @class(['is-active' => request()->routeIs('pm.my-tasks')])>
        <i class="fa fa-list-check"></i> My Tasks
    </a>
</nav>
