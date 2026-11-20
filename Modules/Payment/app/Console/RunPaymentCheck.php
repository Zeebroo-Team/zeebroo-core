<?php

namespace Modules\Payment\Console;

use Illuminate\Console\Command;
use Modules\Payment\Models\PaymentCheckRun;
use Modules\Payment\Services\PaymentCheckService;

class RunPaymentCheck extends Command
{
    protected $signature = 'payment:check {--force : Run now, ignoring the admin-set interval and the enabled switch}';

    protected $description = "Double-check this month's subscription payments against Stripe (backs up the webhook)";

    public function handle(PaymentCheckService $checks): int
    {
        $run = $this->option('force')
            ? $checks->run(PaymentCheckRun::TRIGGER_MANUAL)
            : $checks->runIfDue();

        if (! $run) {
            $this->line('Not due yet, disabled, or another check is already running.');

            return self::SUCCESS;
        }

        if ($run->status === 'failed') {
            $this->error("Payment check #{$run->id} failed: {$run->error}");

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Payment check #%d (%s): %d checked — %d ok, %d fixed, %d need follow-up, %d errors.',
            $run->id, $run->period, $run->checked_count, $run->ok_count, $run->fixed_count, $run->issue_count, $run->error_count,
        ));

        return self::SUCCESS;
    }
}
