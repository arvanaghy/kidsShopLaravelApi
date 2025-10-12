<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected $baseUrl;
    protected $username;
    protected $password;
    protected $from;

    public function __construct()
    {
        $this->baseUrl = env('WEB_ONE_BASE_URL');
        $this->username = env('WEB_ONE_USERNAME');
        $this->password = env('WEB_ONE_PASSWORD');
        $this->from = env('WEB_ONE_FROM');
    }

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
