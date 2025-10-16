<?php

namespace App\Providers;

use App\Events\CustomerLoginEvent;
use App\Events\CustomerRegisteredEvent;
use App\Events\EnquireSubmittedEvent;
use App\Events\OrderProcessSubmittedEvent;
use App\Listeners\SendAdminNewMemberRegisteredSms;
use App\Listeners\SendEnquireConfirmSms;
use App\Listeners\SendLoginSms;
use App\Listeners\SendOrderConfirmSms;
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
        EnquireSubmittedEvent::class => [
            SendEnquireConfirmSms::class
        ],
        OrderProcessSubmittedEvent::class => [
            SendOrderConfirmSms::class
        ]
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
