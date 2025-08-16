<?php

namespace App\Services;

class SmsService
{
    public ?string $phone_number = null;
    public ?string $text = null;
    public bool $is_notice_to_admin = false;
    public ?string $admin_text = null;

    public function __construct(
        $phone_number,
        $text,
        $is_notice_to_admin = false,
        $admin_text = null
    ) {
        $this->phone_number = $phone_number;
        $this->text = $text;
        $this->is_notice_to_admin = $is_notice_to_admin;
        $this->admin_text = $admin_text;

        if ($this->is_notice_to_admin && $this->admin_text) {
            $this->send_to_admin();
        }
    }

    public function send(): array
    {
        try {
            $base_url = config('smspanel.web_one.base_url');
            $params = array(
                'username' => config('smspanel.web_one.username'),
                'password' => config('smspanel.web_one.password'),
                'from' => config('smspanel.web_one.from'),
                'text' => $this->text,
                'to' => $this->phone_number
            );
            $url = $base_url . '?' . http_build_query($params);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            curl_exec($ch);
            curl_close($ch);

            return ['status' => 'success', 'message' => 'پیام  با موفقیت ارسال شد'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function send_to_admin(): array
    {
        try {
            $base_url = config('smspanel.web_one.base_url');
            $params = array(
                'username' => config('smspanel.web_one.username'),
                'password' => config('smspanel.web_one.password'),
                'from' => config('smspanel.web_one.from'),
                'text' => $this->admin_text,
                'to' => config('smspanel.web_one.admin_phone_number')
            );
            $url = $base_url . '?' . http_build_query($params);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            curl_exec($ch);
            curl_close($ch);

            return ['status' => 'success', 'message' => 'پیام  با موفقیت ارسال شد'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
