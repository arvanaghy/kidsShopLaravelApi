<?php

namespace App\Listeners;

use App\Jobs\SendSmsJob;
use App\Repositories\CustomerRepository;

class SendAdminNewMemberRegisteredSms
{
    /**
     * Create the event listener.
     */

    protected $customerRepository;
    public function __construct(CustomerRepository $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    /**
     * Handle the event.
     */
    public function handle($event): void
    {

        $admins = $this->customerRepository->fetchAdminsList();
        if ($admins) {
            $adminSmsText = "کیدزشاپ.کاربر{$event->name}باشماره تماس{$event->phone}ثبت نام کرد.https://kidsshop110.ir";
            foreach ($admins as $admin) {
                $adminPhone = $admin->Mobile;
                SendSmsJob::dispatchSync($adminPhone, $adminSmsText);
            }
        }
    }
}
