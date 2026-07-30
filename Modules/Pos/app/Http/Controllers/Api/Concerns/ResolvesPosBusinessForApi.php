<?php

namespace Modules\Pos\Http\Controllers\Api\Concerns;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessMember;
use Modules\HRManagement\Models\Employee;
use Modules\Pos\Models\PosCashier;

trait ResolvesPosBusinessForApi
{
    protected function resolveBusinessForApi(Request $request): Business|JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // PosCashier token — resolve business directly from the cashier's business_id
        if ($user instanceof PosCashier) {
            $business = Business::find($user->business_id);
            if (! $business) {
                return response()->json(['message' => 'Business not found.'], 422);
            }
            return $business;
        }

        $rawId = $request->header('X-Business-Id')
            ?? $request->query('business_id')
            ?? session('selected_business_id');

        $business = null;
        if ($rawId !== null && $rawId !== '') {
            $employeeBusinessIds = Employee::query()
                ->where('user_id', $user->id)
                ->whereNotNull('user_id')
                ->pluck('business_id');

            $memberBusinessIds = BusinessMember::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->pluck('business_id');

            $business = Business::query()
                ->where(function ($q) use ($user, $employeeBusinessIds, $memberBusinessIds) {
                    $q->where('user_id', $user->id)
                      ->orWhereIn('id', $employeeBusinessIds)
                      ->orWhereIn('id', $memberBusinessIds);
                })
                ->whereKey((int) $rawId)
                ->first();
        } else {
            $business = Business::currentForNavbar($user);
        }

        if ($business === null) {
            return response()->json([
                'message' => 'No business selected. Send X-Business-Id header or business_id query parameter.',
                'errors' => [
                    'business_id' => ['Select a business the authenticated user can access.'],
                ],
            ], 422);
        }

        return $business;
    }

    protected function businessOrAbort(Request $request): Business
    {
        $business = $this->resolveBusinessForApi($request);
        if ($business instanceof JsonResponse) {
            throw new HttpResponseException($business);
        }

        return $business;
    }

    /**
     * Resolve the BusinessMember record for the authenticated user in the given business.
     * Returns null for the business owner (full access, no member record required).
     */
    protected function resolveMember(Request $request, Business $business): ?BusinessMember
    {
        $user = $request->user();
        if ($user === null || $user instanceof PosCashier || (int) $business->user_id === (int) $user->id) {
            return null; // owner or cashier — full POS access
        }

        return BusinessMember::query()
            ->where('business_id', $business->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Abort with 403 unless the authenticated user has the given permission key.
     * Business owners always pass. Admins (null permissions array) always pass.
     */
    protected function abortUnlessPerm(Request $request, Business $business, string $permKey): void
    {
        $member = $this->resolveMember($request, $business);
        if ($member === null) return; // owner — pass

        if (! $member->hasPermission($permKey)) {
            throw new HttpResponseException(
                response()->json(['message' => 'You do not have permission to perform this action.'], 403)
            );
        }
    }
}
