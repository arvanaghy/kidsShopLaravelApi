<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CustomerLoginNotification extends Notification
{
    use Queueable;

    public $customer;
    public $smsCode;

    /**
     * Create a new notification instance.
     */
    public function __construct($customer, $smsCode)
    {
        $this->customer = $customer;
        $this->smsCode = $smsCode;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [\App\Channels\SmsChannel::class];
    }

    public function toSms(object $notifiable)
    {
        return "کیدزشاپ.کدورود:{$this->smsCode}.https://kidsshop110.ir";
    }


    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'کد ورود به سیستم ارسال شد',
            'phone' => $this->customer->Mobile,
            'sms_code' => $this->smsCode,
        ];
    }
}
