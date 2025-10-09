<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected $baseUrl = 'https://webone-sms.ir/SMSInOutBox/SendSms';
    protected $username = '09354278334';
    protected $password = '414411';
    protected $from = '10002147';

    public function send($phoneNumber, $message)
    {
        try {
            $response = Http::get($this->baseUrl, [
                'username' => $this->username,
                'password' => $this->password,
                'from' => $this->from,
                'to' => $phoneNumber,
                'text' => $message,
            ]);

            if ($response->successful()) {
                Log::info('SMS sent successfully to ' . $phoneNumber);
                return true;
            }

            Log::error('Failed to send SMS to ' . $phoneNumber . ': ' . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error('Error sending SMS to ' . $phoneNumber . ': ' . $e->getMessage());
            return false;
        }
    }
}
