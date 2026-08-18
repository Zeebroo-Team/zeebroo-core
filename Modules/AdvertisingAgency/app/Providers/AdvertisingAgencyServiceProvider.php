<?php

namespace Modules\AdvertisingAgency\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class AdvertisingAgencyServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'AdvertisingAgency';

    protected string $nameLower = 'advertising-agency';

    protected array $providers = [
        AgencyEventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
