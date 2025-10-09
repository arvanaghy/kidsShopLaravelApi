<?php

namespace App\Services;

use App\Models\PaymentsModel;
use App\Models\WebPaymentModel;
use App\Repositories\CustomerRepository;
use App\Repositories\InvoiceRepository;
use Exception;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    private const ZARINPAL_API_URL = 'https://api.zarinpal.com/pg/v4/payment/verify.json';
    private const MERCHANT_ID = '87955b91-59e8-4753-af27-b2815b9c6b40';

    protected $active_company;
    protected $financial_period;
    protected $customerRepository;
    protected $invoiceRepository;

    public function __construct(
        CompanyService $companyService,
        CustomerRepository $customerRepository,
        InvoiceRepository $invoiceRepository
    ) {
        $this->active_company = $companyService->getActiveCompany();
        $this->financial_period = $companyService->getFinancialPeriod($this->active_company);
        $this->customerRepository = $customerRepository;
        $this->invoiceRepository = $invoiceRepository;
    }

    private function checkThirdPartyPayment($request): array
    {
        $this->validateRequest($request);

        $data = [
            'merchant_id' => self::MERCHANT_ID,
            'authority' => $request->TrID,
            'amount' => (int) $request->Mablag,
        ];

        $ch = curl_init(self::ZARINPAL_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_USERAGENT => 'ZarinPal Rest Api v4',
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen(json_encode($data)),
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception('Payment gateway error: ' . curl_error($ch));
        }

        curl_close($ch);
        $result = json_decode($result, true);

        if (empty($result)) {
            throw new Exception('Invalid response from payment gateway');
        }

        return $result;
    }

    private function validateRequest($request): void
    {
        if (empty($request->TrID) || empty($request->Mablag) || empty($request->SCode)) {
            throw new Exception('Missing required payment parameters');
        }
    }


    public function paymentCallback($request): array
    {
        $paymentResult = WebPaymentModel::where('TrID', $request->Authority)->first();

        if (!$paymentResult) {
            throw new Exception('Transaction not found: ' . $request->Authority);
        }

        $thirdPartyResult = $this->checkThirdPartyPayment($paymentResult);

        return DB::transaction(function () use ($paymentResult, $thirdPartyResult) {
            $customer = $this->customerRepository->findByCode($paymentResult->CCode);
            $bankAccount = $this->invoiceRepository->getBankAccount();

            if (isset($thirdPartyResult['data']['code']) && $thirdPartyResult['data']['code'] == 100) {
                $paymentResult->update(['UUID' => $thirdPartyResult['data']['ref_id']]);

                DB::table('SOrder')
                    ->where('Code', $paymentResult->SCode)
                    ->update([
                        'CPardakht' => true,
                        'status' => 'سفارش ثبت شده و پرداخت شده است',
                    ]);

                PaymentsModel::create([
                    'Code' => (float) PaymentsModel::where('CodeDoreMali', $this->financial_period)->max('Code') + 1,
                    'CodeCompany' => $this->active_company,
                    'CodeDoreMali' => $this->financial_period,
                    'Index1' => 0,
                    'Index2' => 1,
                    'SIndex1' => 'طرف حساب',
                    'SIndex2' => 'بانک',
                    'Code1' => $paymentResult->CCode,
                    'Code2' => $bankAccount->Code,
                    'SDaryaft' => $customer->Name,
                    'SPardakht' => "{$bankAccount->BankName} - {$bankAccount->ShHesab}",
                    'Mablag' => $paymentResult->Mablag,
                    'Babat' => "{$paymentResult->Comment} با کد رهگیری {$thirdPartyResult['data']['ref_id']}",
                ]);

                return $thirdPartyResult;
            }

            DB::table('SOrder')
                ->where('Code', $paymentResult->SCode)
                ->update([
                    'CPardakht' => true,
                    'status' => 'سفارش ثبت شده، پرداخت با خطا همراه بوده است',
                ]);

            throw new Exception($thirdPartyResult['errors']['message'] ?? 'Payment verification failed', $thirdPartyResult['errors']['code'] ?? 500);
        });
    }
}
