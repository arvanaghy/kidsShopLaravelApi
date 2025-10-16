<?php

namespace App\Services;

use App\Events\OrderProcessSubmittedEvent;
use App\Models\PaymentsModel;
use App\Models\WebPaymentModel;
use App\Repositories\CustomerRepository;
use App\Repositories\GeneralRepository;
use App\Repositories\InvoiceRepository;
use App\Helpers\PaymentGatewayUtility;
use Exception;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    protected $active_company;
    protected $financial_period;
    protected $customerRepository;
    protected $invoiceRepository;
    protected $generalRepository;

    public function __construct(
        CompanyService $companyService,
        CustomerRepository $customerRepository,
        InvoiceRepository $invoiceRepository,
        GeneralRepository $generalRepository
    ) {
        $this->active_company = $companyService->getActiveCompany();
        $this->financial_period = $companyService->getFinancialPeriod($this->active_company);
        $this->customerRepository = $customerRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->generalRepository = $generalRepository;
    }


    public function paymentCallback($request): array
    {
        $paymentResult = WebPaymentModel::where('TrID', $request->Authority)->first();

        if (!$paymentResult) {
            throw new Exception('Transaction not found: ' . $request->Authority);
        }

        $thirdPartyResult = PaymentGatewayUtility::checkThirdPartyPayment($paymentResult);

        if (isset($thirdPartyResult['data']['code']) && $thirdPartyResult['data']['code'] == 100) {
            return DB::transaction(function () use ($paymentResult, $thirdPartyResult) {
                $customer = $this->customerRepository->findByCode($paymentResult->CCode);
                $bankAccount = $this->invoiceRepository->getBankAccount();
                $currencyUnit = $this->generalRepository->getCurrencyUnit();

                $paymentResult->update(['UUID' => $thirdPartyResult['data']['ref_id']]);

                DB::table('SOrder')
                    ->where('Code', $paymentResult->SCode)
                    ->update([
                        'CPardakht' => true,
                        'status' => 'سفارش ثبت شده، پرداخت مبلغ ' . $paymentResult->Mablag .
                            " {$currencyUnit} " .
                            '  با موفقیت انجام پذیرفته است',
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

                event(new OrderProcessSubmittedEvent($customer, $paymentResult->SCode, $paymentResult->Mablag));

                return $thirdPartyResult;
            });
        } else {
            DB::table('SOrder')
                ->where('Code', $paymentResult->SCode)
                ->update([
                    'CPardakht' => false,
                    'status' => 'سفارش ثبت شده، پرداخت با خطا همراه بوده است',
                ]);
            throw new Exception($thirdPartyResult['errors']['message'] ?? 'Payment verification failed', $thirdPartyResult['errors']['code'] ?? 500);
        }
    }
}
