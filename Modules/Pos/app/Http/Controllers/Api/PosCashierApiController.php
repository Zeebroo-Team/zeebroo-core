<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Business\Models\Business;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\PosCashier;

class PosCashierApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $cashiers = PosCashier::where('business_id', $business->id)
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'is_active', 'created_at']);

        return response()->json(['data' => $cashiers]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'username' => [
                'required', 'string', 'max:80',
                Rule::unique('pos_cashiers')->where('business_id', $business->id),
            ],
            'password' => ['required', 'string', 'min:4', 'max:255'],
        ]);

        $cashier = PosCashier::create([
            'business_id' => $business->id,
            'name'        => trim($validated['name']),
            'username'    => trim($validated['username']),
            'password'    => $validated['password'],
            'is_active'   => true,
        ]);

        return response()->json([
            'message' => 'Cashier created.',
            'data'    => $cashier->only(['id', 'name', 'username', 'is_active', 'created_at']),
        ], 201);
    }

    public function update(Request $request, PosCashier $cashier): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_if($cashier->business_id !== $business->id, 403);

        $validated = $request->validate([
            'name'      => ['sometimes', 'string', 'max:120'],
            'username'  => [
                'sometimes', 'string', 'max:80',
                Rule::unique('pos_cashiers')->where('business_id', $business->id)->ignore($cashier->id),
            ],
            'password'  => ['sometimes', 'nullable', 'string', 'min:4', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['name']))      $cashier->name      = trim($validated['name']);
        if (isset($validated['username']))  $cashier->username  = trim($validated['username']);
        if (!empty($validated['password'])) $cashier->password  = $validated['password'];
        if (isset($validated['is_active'])) $cashier->is_active = (bool) $validated['is_active'];

        $cashier->save();

        return response()->json([
            'message' => 'Cashier updated.',
            'data'    => $cashier->only(['id', 'name', 'username', 'is_active', 'created_at']),
        ]);
    }

    public function destroy(Request $request, PosCashier $cashier): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_if($cashier->business_id !== $business->id, 403);

        $cashier->delete();

        return response()->json(['message' => 'Cashier deleted.']);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug'     => ['required', 'string', 'max:200'],
            'username' => ['required', 'string', 'max:80'],
            'password' => ['required', 'string'],
        ]);

        // Find business by slug (match Str::slug(name))
        $business = Business::all(['id', 'name'])->first(
            fn ($b) => Str::slug($b->name) === strtolower(trim($validated['slug']))
        );

        if (! $business) {
            return response()->json(['message' => 'Business not found. Check the business slug.'], 401);
        }

        $cashier = PosCashier::where('business_id', $business->id)
            ->where('username', trim($validated['username']))
            ->where('is_active', true)
            ->first();

        if (! $cashier || ! Hash::check($validated['password'], $cashier->getAttributes()['password'])) {
            return response()->json(['message' => 'Invalid username or password.'], 401);
        }

        // Revoke old tokens and issue a fresh one
        $cashier->tokens()->delete();
        $token = $cashier->createToken('pos-session')->plainTextToken;

        return response()->json([
            'data' => [
                'token'         => $token,
                'cashier_id'    => $cashier->id,
                'cashier_name'  => $cashier->name,
                'cashier_username' => $cashier->username,
                'business_id'   => $business->id,
                'business_name' => $business->name,
                'business_slug' => Str::slug($business->name),
            ],
        ]);
    }
}
