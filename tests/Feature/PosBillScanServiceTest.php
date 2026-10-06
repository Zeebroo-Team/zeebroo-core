<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Modules\Pos\Services\PosBillScanService;

function billScanImage(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('bill.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aU1sAAAAASUVORK5CYII='));
}

test('bill scan cleans each field without inventing defaults or trusting IDs', function () {
    $out = app(PosBillScanService::class)->clean([
        'name' => "  CEB\nBill  ", 'bill_category' => 'ELECTRICITY', 'payment_mode' => 'recurring',
        'recurring_type' => 'per_month', 'recurring_cost' => 'LKR 1,234.50',
        'agreement_valid_until_year' => '2027', 'due_date' => '2026-02-30',
        'first_installment_due_date' => '2026-10-12',
        'allow_split_payment' => 'false', 'amount_varies_by_usage' => 'maybe',
        'remind_before_days' => 367, 'deduct_account_id' => 42, 'employee_id' => 99,
    ]);
    expect($out['name'])->toBe('CEB Bill')
        ->and($out['bill_category'])->toBe('electricity')
        ->and($out['recurring_cost'])->toBe(1234.5)
        ->and($out['agreement_valid_until_year'])->toBe(2027)
        ->and($out['due_date'])->toBeNull()
        ->and($out['first_installment_due_date'])->toBe('2026-10-12')
        ->and($out['allow_split_payment'])->toBeFalse()
        ->and($out['amount_varies_by_usage'])->toBeNull()
        ->and($out['remind_before_days'])->toBeNull()
        ->and($out)->not->toHaveKey('deduct_account_id')->not->toHaveKey('employee_id');
});

test('bill scan drops invalid types ranges ambiguous dates and unsupported enums', function () {
    $out = app(PosBillScanService::class)->clean([
        'name' => ['bad'], 'bill_category' => 'made-up', 'description' => str_repeat('x', 2001),
        'payment_mode' => 'recurring', 'recurring_type' => 'per_week', 'agreement_valid_until_year' => 1999,
        'due_date' => '10/11/2026', 'first_installment_due_date' => '2150-01-01',
        'recurring_cost' => '-1', 'remind_before_days' => '1.5', 'allow_split_payment' => 1,
        'amount_varies_by_usage' => true,
    ]);
    expect($out['payment_mode'])->toBe('recurring')->and($out['amount_varies_by_usage'])->toBeTrue();
    unset($out['payment_mode'], $out['amount_varies_by_usage']);
    expect(array_filter($out, fn ($v) => $v !== null))->toBe([]);
});

test('missing bill scan fields stay null and non recurring drafts have no cadence', function () {
    $service = app(PosBillScanService::class);
    expect(array_filter($service->clean([]), fn ($v) => $v !== null))->toBe([]);
    $out = $service->clean(['payment_mode' => 'one_time', 'recurring_type' => 'per_year', 'agreement_valid_until_year' => 2030]);
    expect($out['recurring_type'])->toBeNull()->and($out['agreement_valid_until_year'])->toBeNull();
});

test('bill scanner sends the image and JSON schema to Gemini without tools', function () {
    config(['services.gemini.key' => 'fake-scan-key', 'pos.bill_scan.model' => 'gemini-2.5-flash']);
    Http::preventStrayRequests();
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
        'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [
            ['thought' => true, 'text' => 'Internal reasoning must not be parsed.'],
            ['text' => json_encode(['is_bill' => true, 'name' => 'Water bill', 'recurring_cost' => 450])],
        ]]]],
    ])]);
    $out = app(PosBillScanService::class)->scan(billScanImage());
    expect($out['name'])->toBe('Water bill')->and($out['recurring_cost'])->toBe(450.0);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'gemini-2.5-flash:generateContent')
        && $request->hasHeader('x-goog-api-key', 'fake-scan-key')
        && $request['generationConfig']['responseMimeType'] === 'application/json'
        && isset($request['generationConfig']['responseJsonSchema']['properties']['due_date'])
        && $request['contents'][0]['parts'][1]['inlineData']['mimeType'] === 'image/png'
        && ! isset($request['tools']));
    Http::assertSentCount(1);
});

test('bill scanner rejects malformed or incomplete JSON responses', function ($text, $reason) {
    config(['services.gemini.key' => 'fake-scan-key']);
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response(['candidates' => [['finishReason' => $reason, 'content' => ['parts' => [['text' => $text]]]]]])]);
    expect(fn () => app(PosBillScanService::class)->scan(billScanImage()))->toThrow(RuntimeException::class);
})->with([
    ['not JSON', 'STOP'], ['[]', 'STOP'], ['```json {"is_bill":true} ```', 'STOP'],
    ['{"is_bill":true}', 'MAX_TOKENS'], ['{"is_bill":true}', 'SAFETY'],
]);

test('bill scanner rejects non bill images', function () {
    config(['services.gemini.key' => 'fake-scan-key']);
    Http::fake(['*' => Http::response(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => '{"is_bill":false}']]]]]])]);
    expect(fn () => app(PosBillScanService::class)->scan(billScanImage()))->toThrow(ValidationException::class);
});

test('missing Gemini key does not make an HTTP request', function () {
    config(['services.gemini.key' => '']);
    Http::fake();
    expect(fn () => app(PosBillScanService::class)->scan(billScanImage()))->toThrow(RuntimeException::class);
    Http::assertNothingSent();
});

test('bill scan requires authentication', function () {
    $this->postJson('/api/v1/pos/expenses/bills/scan')->assertUnauthorized();
});
