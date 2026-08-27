<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Branch;
use Modules\Business\Services\BranchService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosBranchManagementApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $branches = app(BranchService::class)->listForBusiness($business);

        return response()->json([
            'data' => $branches->map(fn (Branch $b) => $this->toArray($b))->values()->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $this->validatedBranch($request);

        $branch = app(BranchService::class)->create($business, $validated);

        return response()->json(['data' => $this->toArray($branch)], 201);
    }

    public function update(Request $request, int $branch): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $branchModel = $business->branches()->where('id', $branch)->firstOrFail();

        $validated = $this->validatedBranch($request);

        $branchModel = app(BranchService::class)->update($branchModel, $validated);

        return response()->json(['data' => $this->toArray($branchModel)]);
    }

    public function destroy(Request $request, int $branch): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $branchModel = $business->branches()->where('id', $branch)->firstOrFail();

        app(BranchService::class)->delete($branchModel);

        return response()->json(['message' => 'Branch deleted.']);
    }

    private function validatedBranch(Request $request): array
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'address'     => ['nullable', 'string', 'max:2000'],
            'phone'       => ['nullable', 'string', 'max:40'],
            'email'       => ['nullable', 'email', 'max:255'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : true;

        return $validated;
    }

    private function toArray(Branch $branch): array
    {
        return [
            'id'          => (int) $branch->id,
            'name'        => $branch->name,
            'description' => $branch->description,
            'address'     => $branch->address,
            'phone'       => $branch->phone,
            'email'       => $branch->email,
            'is_active'   => (bool) $branch->is_active,
            'created_at'  => $branch->created_at?->toIso8601String(),
        ];
    }
}
