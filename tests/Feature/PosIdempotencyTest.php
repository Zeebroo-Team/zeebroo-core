<?php

use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesOfflineTime;
use Modules\Pos\Http\Middleware\PosIdempotency;

// Desktop offline queue: a POS write sent with an Idempotency-Key runs once;
// retries get the first answer back.
beforeEach(function () {
    if (! Schema::hasTable('pos_idempotency_keys')) {
        (require base_path('Modules/Pos/database/migrations/2026_11_02_100000_create_pos_idempotency_keys_table.php'))->up();
    }
});

function idempotentRequest(string $key, string $path = 'api/v1/pos/cash-drawer/withdraw', string $business = '7'): Request
{
    $request = Request::create('/'.$path, 'POST', ['amount' => 50]);
    $request->headers->set(PosIdempotency::HEADER, $key);
    $request->headers->set('X-Business-Id', $business);

    return $request;
}

test('a retried write runs once and gets the first answer back', function () {
    $runs = 0;
    $next = function () use (&$runs) {
        $runs++;

        return response()->json(['message' => 'Withdrawal recorded.', 'run' => $runs]);
    };
    $middleware = new PosIdempotency;

    $first  = $middleware->handle(idempotentRequest('k-1'), $next);
    $second = $middleware->handle(idempotentRequest('k-1'), $next);

    expect($runs)->toBe(1)
        ->and($second->getStatusCode())->toBe(200)
        ->and(json_decode($second->getContent(), true)['run'])->toBe(1)
        ->and($second->headers->get('Idempotent-Replayed'))->toBe('true')
        ->and($first->headers->has('Idempotent-Replayed'))->toBeFalse();
});

test('a rejected write is not remembered, so it can be retried', function () {
    $runs = 0;
    $middleware = new PosIdempotency;
    $middleware->handle(idempotentRequest('k-2'), function () use (&$runs) {
        $runs++;

        return response()->json(['message' => 'Not enough cash.'], 422);
    });
    $retry = $middleware->handle(idempotentRequest('k-2'), function () use (&$runs) {
        $runs++;

        return response()->json(['message' => 'ok']);
    });

    expect($runs)->toBe(2)->and($retry->getStatusCode())->toBe(200);
});

test('the same key in another business, or without a key, runs normally', function () {
    $runs = 0;
    $next = function () use (&$runs) {
        $runs++;

        return response()->json(['ok' => true]);
    };
    $middleware = new PosIdempotency;

    $middleware->handle(idempotentRequest('k-3', business: '7'), $next);
    $middleware->handle(idempotentRequest('k-3', business: '8'), $next);
    $middleware->handle(Request::create('/api/v1/pos/cash-drawer/withdraw', 'POST'), $next);
    $middleware->handle(Request::create('/api/v1/pos/cash-drawer/withdraw', 'POST'), $next);

    expect($runs)->toBe(4);
});

test('a key reused for a different request is refused', function () {
    $middleware = new PosIdempotency;
    $middleware->handle(idempotentRequest('k-4'), fn () => response()->json(['ok' => true]));

    $other = $middleware->handle(idempotentRequest('k-4', 'api/v1/pos/eod/settle'), fn () => response()->json(['ok' => true]));

    expect($other->getStatusCode())->toBe(422);
});

// ── occurred_at: when an offline action really happened ────────────────────
function offlineTimeResolver(): object
{
    return new class {
        use ResolvesOfflineTime;

        public function at(array $body): ?CarbonImmutable
        {
            return $this->offlineOccurredAt(Request::create('/', 'POST', $body));
        }
    };
}

test('occurred_at is used as sent, a future time is pulled back to now', function () {
    $resolver = offlineTimeResolver();
    $twoDaysAgo = now()->subDays(2)->startOfMinute();

    expect($resolver->at([]))->toBeNull()
        ->and($resolver->at(['occurred_at' => $twoDaysAgo->copy()->utc()->toIso8601String()])->equalTo($twoDaysAgo))->toBeTrue()
        ->and($resolver->at(['occurred_at' => now()->addDay()->toIso8601String()])->lte(now()))->toBeTrue();
});

test('occurred_at older than 7 days is refused', function () {
    offlineTimeResolver()->at(['occurred_at' => now()->subDays(8)->toIso8601String()]);
})->throws(HttpResponseException::class);
