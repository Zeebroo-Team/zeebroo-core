<?php

namespace Modules\CRM\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessMember;
use Illuminate\Database\Eloquent\Model;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\Project;
use Modules\CRM\Policies\LeadPolicy;
use Modules\CRM\Services\CrmAccess;
use Modules\CRM\Services\CrmPermissionService;

trait ResolvesCrmBusiness
{
    private ?CrmAccess $resolvedCrmAccess = null;

    protected function requireBusiness(Request $request): Business|RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (!$business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'Select or create a business first.']);
        }

        abort_unless(Business::canAccess($request->user(), $business), 403);

        return $business;
    }

    /** A pipeline of the current business that the user may see (LeadPolicy::canViewProject). */
    protected function requireProject(Request $request, Project $project): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless((int) $project->business_id === (int) $business->id, 404);
        abort_unless($this->leadPolicy()->canViewProject($this->crmAccess($request, $business), $project), 404);

        return $business;
    }

    /** As requireProject, and the user may change its stages, forms and fields (and use $function, if given). */
    protected function requireManageableProject(Request $request, Project $project, ?string $function = null): Business|RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $access = $this->crmAccess($request, $business);
        abort_unless($this->leadPolicy()->canManageProject($access, $project), 403);
        abort_unless($function === null || $access->can($function), 403);

        return $business;
    }

    /** The signed-in user's CRM access, resolved once per request. */
    protected function crmAccess(Request $request, Business $business): CrmAccess
    {
        return $this->resolvedCrmAccess ??= app(CrmPermissionService::class)->accessFor($business, $request->user());
    }

    protected function leadPolicy(): LeadPolicy
    {
        return app(LeadPolicy::class);
    }

    /**
     * Logging activities and tasks: on a lead it takes edit rights on that lead; on a customer
     * (or nothing) any CRM user who is not view-only.
     */
    protected function canWorkSubject(CrmAccess $access, ?Model $subject): bool
    {
        return $subject instanceof Lead
            ? $this->leadPolicy()->canEdit($access, $subject)
            : $access->hasCrm() && ! $access->isReadOnly();
    }

    /**
     * @return Collection<int, \App\Models\User>
     */
    protected function assignableUsers(Business $business): Collection
    {
        $memberUsers = BusinessMember::query()
            ->where('business_id', $business->id)
            ->where('status', 'active')
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        return $memberUsers->push($business->user)->filter()->unique('id')->sortBy('name')->values();
    }
}
