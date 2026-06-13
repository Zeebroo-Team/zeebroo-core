<?php

namespace Modules\Documentation\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class DocumentationServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Documentation';
    protected string $nameLower = 'documentation';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
