<?php

namespace App\Utilities;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentGatewayUtility
{
    protected static $ZARINPAL_MERCHANT_ID;
    protected static $ZARINPAL_API_VERIFICATION_URL;
    protected static $ZARINPAL_API_PURCHASE_URL;

    public function __construct()
    {
        self::$ZARINPAL_MERCHANT_ID = env('ZARINPAL_MERCHANT_ID');
        self::$ZARINPAL_API_VERIFICATION_URL = env('ZARINPAL_API_VERIFICATION_URL');
        self::$ZARINPAL_API_PURCHASE_URL = env('ZARINPAL_API_PURCHASE_URL');
    }

    private static function validateRequest($request): void
    {
        if (empty($request->TrID) || empty($request->Mablag) || empty($request->SCode)) {
            throw new Exception('پارامترهای مورد نیاز پرداخت موجود نیست');
        }
    }

    public static function checkThirdPartyPayment($request): array
    {
        self::validateRequest($request);

        $data = [
            'merchant_id' => self::$ZARINPAL_MERCHANT_ID,
            'authority' => $request->TrID,
            'amount' => (int) $request->Mablag,
        ];

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'ZarinPal Rest Api v4',
            ])->timeout(30)->post(self::$ZARINPAL_API_VERIFICATION_URL, $data);

            $result = $response->json();

            if (empty($result)) {
                throw new Exception('پاسخ نامعتبر از درگاه پرداخت');
            }

            return $result;
        } catch (\Exception $e) {
            throw new Exception('خطا در درگاه پرداخت: ' . $e->getMessage());
        }
    }

    public static function purchaseThirdPartyPayment($user, $order, $sOrder)
    {
        $paymentData = [
            'merchant_id' => self::$ZARINPAL_MERCHANT_ID,
            'amount' => (int)$sOrder->JamKK,
            'callback_url' => env('ZARINPAL_CALLBACK_URL_WEB'),
            'description' => "واریز کاربر {$user->Name} برای پیش فاکتور {$order->Code}",
            'metadata' => [
                'email' => $user->Email,
                'mobile' => $user->Mobile,
                'name' => $user->Name,
                'order_id' => (string)$order->Code
            ]
        ];

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'ZarinPal Rest Api v1',
            ])->post(self::$ZARINPAL_API_PURCHASE_URL, $paymentData);

            $result = $response->json();

            if ($response->failed()) {
                throw new Exception('خطا در زرین پال: ' . ($result['errors']['message'] ?? 'خطای ناشناخته'));
            }

            return $result;
        } catch (\Exception $e) {
            throw new Exception('خطا در زرین پال: ' . $e->getMessage(), 500);
        }
    }
}
