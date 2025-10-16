<?php

namespace App\Listeners;

use App\Events\OrderProcessSubmittedEvent;
use App\Jobs\SendSmsJob;
use App\Repositories\CustomerRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendOrderConfirmSms
{
    protected $customerRepository;
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
    public function handle(OrderProcessSubmittedEvent $event): void
    {
        $customerSmsText = "کیدزشاپ.مشتری گرامی {$event->user->Name} پیش فاکتور {$event->orderCode} خرید شما به مبلغ {$event->amount} درسیستم ثبت شد.https://kidsshop110.ir";
        SendSmsJob::dispatchSync($user->Mobile, $customerSmsText);

        $adminsList  = $this->customerRepository->fetchAdminsList();
        if ($adminsList) {
            foreach ($adminsList as $admin) {
                $adminPhone = $admin->Mobile;
                $adminSmsText = "کیدزشاپ.پیشفاکتور {$event->orderCode} به مبلغ {$event->amount} برای {$event->user->Name} ثبتگردید.https://kidsshop110.ir";
                SendSmsJob::dispatchSync($adminPhone, $adminSmsText);
            }
        }
    }
}
