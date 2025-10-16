<?php

namespace App\Listeners;

use App\Jobs\SendSmsJob;
use App\Repositories\CustomerRepository;

class SendEnquireConfirmSms
{

    protected $customerRepository = null;
    /**
     * Create the event listener.
     */
    public function __construct(CustomerRepository $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    /**
     * Handle the event.
     */
    public function handle($event): void
    {
        $userMessageTemplate = "کیدزشاپ.کاربرگرامی {$event->info} باتشکر از پیغام شما.پشتیبانی با شما تماس خواهد گرفت.https://kidsshop110.ir";
        SendSmsJob::dispatchSync($event->contact, $userMessageTemplate);

        $admins = $this->customerRepository->fetchAdminsList();
        if ($admins) {
            foreach ($admins as $admin) {
                $adminPhone = $admin->Mobile;
                $adminSmsText = "کیدزشاپ.پیغام از {$event->info} باشماره {$event->contact} به متن: {$event->message} .https://kidsshop110.ir";
                SendSmsJob::dispatchSync($adminPhone, $adminSmsText);
            }
        }
    }
}
