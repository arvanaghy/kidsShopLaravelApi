<?php

namespace App\Helpers;

use Exception;
use Illuminate\Support\Facades\Http;

class PaymentGatewayUtility
{
    protected static $ZARINPAL_MERCHANT_ID;
    protected static $ZARINPAL_API_VERIFICATION_URL;
    protected static $ZARINPAL_API_PURCHASE_URL;

    // Initialize static properties directly
    protected static function initialize()
    {
        if (!self::$ZARINPAL_MERCHANT_ID) {
            self::$ZARINPAL_MERCHANT_ID = env('ZARINPAL_MERCHANT_ID', '87955b91-59e8-4753-af27-b2815b9c6b40');
            self::$ZARINPAL_API_VERIFICATION_URL = env('ZARINPAL_API_VERIFICATION_URL', 'https://api.zarinpal.com/pg/v4/payment/verify.json');
            self::$ZARINPAL_API_PURCHASE_URL = env('ZARINPAL_API_PURCHASE_URL', 'https://api.zarinpal.com/pg/v4/payment/request.json');
        }
    }

    private static function validateRequest($request): void
    {
        if (empty($request->TrID) || empty($request->Mablag) || empty($request->SCode)) {
            throw new Exception('پارامترهای مورد نیاز پرداخت موجود نیست');
        }
    }

    protected static function checkCurrencyUnit($JamKK, $currency_unit)
    {
        if ($currency_unit == 'تومان') {
            $amount = $JamKK * 10;
        } else {
            $amount = $JamKK;
        }

        return $amount;
    }

    public static function checkThirdPartyPayment($request, $currency_unit)
    {
        self::initialize();
        self::validateRequest($request);

        $amount = self::checkCurrencyUnit($request->Mablag, $currency_unit);

        $data = [
            'merchant_id' => self::$ZARINPAL_MERCHANT_ID,
            'authority' => $request->TrID,
            'amount' => (int) $amount,
        ];

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'ZarinPal Rest Api v4',
            ])->timeout(30)->post(self::$ZARINPAL_API_VERIFICATION_URL, $data);

            $result = $response->json();

            // if (empty($result)) {
            //     throw new Exception('پاسخ نامعتبر از درگاه پرداخت');
            // }

            // if (!empty($result['errors'])) {
            //     throw new Exception($result['errors']['message']);
            // }
            return $result;
        } catch (\Exception $e) {
            throw new Exception('خطا در پرداخت: ' . $e->getMessage());
        }
    }

    public static function purchaseThirdPartyPayment($user, $orderCode, $sOrder, $currency_unit)
    {
        self::initialize();

        $amount = self::checkCurrencyUnit($sOrder->JamKK, $currency_unit);

        $paymentData = [
            'merchant_id' => self::$ZARINPAL_MERCHANT_ID,
            'amount' => (int)$amount,
            'callback_url' => env('ZARINPAL_CALLBACK_URL_WEB'),
            'description' => "واریز کاربر {$user->Name} برای پیش فاکتور {$orderCode}",
            'metadata' => [
                'email' => $user->Email,
                'mobile' => $user->Mobile,
                'name' => $user->Name,
                'order_id' => (string)$orderCode
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
