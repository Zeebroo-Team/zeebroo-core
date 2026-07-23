<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosRegisterLockApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function verifyPassword(Request $request): JsonResponse
    {
        $this->businessOrAbort($request);

        $request->validate(['password' => ['required', 'string']]);

        $user = $request->user();

        if (! Hash::check($request->input('password'), $user->password)) {
            return response()->json(['message' => 'Incorrect password.'], 422);
        }

        return response()->json(['ok' => true]);
    }
}
