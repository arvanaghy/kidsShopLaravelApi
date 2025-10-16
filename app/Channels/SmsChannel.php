<?php

namespace App\Channels;

use App\Jobs\SendSmsJob;
use Illuminate\Notifications\Notification;

class SmsChannel
{
    /**
     * ارسال نوتیفیکیشن از طریق پیامک
     */
    public function send($notifiable, Notification $notification)
    {
        $message = $notification->toSms($notifiable);

        if ($message) {
            SendSmsJob::dispatch($notifiable->Mobile, $message);
        }
    }
}
