<?php

namespace Modules\ProjectManage\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class ProjectManageServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'ProjectManage';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'projectmanage';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
