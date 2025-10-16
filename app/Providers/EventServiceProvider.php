<?php

namespace App\Providers;

use App\Events\CustomerLoginEvent;
use App\Events\CustomerRegisteredEvent;
use App\Listeners\SendAdminNewMemberRegisteredSms;
use App\Listeners\SendLoginSms;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        CustomerLoginEvent::class => [
            SendLoginSms::class
        ],
        CustomerRegisteredEvent::class => [
            SendLoginSms::class,
            SendAdminNewMemberRegisteredSms::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
