<?php

namespace Modules\CRM\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Modules\CRM\Models\Lead;
use Modules\CRM\Policies\LeadPolicy;
use Modules\CRM\Services\CustomerService;
use Modules\CRM\Services\Timeline;
use Modules\Pos\Models\Customer;

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
        \Modules\CRM\Console\Commands\NormalizeCustomerPhonesCommand::class,
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

        // A lead's unified timeline; other modules add sources with extend() (see TimelineSource)
        $this->app->singleton(Timeline\TimelineService::class, fn ($app) => new Timeline\TimelineService([
            new Timeline\ActivityTimelineSource(),
            new Timeline\StageTimelineSource(),
            new Timeline\AssignmentTimelineSource($app->make(\Modules\CRM\Services\LeadAssignmentService::class)),
            new Timeline\MessageTimelineSource(),
            new Timeline\AttachmentTimelineSource(),
            new Timeline\TaskTimelineSource(),
            new Timeline\QuotationTimelineSource(),
        ]));

        // Sending approved lead quotations; more channels (WhatsApp, …) are added with extend()
        $this->app->singleton(\Modules\CRM\Services\QuotationDispatch\QuotationDispatcher::class, fn ($app) => new \Modules\CRM\Services\QuotationDispatch\QuotationDispatcher(
            $app->make(\Modules\Sales\Services\QuotationPrintService::class),
            $app->make(\Modules\CRM\Services\LeadQuotationService::class),
            [$app->make(\Modules\CRM\Services\QuotationDispatch\EmailQuotationChannel::class)],
        ));
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Lead::class, LeadPolicy::class);

        // Every write path of pos_customers (web, desktop, POS sync, lead convert) keeps the
        // normalised phone the duplicate check matches on
        Customer::saving(fn (Customer $customer) => $this->app->make(CustomerService::class)->normalizeContact($customer));
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
