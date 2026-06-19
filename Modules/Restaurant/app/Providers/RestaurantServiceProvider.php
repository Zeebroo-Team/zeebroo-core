<?php

namespace Modules\Restaurant\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class RestaurantServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Restaurant';
    protected string $nameLower = 'restaurant';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
