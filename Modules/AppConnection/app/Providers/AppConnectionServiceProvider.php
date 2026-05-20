<?php

namespace Modules\AppConnection\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class AppConnectionServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'AppConnection';

    protected string $nameLower = 'appconnection';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
