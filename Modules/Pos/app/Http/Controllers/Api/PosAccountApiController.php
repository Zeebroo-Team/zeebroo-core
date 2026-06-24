<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Account\Models\Account;
use Modules\Account\Models\BankType;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosAccountApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $accounts = Account::where('business_id', $business->id)
            ->where('user_id', $request->user()->id)
            ->orderBy('account_name')
            ->get(['id', 'account_name', 'bank_name', 'current_balance']);

        return response()->json([
            'data' => $accounts->map(fn (Account $a) => [
                'id'              => $a->id,
                'account_name'    => $a->account_name,
                'bank_name'       => $a->bank_name,
                'current_balance' => (float) $a->current_balance,
            ])->values(),
        ]);
    }

    public function bankTypes(Request $request): JsonResponse
    {
        $this->businessOrAbort($request);

        $types = BankType::orderBy('name')->get(['id', 'name', 'slug', 'description']);

        return response()->json([
            'data' => $types->map(fn (BankType $t) => [
                'id'          => $t->id,
                'name'        => $t->name,
                'slug'        => $t->slug,
                'description' => $t->description,
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'account_name'        => 'required|string|max:255',
            'category'            => 'required|in:operating,savings,petty_cash,credit_card,payroll,investment,loan',
            'bank_type_id'        => 'required|exists:bank_types,id',
            'bank_id'             => 'nullable|exists:banks,id',
            'bank_name'           => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:255',
            'branch'              => 'nullable|string|max:255',
            'current_balance'     => 'required|numeric|min:0',
            'notes'               => 'nullable|string|max:2000',
        ]);

        $account = Account::create([
            'user_id'             => $request->user()->id,
            'business_id'         => $business->id,
            'account_name'        => $validated['account_name'],
            'category'            => $validated['category'],
            'bank_type_id'        => $validated['bank_type_id'],
            'bank_id'             => $validated['bank_id'] ?? null,
            'bank_name'           => $validated['bank_name'] ?? null,
            'bank_account_number' => $validated['bank_account_number'] ?? null,
            'branch'              => $validated['branch'] ?? null,
            'current_balance'     => $validated['current_balance'],
            'notes'               => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'data' => [
                'id'              => $account->id,
                'account_name'    => $account->account_name,
                'current_balance' => (float) $account->current_balance,
            ],
        ], 201);
    }
}
