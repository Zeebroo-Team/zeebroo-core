<?php

namespace Modules\Ecommerce\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class EcommerceServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Ecommerce';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'ecommerce';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        RouteServiceProvider::class,
    ];
}
