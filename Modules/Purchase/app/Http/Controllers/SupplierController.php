<?php

namespace Modules\Purchase\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Purchase\Http\Controllers\Concerns\ResolvesPurchaseBusiness;
use Modules\Purchase\Models\Supplier;
use Modules\Purchase\Services\SupplierDetailService;
use Modules\Purchase\Services\SupplierService;

class SupplierController extends Controller
{
    use ResolvesPurchaseBusiness;

    public function __construct(
        private readonly SupplierService $supplierService,
        private readonly SupplierDetailService $supplierDetailService,
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return view('purchase::suppliers.index', [
            'business' => $business,
            'suppliers' => $business->suppliers()->withCount('purchases')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $supplier = $this->supplierService->create($business, $this->validatedSupplier($request, $business));

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Supplier added.',
                'supplier' => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                ],
            ], 201);
        }

        return redirect()->route('purchase.suppliers.index')->with('status', 'Supplier added.');
    }

    public function show(Request $request, Supplier $supplier): View|RedirectResponse
    {
        $business = $this->requireSupplier($request, $supplier);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');
        $activeTab = (string) $request->query('tab', 'overview');
        $allowedTabs = ['overview', 'payments', 'purchases', 'grns'];
        if (! in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'overview';
        }

        $paymentSubTab = (string) $request->query('pay', 'cash');
        $allowedPayTabs = ['cash', 'cheque', 'credit'];
        if (! in_array($paymentSubTab, $allowedPayTabs, true)) {
            $paymentSubTab = 'cash';
        }

        $detail = $this->supplierDetailService->forShowPage($supplier);

        return view('purchase::suppliers.show', array_merge([
            'business' => $business,
            'supplier' => $supplier,
            'currency' => $currency,
            'activeTab' => $activeTab,
            'paymentSubTab' => $paymentSubTab,
        ], $detail));
    }

    public function edit(Request $request, Supplier $supplier): View|RedirectResponse
    {
        $business = $this->requireSupplier($request, $supplier);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return view('purchase::suppliers.edit', [
            'business' => $business,
            'supplier' => $supplier,
        ]);
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $business = $this->requireSupplier($request, $supplier);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->supplierService->update($supplier, $this->validatedSupplier($request, $business, $supplier));

        return redirect()->route('purchase.suppliers.index')->with('status', 'Supplier updated.');
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        $business = $this->requireSupplier($request, $supplier);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        if ($supplier->purchases()->exists()) {
            return redirect()->route('purchase.suppliers.index')->withErrors([
                'supplier' => 'Cannot delete a supplier linked to purchases.',
            ]);
        }

        $this->supplierService->delete($supplier);

        return redirect()->route('purchase.suppliers.index')->with('status', 'Supplier removed.');
    }

    private function requireSupplier(Request $request, Supplier $supplier): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($this->supplierService->supplierForBusiness($business, $supplier) instanceof Supplier, 404);

        return $business;
    }

    /**
     * @return array{name: string, contact_name: ?string, email: ?string, phone: ?string, notes: ?string, is_active: bool}
     */
    private function validatedSupplier(Request $request, Business $business, ?Supplier $ignore = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('suppliers', 'name')
                    ->where(fn ($q) => $q->where('business_id', $business->id))
                    ->ignore($ignore?->id),
            ],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
