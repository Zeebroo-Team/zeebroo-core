<?php

namespace Modules\Service\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class ServiceServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Service';
    protected string $nameLower = 'service';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
