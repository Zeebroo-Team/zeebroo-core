<?php

namespace Modules\CRM\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Modules\CRM\Models\Lead;
use Modules\CRM\Policies\LeadPolicy;

class CRMServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'CRM';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'crm';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        \Modules\CRM\Console\Commands\MapStageStatusesCommand::class,
        \Modules\CRM\Console\Commands\NormalizeLeadPhonesCommand::class,
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

    public function register(): void
    {
        parent::register();

        // Caches each business's settings for the request / queued job
        $this->app->scoped(\Modules\CRM\Services\CrmSettingsService::class);
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Lead::class, LeadPolicy::class);
    }

    /**
     * Define module schedules.
     * 
     * @param $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
