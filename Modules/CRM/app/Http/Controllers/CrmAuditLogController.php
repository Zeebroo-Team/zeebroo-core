<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\CRM\Http\Controllers\Concerns\ResolvesCrmBusiness;
use Modules\CRM\Models\CrmAuditLog;
use Modules\CRM\Services\CrmAuditService;

/** Admin-only viewer for crm_audit_logs (the row-level CRM audit trail). */
class CrmAuditLogController extends Controller
{
    use ResolvesCrmBusiness;

    public function __construct(private readonly CrmAuditService $audit) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }
        abort_unless($this->leadPolicy()->canViewAuditLog($this->crmAccess($request, $business)), 403);

        $filters = $request->validate([
            'subject'    => ['nullable', Rule::in(array_keys(CrmAuditLog::SUBJECTS))],
            'subject_id' => ['nullable', 'integer'],
            'event'      => ['nullable', Rule::in(array_keys(CrmAuditLog::events()))],
            'user_id'    => ['nullable', 'integer'],
            'from'       => ['nullable', 'date'],
            'to'         => ['nullable', 'date'],
        ]);

        $page = $this->audit->list($business, $filters);

        return view('crm::audit.index', [
            'business' => $business,
            'filters'  => $filters,
            'logs'     => $page,
            'rows'     => collect($page->items())->map(fn (CrmAuditLog $log) => $this->audit->present($log)),
            'subjects' => collect(CrmAuditLog::SUBJECTS)->map(fn ($s) => $s[1]),
            'events'   => CrmAuditLog::events(),
            'actors'   => $this->audit->actors($business),
        ]);
    }
}
