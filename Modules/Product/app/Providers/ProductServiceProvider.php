<?php

namespace Modules\Product\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class ProductServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Product';

    protected string $nameLower = 'product';

    /**
     * @var string[]
     */
    protected array $commands = [
        \Modules\Product\Console\Commands\InsertDemoProductsCommand::class,
        \Modules\Product\Console\Commands\TestDataFeedCommand::class,
    ];

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
