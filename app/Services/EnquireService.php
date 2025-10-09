<?php

namespace App\Services;

use App\Jobs\SendSmsJob;

class EnquireService
{
    public function __construct() {}

    // TODO: add admin (Owner) to send admin message
    public function send_enquiry($info = null, $contact = null, $message = null)
    {
        if ($info && $contact && is_numeric($contact) && $message) {
            $userMessageTemplate = 'کاربر گرامی ' . $info . ' با تشکر از تماس شما، پیغام شما با موفقیت دریافت شد';
            SendSmsJob::dispatchSync($contact, $userMessageTemplate);
            $adminMessageTemplate = 'یک پیغام از ' . $info . ' - ' . $contact . ' - ' . $message . 'دارید';
        }
    }
}
