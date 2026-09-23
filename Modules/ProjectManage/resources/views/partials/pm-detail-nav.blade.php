@php
    $pmDetailTabs = [
        ['route' => 'pm.projects.show',        'label' => 'Dashboard',  'icon' => 'fa-gauge',           'active' => request()->routeIs('pm.projects.show')],
        ['route' => 'pm.projects.edit',         'label' => 'Assignment', 'icon' => 'fa-diagram-project', 'active' => request()->routeIs('pm.projects.edit')],
        ['route' => 'pm.projects.tasks.board',  'label' => 'Board',      'icon' => 'fa-table-columns',   'active' => request()->routeIs('pm.projects.tasks.board')],
        ['route' => 'pm.projects.tasks.index',  'label' => 'Task',       'icon' => 'fa-list-check',      'active' => request()->routeIs('pm.projects.tasks.index')],
        ['route' => 'pm.projects.tasks.mine',   'label' => 'My Task',    'icon' => 'fa-user-check',      'active' => request()->routeIs('pm.projects.tasks.mine')],
    ];
@endphp
<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;">
    @foreach($pmDetailTabs as $tab)
        <a href="{{ route($tab['route'], $project) }}"
           style="padding:5px 13px;border-radius:999px;font-size:12px;font-weight:600;text-decoration:none;border:1px solid var(--border);display:inline-flex;align-items:center;gap:5px;
                  {{ $tab['active'] ? 'background:var(--primary);color:#fff;border-color:var(--primary);' : 'background:transparent;color:var(--muted);' }}">
            <i class="fa {{ $tab['icon'] }}"></i> {{ $tab['label'] }}
        </a>
    @endforeach
</div>
