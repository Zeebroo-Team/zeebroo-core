<?php

namespace Modules\Modification\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class ModificationServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Modification';

    protected string $nameLower = 'modification';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
