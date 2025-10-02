<?php

namespace App\Services;

use App\Models\OrderModel;
use App\Models\WebPaymentModel;
use Illuminate\Support\Facades\DB;
use Exception;

class OrderAndPaymentService
{
    protected $financialPeriod;

    public function __construct()
    {
        $active_company = DB::table('Company')->where('DeviceSelected', 1)->first();
        if ($active_company) {
            $this->financialPeriod = DB::table('DoreMali')
                ->where('CodeCompany', $active_company->Code)
                ->where('DeviceSelected', 1)
                ->first()->Code;
        }
    }

    public function processOrderAndPayment($user, $orderData)
    {
        return DB::transaction(function () use ($user, $orderData) {
            $transfer = DB::table('AV_KhadamatDevice_View')->where('Code', $orderData['CodeKhadamat'])->first();

            if (!$transfer) {
                throw new Exception("خطا در دریافت اطلاعات کد خدمات", 500);
            }

            $order = new OrderModel();
            $order->Code = OrderModel::max('Code') + 1;
            $order->CCode = $user->Code;
            $order->CodeDoreMali = $this->financialPeriod;
            $order->CodeKhadamat = $orderData['CodeKhadamat'];
            $order->MKhadamat = $transfer->Mablag ?? 0;
            $order->status = 'سفارش ثبت شده و در انتظار پرداخت می باشد';
            $order->save();

            $insertedCode = $order->Code ?? null;

            if (!$insertedCode) {
                throw new Exception("خطا در ساخت سفارش", 500);
            }

            foreach ($orderData['products'] as $value) {
                $product = DB::table('Kala')
                    ->select('KMegdar', 'SPrice', 'KhordePrice', 'OmdePrice', 'AgsatPrice', 'CheckPrice')
                    ->where('Code', $value['KCode'])
                    ->first();

                if (!$product) {
                    throw new Exception("محصول با کد {$value['KCode']} یافت نشد", 404);
                }

                DB::table('SOrderKala')->insert([
                    'SCode' => $insertedCode,
                    'KCode' => $value['KCode'],
                    'Tedad' => $value['Tedad'],
                    'Fee' => $product->SPrice,
                    'KTedad' => 0,
                    'KMegdar' => 0,
                    'KFee' => 0,
                    'DTakhfif' => 0,
                    'MTakhfif' => 0,
                    'SizeNum' => $value['SizeNum'],
                    'ColorCode' => $value['ColorCode'],
                    'RGB' => $value['RGB']
                ]);

            }

            // محاسبه مجموع سفارش
            $sOrder = DB::table('AV_SOrder_View')->where('Code', $insertedCode)->first();
            if (!$sOrder) {
                throw new Exception('خطا در محاسبه مجموع سفارش', 500);
            }

            // ارسال درخواست به زرین‌پال
            $paymentData = [
                'merchant_id' => '87955b91-59e8-4753-af27-b2815b9c6b40',
                'amount' => (int)$sOrder->JamKK,
                'callback_url' => 'https://api.kidsshop110.ir/api/v1/zarinpal-payment-callback',
                'description' => "واریز کاربر {$user->Name} برای پیش فاکتور {$insertedCode}",
                'metadata' => [
                    'email' => $user->Email,
                    'mobile' => $user->Mobile,
                    'name' => $user->Name,
                    'order_id' => (string)$insertedCode
                ]
            ];

            $response = $this->callZarinpalApi($paymentData);

            if (empty($response['errors']) && $response['data']['code'] == 100) {
                WebPaymentModel::create([
                    'TrID' => $response['data']['authority'],
                    'UUID' => $response['data']['authority'],
                    'SCode' => $insertedCode,
                    'CCode' => (float)$user->Code,
                    'Mablag' => (int)$sOrder->JamKK,
                    'Comment' => "واریز کاربر {$user->Name} برای پیش فاکتور {$insertedCode}",
                ]);

                // ارسال پیامک
                $this->sendSms($user->Mobile, "مشتری گرامی {$user->Name} پیش فاکتور {$insertedCode} به مبلغ {$sOrder->JamKK} در سیستم ثبت گردید.");
                $this->sendSms('09354278334', "پیش فاکتور جدید به شماره {$insertedCode} به مبلغ {$sOrder->JamKK} برای کاربر {$user->Name} ثبت گردید.");

                return "https://www.zarinpal.com/pg/StartPay/{$response['data']['authority']}";
            } else {
                throw new Exception($response['errors']['message'] ?? 'خطا در ارتباط با زرین پال', 500);
            }
        });
    }

    private function callZarinpalApi($data)
    {
        $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/request.json');
        curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v1');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen(json_encode($data))
        ]);

        $result = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('خطا در ارتباط با زرین پال: ' . $error, 500);
        }

        return json_decode($result, true);
    }

    private function sendSms($phone, $message)
    {
        $base_url = 'https://webone-sms.ir/SMSInOutBox/SendSms';
        $params = [
            'username' => '09354278334',
            'password' => '414411',
            'from' => '10002147',
            'text' => $message,
            'to' => $phone
        ];
        $url = $base_url . '?' . http_build_query($params);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }
}
