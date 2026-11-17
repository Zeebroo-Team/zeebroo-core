<?php

namespace Modules\CRM\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [];

    /**
     * Notifications, follow-up scheduling and automation triggers for the CRM workflow events.
     *
     * @var array<int, class-string>
     */
    protected $subscribe = [
        \Modules\CRM\Listeners\CrmEventSubscriber::class,
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
