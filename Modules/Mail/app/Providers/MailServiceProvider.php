<?php

namespace Modules\Mail\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Mail\Console\SendScheduledMails;
use Modules\Mail\Console\SyncMailboxes;
use Nwidart\Modules\Support\ModuleServiceProvider;

class MailServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Mail';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'mail';

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
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        SyncMailboxes::class,
        SendScheduledMails::class,
    ];

    /**
     * Poll every connected business mailbox every minute, and check for due
     * scheduled sends every minute. Requires the server's own cron to run
     * `php artisan schedule:run` every minute (or `php artisan schedule:work`
     * in local dev), plus a running queue worker to process the dispatched
     * SyncMailboxJob/SendScheduledMailJob jobs.
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command(SyncMailboxes::class)->everyMinute()->withoutOverlapping();
        $schedule->command(SendScheduledMails::class)->everyMinute()->withoutOverlapping();
    }
}
