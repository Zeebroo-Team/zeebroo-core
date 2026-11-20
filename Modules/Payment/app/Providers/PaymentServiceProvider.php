<?php

namespace Modules\Payment\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Payment\Console\RunPaymentCheck;
use Nwidart\Modules\Support\ModuleServiceProvider;

class PaymentServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Payment';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'payment';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        RouteServiceProvider::class,
    ];

    protected array $commands = [
        RunPaymentCheck::class,
    ];

    /**
     * The Stripe payment check ticks every minute but only actually runs once
     * the admin-set interval (Admin › Payment Checks, default 20 min) has
     * elapsed. Requires the server cron to run `php artisan schedule:run`
     * every minute (or `php artisan schedule:work` locally).
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command(RunPaymentCheck::class)->everyMinute()->withoutOverlapping(15);
    }
}
