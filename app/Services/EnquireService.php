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
            $userMessageTemplate = 'کیدزشاپ. کاربر گرامی ' . $info . ' با تشکر از تماس شما، پیغام شما با موفقیت دریافت شد';
            SendSmsJob::dispatchSync($contact, $userMessageTemplate);

            $admins = $this->customerRepository->fetchAdminsList();
            if ($admins) {
                foreach ($admins as $admin) {
                    $adminPhone = $admin->Mobile;
                    $adminSmsText = 'کیدزشاپ. مدیر . یک پیغام از ' . $info . ' - ' . $contact . ' - ' . $message . 'دارید';
                    SendSmsJob::dispatchSync($adminPhone, $adminSmsText);
                }
            }
        }
    }
}
