<?php

namespace App\Services;

use App\Events\OrderProcessSubmittedEvent;
use App\Jobs\SendSmsJob;
use App\Models\OrderModel;
use App\Models\ProductModel;
use App\Models\WebPaymentModel;
use App\Repositories\CustomerRepository;
use App\Helpers\PaymentGatewayUtility;
use App\Repositories\GeneralRepository;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Log;

class OrderAndPaymentService
{
    protected $financialPeriod;
    protected $activeCompany;
    protected $customerRepository;
    protected $paymentGatewayUtility;
    protected $generalRepository;
    public function __construct(
        CustomerRepository $customerRepository,
        CompanyService $companyService,
        PaymentGatewayUtility $paymentGatewayUtility,
        GeneralRepository $generalRepository
    ) {
        $this->customerRepository = $customerRepository;
        $this->paymentGatewayUtility = $paymentGatewayUtility;
        $this->generalRepository = $generalRepository;
        $this->activeCompany = $companyService->getActiveCompany();
        if ($this->activeCompany) {
            $this->financialPeriod = $companyService->getFinancialPeriod($this->activeCompany);
        }
    }

    public function processOrderAndPayment($request): string
    {
        try {
            $user = $this->customerRepository->findByToken($request->bearerToken());
            $orderData = $request->all();

            return DB::transaction(function () use ($user, $orderData) {
                $this->checkProductsStack($orderData['products']);
                $transfer = $this->validateTransfer($orderData['CodeKhadamat']);
                $orderCode = $this->createOrder($user, $orderData, $transfer);
                $this->processOrderItems($orderCode, $orderData['products']);
                $sOrder = $this->calculateOrderTotal($orderCode);
                $paymentUrl = $this->initiatePayment($user, $orderCode, $sOrder);
                return $paymentUrl;
            });
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }

    private function validateTransfer($codeKhadamat)
    {
        $transfer = DB::table('AV_KhadamatDevice_View')->select('Mablag')->where('Code', $codeKhadamat)->first();
        if (!$transfer) {
            throw new Exception('خطا در دریافت اطلاعات کد خدمات', 500);
        }
        return $transfer;
    }

    private function createOrder($user, $orderData, $transfer)
    {
        try {
            OrderModel::create([
                'CCode' => $user->Code,
                'CodeDoreMali' => $this->financialPeriod ?? 1,
                'CodeKhadamat' => $orderData['CodeKhadamat'],
                'MKhadamat' => $transfer->Mablag ?? 0,
                'status' => 'سفارش ثبت شده و در انتظار پرداخت می باشد',
            ]);
            return OrderModel::where('CCode', $user->Code)->orderBy('Code', 'desc')->first()->Code;
        } catch (Exception $e) {
            throw new Exception('خطا در ایجاد سفارش' . '_' . '_' . $e->getMessage(), $e->getCode());
        }
    }

    private function checkProductsStack($products)
    {
        foreach ($products as $product) {
            $product = ProductModel::whereHas('productSizeColor', function ($query) use ($product) {
                $query->havingRaw('SUM(Mande) >= ?', [$product['Tedad']]);
                $query->where('CodeKala', $product['KCode']);
            })->first();

            if (!$product) {
                throw new Exception("موجودی محصول با کد {$product['KCode']} کافی نیست", 500);
            }
        }
    }

    private function processOrderItems($orderCode, $products)
    {

        if ($orderCode <= 0) {
            throw new Exception('کد سفارش نامعتبر است', 500);
        }
        foreach ($products as $value) {
            $product = DB::table('Kala')
                ->select('SPrice')
                ->where('Code', $value['KCode'])
                ->first();

            if (!$product) {
                throw new Exception("محصول با کد {$value['KCode']} یافت نشد", 404);
            }

            DB::table('SOrderKala')->insert([
                'SCode' => $orderCode,
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
    }

    private function calculateOrderTotal($orderCode)
    {
        $sOrder = DB::table('AV_SOrder_View')->where('Code', $orderCode)->first();
        if (!$sOrder) {
            throw new Exception('خطا در محاسبه مجموع سفارش', 500);
        }
        return $sOrder;
    }

    private function initiatePayment($user, $orderCode, $sOrder): string
    {

        $currency_unit = $this->generalRepository->getCurrencyUnit();
        $response = $this->paymentGatewayUtility->purchaseThirdPartyPayment($user, $orderCode, $sOrder, $currency_unit);

        if (empty($response['errors']) && $response['data']['code'] == 100) {
            WebPaymentModel::create([
                'TrID' => $response['data']['authority'],
                'UUID' => $response['data']['authority'],
                'SCode' => $orderCode,
                'CCode' => (float)$user->Code,
                'Mablag' => (int)$sOrder->JamKK,
                'Comment' => "واریز کاربر {$user->Name} برای پیش فاکتور {$orderCode}",
            ]);

            return env('ZARINPAL_API_PAYMENT_URL') . "{$response['data']['authority']}";
        }

        throw new Exception($response['errors']['message'] ?? 'خطا در ارتباط با زرین پال', 500);
    }
}
