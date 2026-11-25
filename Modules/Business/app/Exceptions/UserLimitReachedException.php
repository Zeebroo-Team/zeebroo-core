<?php

namespace Modules\Business\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when inviting another user would exceed the business's seat limit.
 * API clients get a 409 with `code: user_limit_reached` (the desktop app then
 * opens its "buy extra users" dialog); web pages get a normal form error.
 */
class UserLimitReachedException extends RuntimeException
{
    public const CODE = 'user_limit_reached';

    public function __construct(public readonly int $used, public readonly int $limit)
    {
        parent::__construct("Your plan's user limit is full ({$used} of {$limit} users). Buy extra users to add more people.");
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $this->getMessage(),
                'code' => self::CODE,
                'used' => $this->used,
                'limit' => $this->limit,
            ], 409);
        }

        return back()->withInput()->withErrors(['email' => $this->getMessage()]);
    }
}
