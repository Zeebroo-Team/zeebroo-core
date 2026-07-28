<?php

namespace Modules\Developers\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class DevelopersServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Developers';
    protected string $nameLower = 'developers';

    protected array $providers = [
        RouteServiceProvider::class,
    ];
}
