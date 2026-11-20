<?php

namespace Modules\Payment\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Payment\Models\PaymentCheckRun;
use Modules\Payment\Models\PaymentCheckSetting;
use Modules\Payment\Services\PaymentCheckService;
use Modules\Payment\Services\StripePaymentSyncService;

/**
 * Admin › Payment Checks — this month's Stripe double-check results, the
 * schedule settings (on/off + interval) and webhook health.
 */
class AdminPaymentCheckController extends Controller
{
    public function __construct(private readonly PaymentCheckService $checks) {}

    public function index(Request $request): View
    {
        $runs = PaymentCheckRun::query()->with('triggeredBy:id,name')->latest('id')->limit(30)->get();

        $selected = $request->filled('run')
            ? PaymentCheckRun::query()->with('triggeredBy:id,name')->findOrFail((int) $request->query('run'))
            : $runs->firstWhere('status', 'completed') ?? $runs->first();

        return view('payment::admin.checks', [
            'settings' => PaymentCheckSetting::current()->load('editor:id,name'),
            'runs' => $runs,
            'selected' => $selected,
            'filter' => in_array($request->query('outcome'), [
                PaymentCheckRun::OUTCOME_OK, PaymentCheckRun::OUTCOME_FIXED, PaymentCheckRun::OUTCOME_ISSUE, PaymentCheckRun::OUTCOME_ERROR,
            ], true) ? $request->query('outcome') : null,
            'webhookConfigured' => (string) config('services.stripe.webhook_secret') !== '',
            'webhookUrl' => route('api.payment.webhook'),
            'webhookEvents' => StripePaymentSyncService::WEBHOOK_EVENTS,
            'stripeMode' => $this->stripeMode(),
            'isLocalUrl' => (bool) preg_match('#^https?://(localhost|127\.0\.0\.1|0\.0\.0\.0|\[::1\])(:|/|$)#i', route('api.payment.webhook')),
        ]);
    }

    /** Test / Live from the key prefix only — the key itself never reaches the view. */
    private function stripeMode(): ?string
    {
        $secret = (string) config('services.stripe.secret');

        return match (true) {
            str_starts_with($secret, 'sk_live_'), str_starts_with($secret, 'rk_live_') => 'live',
            str_starts_with($secret, 'sk_test_'), str_starts_with($secret, 'rk_test_') => 'test',
            default => null,
        };
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'is_enabled' => ['nullable', 'boolean'],
            'interval_minutes' => ['required', 'integer', 'min:'.PaymentCheckSetting::MIN_INTERVAL, 'max:'.PaymentCheckSetting::MAX_INTERVAL],
        ]);

        PaymentCheckSetting::current()->update([
            'is_enabled' => $request->boolean('is_enabled'),
            'interval_minutes' => (int) $data['interval_minutes'],
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.payments.checks.index')->with('status', 'Payment check schedule saved.');
    }

    public function runNow(Request $request): RedirectResponse
    {
        $run = $this->checks->run(PaymentCheckRun::TRIGGER_MANUAL, $request->user());

        if (! $run) {
            return redirect()->route('admin.payments.checks.index')
                ->withErrors(['check' => 'A payment check is already running — refresh in a moment.']);
        }

        if ($run->status === 'failed') {
            return redirect()->route('admin.payments.checks.index', ['run' => $run->id])
                ->withErrors(['check' => 'Payment check failed: '.$run->error]);
        }

        return redirect()->route('admin.payments.checks.index', ['run' => $run->id])->with('status', sprintf(
            'Checked %d — %d ok, %d fixed, %d need follow-up, %d errors.',
            $run->checked_count, $run->ok_count, $run->fixed_count, $run->issue_count, $run->error_count,
        ));
    }
}
