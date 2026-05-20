<?php

namespace Modules\Settings\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Modules\Business\Models\Business;
use Modules\Settings\Observers\BusinessObserver;
use Modules\Settings\Observers\UserObserver;
use Modules\Settings\Services\SettingsService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class SettingsServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Settings';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'settings';

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

    public function register(): void
    {
        parent::register();

        $this->app->singleton(SettingsService::class, fn () => new SettingsService());
    }

    public function boot(): void
    {
        parent::boot();
        User::observe(UserObserver::class);
        Business::observe(BusinessObserver::class);

        View::composer('*', function ($view): void {
            $user = Auth::user();

            if (!$user instanceof User) {
                return;
            }

            /** @var SettingsService $service */
            $service = app(SettingsService::class);
            $business = $user->businesses()->latest()->first();

            $view->with('currentUserSettings', $service->allForScope($user));
            $view->with('currentBusinessSettings', $business ? $service->allForScope($business) : collect());
        });
    }
}
