<?php

namespace Modules\EventManagement\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class EventManagementServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'EventManagement';

    protected string $nameLower = 'event-management';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
