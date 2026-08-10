<?php

namespace Modules\AdvertisingAgency\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\AdvertisingAgency\Models\Reporter;
use Modules\Business\Models\Business;

class ReporterAuthApiController extends Controller
{
    /**
     * POST /brand-mgmt/reporter/login  (public — no auth required)
     * Body: { slug, email, password }
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug'     => ['required', 'string', 'max:200'],
            'email'    => ['required', 'string', 'max:200'],
            'password' => ['required', 'string'],
        ]);

        // Resolve business by slug
        $business = Business::all(['id', 'name'])->first(
            fn ($b) => Str::slug($b->name) === strtolower(trim($validated['slug']))
        );

        if (! $business) {
            return response()->json(['message' => 'Business not found. Check the business slug.'], 401);
        }

        $reporter = Reporter::where('business_id', $business->id)
            ->where('email', trim($validated['email']))
            ->first();

        if (! $reporter || ! Hash::check($validated['password'], $reporter->getAttributes()['password'])) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        // Revoke old tokens and issue a fresh one
        $reporter->tokens()->delete();
        $token = $reporter->createToken('reporter-session')->plainTextToken;

        return response()->json([
            'data' => [
                'token'         => $token,
                'reporter_id'   => $reporter->id,
                'reporter_name' => $reporter->name,
                'business_id'   => $business->id,
                'business_name' => $business->name,
                'business_slug' => Str::slug($business->name),
            ],
        ]);
    }

    /**
     * GET /brand-mgmt/reporter/me  (reporter-auth required)
     */
    public function me(Request $request): JsonResponse
    {
        /** @var Reporter $reporter */
        $reporter = $request->user();

        if (! ($reporter instanceof Reporter)) {
            return response()->json(['message' => 'Reporter token required.'], 403);
        }

        return response()->json([
            'data' => [
                'reporter_id'   => $reporter->id,
                'reporter_name' => $reporter->name,
                'reporter_email'=> $reporter->email,
                'business_id'   => $reporter->business_id,
            ],
        ]);
    }
}
