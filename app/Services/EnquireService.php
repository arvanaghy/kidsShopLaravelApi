<?php

namespace App\Services;

use App\Jobs\SendSmsJob;
use App\Repositories\CustomerRepository;

class EnquireService
{
    protected $customerRepository;
    public function __construct(
        CustomerRepository $customerRepository
    ) {
        $this->customerRepository = $customerRepository;
    }

    public function send_enquiry($info = null, $contact = null, $message = null)
    {
        if ($info && $contact && is_numeric($contact) && $message) {
            $userMessageTemplate = "کیدزشاپ.کاربرگرامی {$info} باتشکر از پیغام شما.پشتیبانی با شما تماس خواهد گرفت.https://kidsshop110.ir";
            SendSmsJob::dispatchSync($contact, $userMessageTemplate);

            $admins = $this->customerRepository->fetchAdminsList();
            if ($admins) {
                foreach ($admins as $admin) {
                    $adminPhone = $admin->Mobile;
                    $adminSmsText = "کیدزشاپ.پیغام از {$info} باشماره {$contact} به متن: {$message} .https://kidsshop110.ir";
                    SendSmsJob::dispatchSync($adminPhone, $adminSmsText);
                }
            }
        }
    }
}
