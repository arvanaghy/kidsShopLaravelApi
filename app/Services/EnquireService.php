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
            $userMessageTemplate = "کیدزشاپ.\n کاربر گرامی " . $info . " با تشکر از تماس شما، پیغام شما با موفقیت دریافت شد\n{env('FRONTEND_URL')}";
            SendSmsJob::dispatchSync($contact, $userMessageTemplate);

            $admins = $this->customerRepository->fetchAdminsList();
            if ($admins) {
                foreach ($admins as $admin) {
                    $adminPhone = $admin->Mobile;
                    $adminSmsText = "کیدزشاپ.\n مدیر . یک پیغام از " . $info . " - " . $contact . " - " . $message . "دارید\n{env('FRONTEND_URL')}";
                    SendSmsJob::dispatchSync($adminPhone, $adminSmsText);
                }
            }
        }
    }
}
