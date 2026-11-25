<?php

namespace Modules\Package\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class PackageServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Package';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'package';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        \Modules\Package\Console\SendTrialReminders::class,
    ];

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
     * 
     * @param $schedule
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        // Emails owners whose free trial ends within 2 days (once per trial).
        // Expiry itself needs no job — the subscription middleware checks trial_ends_at live.
        $schedule->command(\Modules\Package\Console\SendTrialReminders::class)->hourly()->withoutOverlapping();
    }
}
