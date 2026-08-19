<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Auth\Services\UserManagementService;
use Modules\Business\Models\Business;
use Modules\CRM\Models\Project as CrmProject;
use Modules\Pos\Models\Sale;
use Modules\Purchase\Models\Purchase;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\Quotation;
use Spatie\Permission\Models\Role;

class AdminUserController extends Controller
{
    public function __construct(private readonly UserManagementService $users) {}

    public function index(): View
    {
        return view('auth::admin.users.index', [
            'users' => $this->users->paginate(),
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function show(User $user): View
    {
        $user->load([
            'roles',
            'businesses' => fn ($query) => $query->orderByDesc('created_at'),
            'businesses.branches',
            'accounts' => fn ($query) => $query->orderByDesc('created_at'),
            'accounts.business',
            'accounts.warehouse',
            'accounts.bank',
            'accounts.bankType',
            'hrEmployees.business',
            'appConnections' => fn ($query) => $query->orderByDesc('created_at'),
        ]);

        $businessStats = $user->businesses->mapWithKeys(
            fn (Business $business) => [$business->id => $this->businessStats($business)]
        );

        $lastSeenAt = DB::table('sessions')->where('user_id', $user->id)->max('last_activity');

        return view('auth::admin.users.show', [
            'user' => $user,
            'businessStats' => $businessStats,
            'recentActivity' => $this->recentActivity($user, $businessStats),
            'lastSeenAt' => $lastSeenAt ? Carbon::createFromTimestamp($lastSeenAt) : null,
        ]);
    }

    /**
     * Most recent completed sales and received purchases across all of the user's
     * businesses, merged and sorted by date, for a quick "what have they been doing" glance.
     */
    private function recentActivity(User $user, Collection $businessStats): Collection
    {
        $businessIds = $user->businesses->pluck('id');
        $businessNames = $user->businesses->pluck('name', 'id');

        if ($businessIds->isEmpty()) {
            return collect();
        }

        $recentSales = Sale::query()
            ->whereIn('business_id', $businessIds)
            ->where('status', Sale::STATUS_COMPLETED)
            ->latest('sold_at')
            ->limit(5)
            ->get(['id', 'business_id', 'sale_number', 'total', 'sold_at']);

        $recentPurchases = Purchase::query()
            ->whereIn('business_id', $businessIds)
            ->where('status', Purchase::STATUS_RECEIVED)
            ->latest('purchase_date')
            ->limit(5)
            ->get(['id', 'business_id', 'po_number', 'total', 'purchase_date']);

        $activity = collect();

        foreach ($recentSales as $sale) {
            $activity->push([
                'type' => 'sale',
                'label' => 'Sale '.$sale->sale_number,
                'business' => $businessNames[$sale->business_id] ?? '—',
                'currency' => $businessStats[$sale->business_id]['currency'] ?? 'LKR',
                'amount' => (float) $sale->total,
                'date' => $sale->sold_at,
            ]);
        }

        foreach ($recentPurchases as $purchase) {
            $activity->push([
                'type' => 'purchase',
                'label' => 'Purchase '.$purchase->po_number,
                'business' => $businessNames[$purchase->business_id] ?? '—',
                'currency' => $businessStats[$purchase->business_id]['currency'] ?? 'LKR',
                'amount' => (float) $purchase->total,
                'date' => $purchase->purchase_date,
            ]);
        }

        return $activity->sortByDesc('date')->take(8)->values();
    }

    /**
     * Overview / sales-purchases / HR / CRM analysis for one business, computed via
     * aggregate queries so we never load a business's full product/sale/etc. tables.
     */
    private function businessStats(Business $business): array
    {
        $stockValue = (float) ($business->products()
            ->where('is_active', true)
            ->selectRaw('COALESCE(SUM(stock_quantity * cost_price), 0) as total')
            ->value('total') ?? 0);

        $leads = $business->crmLeads();

        return [
            'currency' => (string) (get_settings('business.currency', '', $business) ?: 'LKR'),
            'overview' => [
                'products_total' => $business->products()->count(),
                'products_active' => $business->products()->where('is_active', true)->count(),
                'stock_value' => $stockValue,
                'suppliers_count' => $business->suppliers()->count(),
                'branches_count' => $business->branches->count(),
            ],
            'sales_purchases' => [
                'sales_count' => $business->sales()->where('status', Sale::STATUS_COMPLETED)->count(),
                'sales_total' => (float) $business->sales()->where('status', Sale::STATUS_COMPLETED)->sum('total'),
                'purchases_count' => $business->purchases()->where('status', Purchase::STATUS_RECEIVED)->count(),
                'purchases_total' => (float) $business->purchases()->where('status', Purchase::STATUS_RECEIVED)->sum('total'),
            ],
            'hr' => [
                'employees_count' => $business->employees()->count(),
                'departments_count' => $business->departments()->count(),
            ],
            'crm' => [
                'leads_count' => (clone $leads)->count(),
                'leads_won' => (clone $leads)->whereHas('stage', fn ($q) => $q->where('is_won', true))->count(),
                'leads_lost' => (clone $leads)->whereHas('stage', fn ($q) => $q->where('is_lost', true))->count(),
                'leads_value' => (float) (clone $leads)->sum('estimated_value'),
                'projects_count' => $business->crmProjects()->count(),
                'projects_active' => $business->crmProjects()->where('status', CrmProject::STATUS_ACTIVE)->count(),
            ],
            'quotes_invoices' => [
                'quotations_count' => $business->quotations()->count(),
                'quotations_accepted' => $business->quotations()->where('status', Quotation::STATUS_ACCEPTED)->count(),
                'invoices_count' => Invoice::where('business_id', $business->id)->count(),
                'invoices_paid_total' => (float) Invoice::where('business_id', $business->id)->where('status', Invoice::STATUS_PAID)->sum('total'),
                'invoices_outstanding_total' => (float) Invoice::where('business_id', $business->id)
                    ->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_OVERDUE])
                    ->sum('total'),
            ],
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
        ]);

        $this->users->create($validated);

        return redirect()->route('admin.users.index')->with('status', __('User created.'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
        ]);

        if ((int) $user->id === (int) $request->user()->id && $validated['role'] !== 'admin') {
            return redirect()->route('admin.users.index')->withErrors([
                'role' => __('You cannot remove your own admin role.'),
            ]);
        }

        if ($this->users->wouldRemoveLastAdmin($user, $validated['role'])) {
            return redirect()->route('admin.users.index')->withErrors([
                'role' => __('Cannot remove the last remaining admin.'),
            ]);
        }

        $this->users->update($user, $validated);

        return redirect()->route('admin.users.index')->with('status', __('User updated.'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ((int) $user->id === (int) $request->user()->id) {
            return redirect()->route('admin.users.index')->withErrors([
                'delete' => __('You cannot delete your own account.'),
            ]);
        }

        if ($reason = $this->users->undeletableReason($user)) {
            return redirect()->route('admin.users.index')->withErrors(['delete' => $reason]);
        }

        $this->users->delete($user);

        return redirect()->route('admin.users.index')->with('status', __('User deleted.'));
    }
}
