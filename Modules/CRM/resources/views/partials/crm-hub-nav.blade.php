<nav class="pcat-nav" style="margin-bottom:14px;">
    <a href="{{ route('crm.overview') }}" @class(['is-active' => request()->routeIs('crm.overview')])>
        <i class="fa fa-house"></i> Overview
    </a>
    <a href="{{ route('crm.projects.index') }}" @class(['is-active' => request()->routeIs('crm.projects.*') || request()->routeIs('crm.leads.*')])>
        <i class="fa fa-diagram-project"></i> Projects
    </a>
    <a href="{{ route('crm.contacts.index') }}" @class(['is-active' => request()->routeIs('crm.contacts.*')])>
        <i class="fa fa-address-book"></i> Contacts
    </a>
    <a href="{{ route('crm.tasks.index') }}" @class(['is-active' => request()->routeIs('crm.tasks.*')])>
        <i class="fa fa-list-check"></i> Tasks
    </a>
    @php
        $crmNavBusiness = $business ?? null;
        $crmNavAccess   = $crmNavBusiness ? app(\Modules\CRM\Services\CrmPermissionService::class)->accessFor($crmNavBusiness, auth()->user()) : null;
    @endphp
    @if($crmNavAccess && $crmNavAccess->usesInbox())
        <a href="{{ route('crm.inbox.index') }}" @class(['is-active' => request()->routeIs('crm.inbox.*')])>
            <i class="fa fa-inbox"></i> Inbox
        </a>
    @endif
    @if($crmNavAccess && \Modules\CRM\Http\Controllers\LeadImportController::canImport($crmNavAccess))
        <a href="{{ route('crm.imports.index') }}" @class(['is-active' => request()->routeIs('crm.imports.*')])>
            <i class="fa fa-file-import"></i> Import Leads
        </a>
    @endif
    @if($crmNavBusiness && app(\Modules\CRM\Services\CrmPermissionService::class)->isAdmin($crmNavBusiness, (int) auth()->id()))
        <a href="{{ route('crm.org.index') }}" @class(['is-active' => request()->routeIs('crm.org.*')])>
            <i class="fa fa-sitemap"></i> Org Structure
        </a>
    @endif
    @if($crmNavAccess && app(\Modules\CRM\Policies\LeadPolicy::class)->canViewAuditLog($crmNavAccess))
        <a href="{{ route('crm.audit.index') }}" @class(['is-active' => request()->routeIs('crm.audit.*')])>
            <i class="fa fa-shield-halved"></i> Audit Log
        </a>
    @endif
</nav>
