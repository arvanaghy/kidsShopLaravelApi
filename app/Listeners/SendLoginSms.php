<?php

namespace App\Listeners;

use App\Jobs\SendSmsJob;

class SendLoginSms
{
    /**
     * Create the event listener.
     */

    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle($event): void
    {
        SendSmsJob::dispatchSync($event->phone, "کیدزشاپ.کدورود:{$event->smsCode}.https://kidsshop110.ir");
    }
}
