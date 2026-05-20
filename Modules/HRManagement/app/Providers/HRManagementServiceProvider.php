<?php

namespace Modules\HRManagement\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Modules\HRManagement\Payroll\RegionalTemplates\IndianPayrollRegionalTemplate;
use Modules\HRManagement\Payroll\RegionalTemplates\LkTwentySixDayEpfWorksheetPayrollTemplate;
use Modules\HRManagement\Payroll\RegionalTemplates\PayrollRegionalTemplateInstallHelper;
use Modules\HRManagement\Payroll\RegionalTemplates\PayrollRegionalTemplateRegistry;
use Modules\HRManagement\Payroll\RegionalTemplates\SriLankanEmployeeStandardPayrollTemplate;
use Modules\Settings\Services\SettingsService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class HRManagementServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'HRManagement';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'hrmanagement';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

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
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(PayrollRegionalTemplateInstallHelper::class);

        $this->app->singleton(PayrollRegionalTemplateRegistry::class, static function ($app): PayrollRegionalTemplateRegistry {
            /** @var Application $app */
            return new PayrollRegionalTemplateRegistry([
                new SriLankanEmployeeStandardPayrollTemplate(
                    $app->make(SettingsService::class),
                    $app->make(PayrollRegionalTemplateInstallHelper::class),
                ),
                new IndianPayrollRegionalTemplate(
                    $app->make(SettingsService::class),
                    $app->make(PayrollRegionalTemplateInstallHelper::class),
                ),
                new LkTwentySixDayEpfWorksheetPayrollTemplate(
                    $app->make(SettingsService::class),
                    $app->make(PayrollRegionalTemplateInstallHelper::class),
                ),
            ]);
        });
    }
}
