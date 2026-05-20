<?php

namespace Modules\Transaction\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Transaction\Services\LoanRecurringDeductionService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class TransactionServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Transaction';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'transaction';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Define module schedules.
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->call(static function (): void {
            app(LoanRecurringDeductionService::class)->run();
        })
            ->name('loan-recurring-deductions')
            ->dailyAt('00:10')
            ->withoutOverlapping();
    }
}
