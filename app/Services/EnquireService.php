<?php

namespace App\Services;

use App\Events\EnquireSubmittedEvent;
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
            event(new EnquireSubmittedEvent($info, $contact, $message));
        }
    }
}
