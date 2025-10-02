<?php

namespace App\Services;

use App\Models\WebPaymentModel;
use Illuminate\Support\Facades\DB;
use Exception;

class PaymentService
{
    public function verifyZarinpalPayment($authority, $successUrl, $failedUrl)
    {
        try {
            $payment = WebPaymentModel::where('TrID', $authority)->first();
            if (!$payment) {
                throw new Exception('تراکنش یافت نشد', 404);
            }

            $data = [
                'merchant_id' => config('payment.zarinpal.merchant_id', '87955b91-59e8-4753-af27-b2815b9c6b40'),
                'authority' => $authority,
                'amount' => $payment->Mablag
            ];

            $ch = curl_init(config('payment.zarinpal.verify_url', 'https://api.zarinpal.com/pg/v4/payment/verify.json'));
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

            $result = json_decode($result, true);

            if (!empty($result['errors']) || $result['data']['code'] != 100) {
                $payment->update(['status' => 'failed']);
                throw new Exception($result['errors']['message'] ?? 'خطا در تأیید پرداخت', 400);
            }

            $payment->update(['status' => 'completed']);
            if ($payment->SCode) {
                DB::table('Order')->where('Code', $payment->SCode)->update(['status' => 'confirmed']);
            }

            return redirect($successUrl);
        } catch (Exception $e) {
            return redirect($failedUrl . '?exception=' . urlencode($e->getMessage()));
        }
    }
}
