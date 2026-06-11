<?php

namespace Modules\DesignStudio\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class DesignStudioServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'DesignStudio';

    protected string $nameLower = 'designstudio';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
