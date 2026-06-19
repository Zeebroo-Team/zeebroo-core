<?php

namespace Modules\Account\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Models\User;
use Modules\Account\Models\Account;
use Modules\Account\Models\Bank;
use Modules\Account\Models\BankType;
use Modules\Account\Services\AccountService;
use Modules\Business\Models\Business;

class AccountController extends Controller
{
    public function __construct(private readonly AccountService $accountService)
    {
    }

    public function index()
    {
        $accounts = Account::with(['bankType', 'business', 'bank', 'warehouse'])
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        if (request()->expectsJson()) {
            return response()->json(['data' => $accounts]);
        }

        return view('account::index', compact('accounts'));
    }

    public function create()
    {
        return view('account::create', array_merge([
            'bankTypes' => BankType::orderBy('name')->get(),
            'banks' => Bank::orderBy('name')->get(),
            'businesses' => Business::where('user_id', Auth::id())->orderBy('name')->get(),
        ], $this->warehousesFormContext()));
    }

    public function onboarding()
    {
        return view('account::onboarding', array_merge([
            'bankTypes' => BankType::orderBy('name')->get(),
            'banks' => Bank::orderBy('name')->get(),
            'businesses' => Business::where('user_id', Auth::id())->orderBy('name')->get(),
            'defaultBusiness' => Business::where('user_id', Auth::id())->latest()->first(),
            'defaultBankTypeId' => BankType::query()->where('slug', 'current-account')->value('id'),
            'accountCategories' => Account::categories(),
        ], $this->warehousesFormContext()));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'business_id' => ['required', Rule::exists('businesses', 'id')->where('user_id', Auth::id())],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('business_id', $request->integer('business_id'))),
            ],
            'account_name' => ['required', 'string', 'max:255'],
            'bank_type_id' => ['required', 'exists:bank_types,id'],
            'category' => ['nullable', 'string', 'in:' . implode(',', array_keys(Account::categories()))],
            'bank_id' => ['required', 'exists:banks,id'],
            'bank_account_number' => ['required', 'string', 'max:255'],
            'branch' => ['required', 'string', 'max:255'],
            'current_balance' => ['required', 'numeric', 'min:0'],
            'bank_officer_contact' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data = $this->finalizeWarehouseBranchOnAccount($request->user(), $data);

        $account = $this->accountService->create($request->user(), $data);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Account created.', 'data' => $account->load(['bankType', 'business', 'bank'])], 201);
        }

        if ($request->boolean('from_onboarding')) {
            session([
                'selected_business_id' => (int) $data['business_id'],
                'selected_account_id' => (int) $account->getKey(),
            ]);

            return redirect()->route('dashboard')->with('status', 'Bank account setup completed.');
        }

        return redirect()->route('account.index')->with('status', 'Account created.');
    }

    public function show($id)
    {
        $account = Account::with(['bankType', 'business', 'bank', 'warehouse'])
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        if (request()->expectsJson()) {
            return response()->json(['data' => $account]);
        }

        return view('account::show', compact('account'));
    }

    public function edit($id)
    {
        $account = Account::where('user_id', Auth::id())->findOrFail($id);

        return view('account::edit', array_merge([
            'account' => $account,
            'bankTypes' => BankType::orderBy('name')->get(),
            'banks' => Bank::orderBy('name')->get(),
            'businesses' => Business::where('user_id', Auth::id())->orderBy('name')->get(),
        ], $this->warehousesFormContext()));
    }

    public function update(Request $request, $id): JsonResponse|RedirectResponse
    {
        $account = Account::where('user_id', Auth::id())->findOrFail($id);

        $data = $request->validate([
            'business_id' => ['required', Rule::exists('businesses', 'id')->where('user_id', Auth::id())],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('business_id', $request->integer('business_id'))),
            ],
            'account_name' => ['required', 'string', 'max:255'],
            'bank_type_id' => ['required', 'exists:bank_types,id'],
            'bank_id' => ['required', 'exists:banks,id'],
            'bank_account_number' => ['required', 'string', 'max:255'],
            'branch' => ['required', 'string', 'max:255'],
            'current_balance' => ['required', 'numeric', 'min:0'],
            'bank_officer_contact' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data = $this->finalizeWarehouseBranchOnAccount($request->user(), $data);

        $account = $this->accountService->update($account, $data);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Account updated.', 'data' => $account->load(['bankType', 'business', 'bank'])]);
        }

        return redirect()->route('account.index')->with('status', 'Account updated.');
    }

    public function destroy($id): JsonResponse|RedirectResponse
    {
        $account = Account::where('user_id', Auth::id())->findOrFail($id);
        $account->delete();

        if (request()->expectsJson()) {
            return response()->json(['message' => 'Account deleted.']);
        }

        return redirect()->route('account.index')->with('status', 'Account deleted.');
    }

    /**
     * Branch records per business + multi-warehouse flag for account forms / JS dropdown.
     *
     * @return array{accountBusinessMultiWarehouse: array<int, bool>, accountBranchesByBusiness: array<int, list<array{id: int, name: string}>>}
     */
    private function warehousesFormContext(): array
    {
        $businesses = Business::query()
            ->where('user_id', Auth::id())
            ->with(['branches' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->get();

        $multiWarehouse = [];
        $byBusiness = [];

        foreach ($businesses as $biz) {
            $multiWarehouse[$biz->id] = $biz->multiWarehouseBranchEnabled();
            $byBusiness[$biz->id] = $biz->branches->map(fn ($br) => [
                'id' => $br->id,
                'name' => $br->name,
            ])->values()->all();
        }

        return [
            'accountBusinessMultiWarehouse' => $multiWarehouse,
            'accountBranchesByBusiness' => $byBusiness,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function finalizeWarehouseBranchOnAccount(User $user, array $data): array
    {
        $businessId = (int) $data['business_id'];
        $business = Business::query()->where('user_id', $user->id)->whereKey($businessId)->first();

        if (!$business instanceof Business || !$business->multiWarehouseBranchEnabled()) {
            $data['branch_id'] = null;
        } elseif (empty($data['branch_id'])) {
            $data['branch_id'] = null;
        } else {
            $data['branch_id'] = (int) $data['branch_id'];
        }

        return $data;
    }
}
