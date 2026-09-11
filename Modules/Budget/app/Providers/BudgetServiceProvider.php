<?php

namespace Modules\Budget\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class BudgetServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Budget';

    protected string $nameLower = 'budget';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
