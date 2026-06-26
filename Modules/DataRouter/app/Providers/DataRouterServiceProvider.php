<?php

namespace Modules\DataRouter\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class DataRouterServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'DataRouter';
    protected string $nameLower = 'datarouter';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
