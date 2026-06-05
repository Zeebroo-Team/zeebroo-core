<?php

declare(strict_types=1);

namespace Modules\HRManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Modules\Account\Models\Account;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Models\HrComplaint;
use Modules\HRManagement\Models\LeaveRequest;
use Modules\HRManagement\Services\EmployeePortalService;
use Modules\HRManagement\Services\HrPayrollSettingsService;
use Modules\Pos\Models\Sale;
use Modules\Pos\Models\SaleReturn;
use Modules\Pos\Services\PosCatalogService;
use Modules\Pos\Services\PosProductQuickCreateService;
use Modules\Pos\Services\PosSettingsService;
use Modules\Pos\Services\SaleReturnService;
use Modules\Pos\Services\SaleService;
use Modules\Product\Models\Product;
use Modules\Product\Services\ProductCatalogOptionsService;

class HrEmployeePortalController extends Controller
{
    public function __construct(
        private readonly EmployeePortalService $employeePortal,
        private readonly HrPayrollSettingsService $hrPayrollSettings,
        private readonly PosCatalogService $posCatalog,
        private readonly SaleService $posSales,
        private readonly PosSettingsService $posSettings,
        private readonly ProductCatalogOptionsService $productCatalogOptions,
        private readonly PosProductQuickCreateService $posProductQuickCreate,
        private readonly SaleReturnService $saleReturns,
    ) {}

    public function showLogin(): View|RedirectResponse
    {
        $hasAccountButNoEmployee = false;
        if (Auth::check()) {
            $employee = $this->employeePortal->linkAndResolve(Auth::user());
            if ($employee !== null) {
                return redirect()->route('hr.portal.dashboard');
            }
            $hasAccountButNoEmployee = true;
        }

        return view('hrmanagement::portal.login', [
            'googleAuthConfigured' => $this->googleOAuthConfigured(),
            'hasAccountButNoEmployee' => $hasAccountButNoEmployee,
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            $employee = $this->employeePortal->linkAndResolve(Auth::user());

            return $employee !== null
                ? redirect()->route('hr.portal.dashboard')
                : redirect()->route('login')->with('status', __('You are already signed in. Use your workspace, or sign out to switch accounts.'));
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => __('These credentials do not match our records.')])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = Auth::user();
        $employee = $this->employeePortal->linkAndResolve($user);

        if ($employee === null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors([
                    'email' => __('No employee profile is linked to this account. Sign in with the same email your HR team has on file, or ask them to connect your login.'),
                ])
                ->onlyInput('email');
        }

        return redirect()->intended(route('hr.portal.dashboard'));
    }

    public function dashboard(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $employee = $this->employeePortal->linkAndResolve($user);
        if ($employee === null) {
            return redirect()->route('hr.portal.login')
                ->withErrors(['email' => __('Your session no longer has an employee profile.')]);
        }

        $employee->load(['business', 'department', 'jobTitle']);

        $business = $employee->business;
        if ($business === null || ! $this->hrPayrollSettings->optedIn($business)) {
            return view('hrmanagement::portal.unavailable', [
                'employee' => $employee,
                'heading' => __('HR portal'),
                'portalEmployeeChoices' => $this->employeePortal->linkedEmployeesForUser($user),
            ]);
        }

        $employee->load(['leaveRequests' => fn ($q) => $q->orderByDesc('created_at')->limit(20)]);

        $portalFeatures = $this->resolvePortalFeatures($employee);

        return view('hrmanagement::portal.dashboard', [
            'employee' => $employee,
            'heading' => __('HR portal'),
            'employeePortal' => true,
            'portalEmployerBusiness' => $business,
            'portalEmployee' => $employee,
            'portalEmployeeChoices' => $this->employeePortal->linkedEmployeesForUser($user),
            'portalFeatures' => $portalFeatures,
        ]);
    }

    public function switchEmployer(Request $request): RedirectResponse
    {
        $request->validate([
            'employee_id' => ['required', 'integer'],
        ]);

        $user = $request->user();
        if (! $this->employeePortal->setPortalEmployee($user, (int) $request->input('employee_id'))) {
            return back()->withErrors([
                'employer' => __('That employer is not available for your account.'),
            ]);
        }

        return back();
    }

    public function profile(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $employee = $this->employeePortal->linkAndResolve($user);
        if ($employee === null) {
            return redirect()->route('hr.portal.login');
        }

        $employee->load(['business', 'department', 'jobTitle', 'bank']);

        $business = $employee->business;
        if ($business === null || ! $this->hrPayrollSettings->optedIn($business)) {
            return view('hrmanagement::portal.unavailable', [
                'employee' => $employee,
                'heading' => __('HR portal'),
                'portalEmployeeChoices' => $this->employeePortal->linkedEmployeesForUser($user),
            ]);
        }

        return view('hrmanagement::portal.profile', [
            'employee' => $employee,
            'heading' => __('My profile'),
            'employeePortal' => true,
            'portalEmployerBusiness' => $business,
            'portalEmployee' => $employee,
            'portalEmployeeChoices' => $this->employeePortal->linkedEmployeesForUser($user),
        ]);
    }

    public function leaves(Request $request): View|RedirectResponse
    {
        $gate = $this->assertPortalEmployerAvailable($request);
        if ($gate instanceof RedirectResponse || $gate instanceof View) {
            return $gate;
        }

        /** @var array{user: User, employee: Employee, business: Business, choices: Collection} $gate */
        ['employee' => $employee, 'business' => $business, 'choices' => $choices] = $gate;

        if ($denied = $this->denyPortalFeature($employee, 'leaves')) {
            return $denied;
        }

        $leaveRequests = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('hrmanagement::portal.leaves', [
            'employee' => $employee,
            'leaveRequests' => $leaveRequests,
            'heading' => __('My leaves'),
            'employeePortal' => true,
            'portalEmployerBusiness' => $business,
            'portalEmployee' => $employee,
            'portalEmployeeChoices' => $choices,
        ]);
    }

    public function complaints(Request $request): View|RedirectResponse
    {
        $gate = $this->assertPortalEmployerAvailable($request);
        if ($gate instanceof RedirectResponse || $gate instanceof View) {
            return $gate;
        }

        /** @var array{user: User, employee: Employee, business: Business, choices: Collection} $gate */
        ['employee' => $employee, 'business' => $business, 'choices' => $choices] = $gate;

        if ($denied = $this->denyPortalFeature($employee, 'complaints')) {
            return $denied;
        }

        $complaints = HrComplaint::query()
            ->where('employee_id', $employee->id)
            ->where('business_id', $employee->business_id)
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('hrmanagement::portal.complaints', [
            'employee' => $employee,
            'complaints' => $complaints,
            'heading' => __('Complaints'),
            'employeePortal' => true,
            'portalEmployerBusiness' => $business,
            'portalEmployee' => $employee,
            'portalEmployeeChoices' => $choices,
        ]);
    }

    public function storeComplaint(Request $request): View|RedirectResponse
    {
        $gate = $this->assertPortalEmployerAvailable($request);
        if ($gate instanceof RedirectResponse || $gate instanceof View) {
            return $gate;
        }

        /** @var array{user: User, employee: Employee, business: Business, choices: Collection} $gate */
        ['user' => $user, 'employee' => $employee] = $gate;

        if ($denied = $this->denyPortalFeature($employee, 'complaints')) {
            return $denied;
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        HrComplaint::query()->create([
            'business_id' => $employee->business_id,
            'employee_id' => $employee->id,
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'status' => HrComplaint::STATUS_OPEN,
            'recorded_by_user_id' => $user->id,
        ]);

        return redirect()
            ->route('hr.portal.complaints')
            ->with('status', __('Your complaint has been submitted.'));
    }

    public function salary(Request $request): View|RedirectResponse
    {
        $gate = $this->assertPortalEmployerAvailable($request);
        if ($gate instanceof RedirectResponse || $gate instanceof View) {
            return $gate;
        }

        /** @var array{user: User, employee: Employee, business: Business, choices: Collection} $gate */
        ['employee' => $employee, 'business' => $business, 'choices' => $choices] = $gate;

        if ($denied = $this->denyPortalFeature($employee, 'salary')) {
            return $denied;
        }

        $employee->load(['employeeAllowances.allowanceType']);

        return view('hrmanagement::portal.salary', [
            'employee' => $employee,
            'heading' => __('My salary'),
            'employeePortal' => true,
            'portalEmployerBusiness' => $business,
            'portalEmployee' => $employee,
            'portalEmployeeChoices' => $choices,
        ]);
    }

    public function posOnline(Request $request): View|RedirectResponse
    {
        $gate = $this->assertPortalEmployerAvailable($request);
        if ($gate instanceof RedirectResponse || $gate instanceof View) {
            return $gate;
        }

        /** @var array{user: User, employee: Employee, business: Business, choices: Collection} $gate */
        ['employee' => $employee, 'business' => $business, 'choices' => $choices] = $gate;

        if ($denied = $this->denyPortalFeature($employee, 'pos_online')) {
            return $denied;
        }

        // Portal POS always starts in full-screen walk-in mode.
        // Only initialise the session key if it hasn't been set yet (preserve the user's toggle choice on reload).
        if (! session()->has('pos_walking_customer')) {
            session(['pos_walking_customer' => true]);
        }

        $search = (string) $request->query('q', '');
        $categoryId = $request->query('category');
        $categoryId = is_numeric($categoryId) ? (int) $categoryId : null;

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');
        $accounts = Account::query()
            ->with(['bankType', 'bank', 'warehouse'])
            ->where('business_id', $business->id)
            ->orderBy('account_name')
            ->get();
        $categories = $this->posCatalog->posCategories($business);
        $products = $this->posCatalog->productCardsForPos(
            $business,
            $search !== '' ? $search : null,
            $categoryId,
        );
        $today = $this->posSales->todaySummaryForBusiness($business);
        $posSettings = $this->posSettings->forBusiness($business);
        $posShellClass = match ($posSettings['display_theme'] ?? '') {
            'dark' => 'pos-shell--dark',
            'light' => 'pos-shell--light',
            default => '',
        };

        $printSale = null;
        $printSaleId = session()->pull('hr_portal_pos_print_sale_id');
        if (is_numeric($printSaleId)) {
            $printSale = Sale::query()
                ->where('business_id', $business->id)
                ->whereKey((int) $printSaleId)
                ->with(['items', 'creditAccount', 'user'])
                ->first();
        }

        $catalogOptions = $this->productCatalogOptions->optionsForBusiness($business);

        return view('hrmanagement::portal.pos-online', [
            'employee' => $employee,
            'heading' => __('POS Online'),
            'employeePortal' => true,
            'portalEmployerBusiness' => $business,
            'portalEmployee' => $employee,
            'portalEmployeeChoices' => $choices,
            'business' => $business,
            'currency' => $currency,
            'productUnits' => $catalogOptions['units'],
            'search' => $search,
            'categoryId' => $categoryId,
            'categories' => $categories,
            'products' => $products,
            'accounts' => $accounts,
            'hasAccounts' => $accounts->isNotEmpty(),
            'channel' => Sale::CHANNEL_ONLINE,
            'today' => $today,
            'posWalkingCustomer' => false,
            'posSettings' => $posSettings,
            'posShellClass' => $posShellClass,
            'defaultDepositAccountId' => $posSettings['default_deposit_account_id'] ?? null,
            'printSale' => $printSale,
            'checkoutFormAction' => route('hr.portal.pos-online.checkout'),
        ]);
    }

    public function posOnlineCheckout(Request $request): RedirectResponse
    {
        $gate = $this->assertPortalEmployerAvailable($request);
        if ($gate instanceof RedirectResponse || $gate instanceof View) {
            return $gate instanceof RedirectResponse
                ? $gate
                : redirect()->route('hr.portal.pos-online');
        }

        /** @var array{user: User, employee: Employee, business: Business, choices: Collection} $gate */
        ['employee' => $employee, 'business' => $business] = $gate;

        if ($denied = $this->denyPortalFeature($employee, 'pos_online')) {
            return $denied;
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.product_stock_layer_id' => ['nullable', 'integer', 'min:1'],
            'items.*.selling_unit_label' => ['nullable', 'string', 'max:80'],
            'items.*.selling_unit_factor' => ['nullable', 'numeric', 'min:0.000001'],
            'payment_method' => ['required', 'string', 'in:cash,card,credit'],
            'credit_account_id' => [
                'nullable', 'integer', 'min:1',
                Rule::requiredIf(in_array($request->input('payment_method'), ['cash', 'card'], true)),
            ],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'amount_tendered' => ['nullable', 'numeric', 'min:0', 'required_if:payment_method,cash'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'pos_customer_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $posSettings = $this->posSettings->forBusiness($business);
        $deferSettlement = ($posSettings['payment_settlement_mode'] ?? 'immediate') === 'end_of_day';

        $sale = $this->posSales->checkout(
            $business,
            $request->user(),
            $validated['items'],
            $validated['payment_method'],
            isset($validated['credit_account_id']) ? (int) $validated['credit_account_id'] : null,
            isset($validated['amount_paid']) ? (float) $validated['amount_paid'] : null,
            $validated['notes'] ?? null,
            Sale::CHANNEL_ONLINE,
            isset($validated['discount_percent']) ? (float) $validated['discount_percent'] : null,
            isset($validated['amount_tendered']) ? (float) $validated['amount_tendered'] : null,
            isset($validated['pos_customer_id']) ? (int) $validated['pos_customer_id'] : null,
            $deferSettlement,
        );

        return redirect()
            ->route('hr.portal.pos-online')
            ->with('hr_portal_pos_print_sale_id', $sale->id)
            ->with('status', __('Sale :number completed.', ['number' => $sale->sale_number]));
    }

    public function posOnlineSaveSettings(Request $request): RedirectResponse
    {
        $gate = $this->assertPortalEmployerAvailable($request);
        if ($gate instanceof RedirectResponse || $gate instanceof View) {
            return $gate instanceof RedirectResponse ? $gate : redirect()->route('hr.portal.pos-online');
        }

        /** @var array{employee: Employee, business: Business} $gate */
        ['employee' => $employee, 'business' => $business] = $gate;

        if ($denied = $this->denyPortalFeature($employee, 'pos_online')) {
            return $denied;
        }

        $validated = $request->validate([
            'default_deposit_account_id' => ['nullable', 'integer', 'min:1'],
            'discount_field_enabled'      => ['nullable'],
            'checkout_modal_enabled'      => ['nullable', 'boolean'],
            'display_theme'               => ['nullable', 'string', 'in:light,dark'],
            'receipt_header'              => ['nullable', 'string', 'max:200'],
            'receipt_footer'              => ['nullable', 'string', 'max:200'],
            'show_business_name'          => ['nullable'],
            'show_business_address'       => ['nullable'],
            'show_account_info'           => ['nullable'],
            'payment_settlement_mode'     => ['nullable', 'string', 'in:immediate,end_of_day'],
            'redirect'                    => ['nullable', 'string', 'max:2000'],
        ]);

        $this->posSettings->saveForBusiness($business, $validated);

        $redirect = $validated['redirect'] ?? null;
        if (is_string($redirect) && str_starts_with($redirect, url('/'))) {
            return redirect()->to($redirect)->with('status', 'POS settings saved.');
        }

        return redirect()->route('hr.portal.pos-online')->with('status', 'POS settings saved.');
    }

    public function posOnlineStoreProduct(Request $request): \Illuminate\Http\JsonResponse
    {
        $gate = $this->assertPortalEmployerAvailable($request);
        if ($gate instanceof RedirectResponse || $gate instanceof View) {
            return response()->json(['message' => 'Portal session unavailable.'], 403);
        }

        /** @var array{employee: Employee, business: Business} $gate */
        ['employee' => $employee, 'business' => $business] = $gate;

        if ($employee->jobTitle !== null && ! $employee->jobTitle->hasPortalFeature('pos_online')) {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        try {
            $product = $this->posProductQuickCreate->create($business, $request->all());

            return response()->json(['message' => 'Product added.', 'product' => $product]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not save product.',
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    public function togglePortalWalkingCustomer(Request $request): RedirectResponse
    {
        $gate = $this->assertPortalEmployerAvailable($request);
        if ($gate instanceof RedirectResponse || $gate instanceof View) {
            return $gate instanceof RedirectResponse ? $gate : redirect()->route('hr.portal.pos-online');
        }

        session(['pos_walking_customer' => $request->boolean('enabled')]);

        $redirect = $request->input('redirect');
        if (is_string($redirect) && str_starts_with($redirect, url('/'))) {
            return redirect()->to($redirect);
        }

        return redirect()->route('hr.portal.pos-online');
    }

    public function portalReturnsIndex(Request $request): View|RedirectResponse
    {
        $gate = $this->assertPortalEmployerAvailable($request);
        if ($gate instanceof RedirectResponse || $gate instanceof View) {
            return $gate;
        }

        /** @var array{user: User, employee: Employee, business: Business, choices: Collection} $gate */
        ['employee' => $employee, 'business' => $business, 'choices' => $choices] = $gate;

        if ($denied = $this->denyPortalFeature($employee, 'pos_online')) {
            return $denied;
        }

        $search   = (string) $request->query('q', '');
        $currency = (string) (get_settings('business.currency', '', $business) ?: '');

        $returns = SaleReturn::query()
            ->where('business_id', $business->id)
            ->with(['sale:id,sale_number,sold_at', 'user:id,name', 'items'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('return_number', 'like', '%'.$search.'%')
                      ->orWhereHas('sale', fn ($q) => $q->where('sale_number', 'like', '%'.$search.'%'));
                });
            })
            ->orderByDesc('returned_at')
            ->paginate(50);

        $hasReturns = SaleReturn::query()->where('business_id', $business->id)->exists();

        return view('hrmanagement::portal.pos-returns', [
            'employee'    => $employee,
            'heading'     => __('Return items'),
            'employeePortal' => true,
            'portalEmployerBusiness' => $business,
            'portalEmployee' => $employee,
            'portalEmployeeChoices' => $choices,
            'business'    => $business,
            'currency'    => $currency,
            'search'      => $search,
            'returns'     => $returns,
            'hasReturns'  => $hasReturns,
        ]);
    }

    public function portalCreateReturn(Request $request): View|RedirectResponse
    {
        $gate = $this->assertPortalEmployerAvailable($request);
        if ($gate instanceof RedirectResponse || $gate instanceof View) {
            return $gate;
        }

        /** @var array{user: User, employee: Employee, business: Business, choices: Collection} $gate */
        ['employee' => $employee, 'business' => $business, 'choices' => $choices] = $gate;

        if ($denied = $this->denyPortalFeature($employee, 'pos_online')) {
            return $denied;
        }

        $mode        = $request->query('mode') === 'open' ? 'open' : 'ref';
        $saleNumber  = trim((string) $request->query('sale', ''));
        $sale        = null;
        $returnedQtys = [];
        $accounts    = Account::query()->where('business_id', $business->id)->orderBy('account_name')->get();
        $saleNotFound = false;
        $products    = collect();

        if ($mode === 'open') {
            $products = Product::query()
                ->where('business_id', $business->id)
                ->where('is_active', true)
                ->where('is_bundle', false)
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'unit_price']);
        } elseif (filled($saleNumber)) {
            $sale = Sale::query()
                ->where('business_id', $business->id)
                ->where('sale_number', $saleNumber)
                ->with(['items.product', 'returns.items'])
                ->first();

            if ($sale === null) {
                $saleNotFound = true;
            } elseif ($sale->isCompleted()) {
                $returnedQtys = $this->saleReturns->returnedQuantitiesForSale($sale);
            }
        }

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');

        return view('hrmanagement::portal.pos-create-return', [
            'employee'    => $employee,
            'heading'     => __('Create return note'),
            'employeePortal' => true,
            'portalEmployerBusiness' => $business,
            'portalEmployee' => $employee,
            'portalEmployeeChoices' => $choices,
            'business'    => $business,
            'currency'    => $currency,
            'mode'        => $mode,
            'saleNumber'  => $saleNumber,
            'sale'        => $sale,
            'returnedQtys' => $returnedQtys,
            'accounts'    => $accounts,
            'saleNotFound' => $saleNotFound,
            'products'    => $products,
        ]);
    }

    public function portalStoreReturn(Request $request, Sale $sale): RedirectResponse
    {
        $gate = $this->assertPortalEmployerAvailable($request);
        if ($gate instanceof RedirectResponse || $gate instanceof View) {
            return $gate instanceof RedirectResponse ? $gate : redirect()->route('hr.portal.pos-returns.index');
        }

        /** @var array{employee: Employee, business: Business} $gate */
        ['employee' => $employee, 'business' => $business] = $gate;

        if ($denied = $this->denyPortalFeature($employee, 'pos_online')) {
            return $denied;
        }

        abort_unless($sale->business_id === $business->id, 404);

        $validated = $request->validate([
            'items'                    => ['required', 'array', 'min:1'],
            'items.*.sale_item_id'     => ['required', 'integer', 'min:1'],
            'items.*.quantity'         => ['required', 'numeric', 'min:0.001'],
            'refund_method'            => ['required', 'string', 'in:cash,credit,none'],
            'refund_reason'            => ['nullable', 'string', 'max:100'],
            'credit_account_id'        => ['nullable', 'integer', 'min:1'],
            'notes'                    => ['nullable', 'string', 'max:2000'],
        ]);

        $ret = $this->saleReturns->processReturn(
            $sale,
            $business,
            $request->user(),
            $validated['items'],
            $validated['refund_method'],
            isset($validated['credit_account_id']) ? (int) $validated['credit_account_id'] : null,
            $validated['notes'] ?? null,
            $validated['refund_reason'] ?? null,
        );

        return redirect()
            ->route('hr.portal.pos-returns.index')
            ->with('status', "Return {$ret->return_number} processed successfully.");
    }

    public function portalStoreOpenReturn(Request $request): RedirectResponse
    {
        $gate = $this->assertPortalEmployerAvailable($request);
        if ($gate instanceof RedirectResponse || $gate instanceof View) {
            return $gate instanceof RedirectResponse ? $gate : redirect()->route('hr.portal.pos-returns.index');
        }

        /** @var array{employee: Employee, business: Business} $gate */
        ['employee' => $employee, 'business' => $business] = $gate;

        if ($denied = $this->denyPortalFeature($employee, 'pos_online')) {
            return $denied;
        }

        $validated = $request->validate([
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.product_id'     => ['required', 'integer', 'min:1'],
            'items.*.quantity'       => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price'     => ['required', 'numeric', 'min:0'],
            'refund_method'          => ['required', 'string', 'in:cash,credit,none'],
            'refund_reason'          => ['nullable', 'string', 'max:100'],
            'credit_account_id'      => ['nullable', 'integer', 'min:1'],
            'notes'                  => ['nullable', 'string', 'max:2000'],
        ]);

        $ret = $this->saleReturns->processOpenReturn(
            $business,
            $request->user(),
            $validated['items'],
            $validated['refund_method'],
            isset($validated['credit_account_id']) ? (int) $validated['credit_account_id'] : null,
            $validated['notes'] ?? null,
            $validated['refund_reason'] ?? null,
        );

        return redirect()
            ->route('hr.portal.pos-returns.index')
            ->with('status', "Return {$ret->return_number} processed successfully.");
    }

    /**
     * @return array<string, mixed>|View|RedirectResponse
     */
    private function assertPortalEmployerAvailable(Request $request): array|View|RedirectResponse
    {
        $user = $request->user();
        $employee = $this->employeePortal->linkAndResolve($user);
        if ($employee === null) {
            return redirect()->route('hr.portal.login');
        }

        $choices = $this->employeePortal->linkedEmployeesForUser($user);
        $employee->loadMissing(['business', 'jobTitle']);
        $business = $employee->business;

        if ($business === null || ! $this->hrPayrollSettings->optedIn($business)) {
            return view('hrmanagement::portal.unavailable', [
                'employee' => $employee,
                'heading' => __('HR portal'),
                'portalEmployeeChoices' => $choices,
            ]);
        }

        return compact('user', 'employee', 'business', 'choices');
    }

    /** Returns null if allowed, or a redirect if the designation blocks this feature. */
    private function denyPortalFeature(Employee $employee, string $feature): ?RedirectResponse
    {
        $employee->loadMissing('jobTitle');

        if ($employee->jobTitle !== null && ! $employee->jobTitle->hasPortalFeature($feature)) {
            return redirect()->route('hr.portal.dashboard')
                ->withErrors(['access' => __('This feature is not available for your role.')]);
        }

        return null;
    }

    /** Returns a keyed bool array of which features are accessible for this employee. */
    private function resolvePortalFeatures(Employee $employee): array
    {
        $employee->loadMissing('jobTitle');

        return array_combine(
            \Modules\HRManagement\Models\JobTitle::PORTAL_FEATURES,
            array_map(
                fn (string $f) => $employee->jobTitle === null || $employee->jobTitle->hasPortalFeature($f),
                \Modules\HRManagement\Models\JobTitle::PORTAL_FEATURES
            )
        );
    }

    private function googleOAuthConfigured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }
}
