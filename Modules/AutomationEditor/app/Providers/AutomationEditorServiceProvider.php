<?php

namespace Modules\AutomationEditor\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\AutomationEditor\Console\Commands\DetectExpiredChequesCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class AutomationEditorServiceProvider extends ModuleServiceProvider
{
    protected string $name      = 'AutomationEditor';
    protected string $nameLower = 'automationeditor';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    protected array $commands = [
        DetectExpiredChequesCommand::class,
    ];

    public function boot(): void
    {
        parent::boot();

        $this->app->booted(function () {
            $this->app->make(Schedule::class)
                ->command('automation:detect-expired-cheques')
                ->dailyAt('07:00');
        });
    }
}
