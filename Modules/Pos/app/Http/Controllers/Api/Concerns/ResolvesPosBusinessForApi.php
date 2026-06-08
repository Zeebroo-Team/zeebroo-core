<?php

namespace Modules\Pos\Http\Controllers\Api\Concerns;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Employee;

trait ResolvesPosBusinessForApi
{
    protected function resolveBusinessForApi(Request $request): Business|JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
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

            $business = Business::query()
                ->where(function ($q) use ($user, $employeeBusinessIds) {
                    $q->where('user_id', $user->id)
                      ->orWhereIn('id', $employeeBusinessIds);
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
}
