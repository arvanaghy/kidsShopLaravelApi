<?php

namespace App\Services;

use App\Jobs\SendSmsJob;
use App\Models\OrderModel;
use App\Models\ProductModel;
use App\Models\WebPaymentModel;
use App\Repositories\CustomerRepository;
use App\Utilities\PaymentGatewayUtility;
use Illuminate\Support\Facades\DB;
use Exception;

class OrderAndPaymentService
{
    protected $financialPeriod;
    protected $activeCompany;
    protected $customerRepository;
    protected $paymentGatewayUtility;

    public function __construct(
        CustomerRepository $customerRepository,
        CompanyService $companyService,
        PaymentGatewayUtility $paymentGatewayUtility
    ) {
        $this->customerRepository = $customerRepository;
        $this->paymentGatewayUtility = $paymentGatewayUtility;
        $this->activeCompany = $companyService->getActiveCompany();
        $this->financialPeriod = $companyService->getFinancialPeriod($this->activeCompany);
    }

    public function processOrderAndPayment($request): string
    {
        $user = $this->customerRepository->findByToken($request->bearerToken());
        $orderData = $request->all();

        return DB::transaction(function () use ($user, $orderData) {
            $this->checkProductsStack($orderData['products']);
            $transfer = $this->validateTransfer($orderData['CodeKhadamat']);
            $order = $this->createOrder($user, $orderData, $transfer);
            $this->processOrderItems($order->Code, $orderData['products']);
            $sOrder = $this->calculateOrderTotal($order->Code);

            $paymentUrl = $this->initiatePayment($user, $order, $sOrder);

            $this->sendNotifications($user, $order->Code, $sOrder->JamKK);

            return $paymentUrl;
        });
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
        $order = new OrderModel();
        $order->Code = OrderModel::max('Code') + 1;
        $order->CCode = $user->Code;
        $order->CodeDoreMali = $this->financialPeriod;
        $order->CodeKhadamat = $orderData['CodeKhadamat'];
        $order->MKhadamat = $transfer->Mablag ?? 0;
        $order->status = 'سفارش ثبت شده و در انتظار پرداخت می باشد';
        $order->save();

        if (!$order->Code) {
            throw new Exception('خطا در ساخت سفارش', 500);
        }

        return $order;
    }

    private function checkProductsStack($products)
    {
        foreach ($products as $product) {
            $product = ProductModel::whereHas('productSizeColor', function ($query) use ($product) {
                $query->havingRaw('SUM(Mande) ', '>=', $product['Tedad']);
                $query->where('CodeKala', $product['KCode']);
            })->first();

            if (!$product) {
                throw new Exception("موجودی محصول با کد {$product['KCode']} کافی نیست", 500);
            }
        }
    }

    private function processOrderItems($orderCode, $products)
    {
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

    private function initiatePayment($user, $order, $sOrder): string
    {

        $response = $this->paymentGatewayUtility->purchaseThirdPartyPayment($user, $order, $sOrder);

        if (empty($response['errors']) && $response['data']['code'] == 100) {
            WebPaymentModel::create([
                'TrID' => $response['data']['authority'],
                'UUID' => $response['data']['authority'],
                'SCode' => $order->Code,
                'CCode' => (float)$user->Code,
                'Mablag' => (int)$sOrder->JamKK,
                'Comment' => "واریز کاربر {$user->Name} برای پیش فاکتور {$order->Code}",
            ]);

            return env('ZARINPAL_API_PAYMENT_URL')."{$response['data']['authority']}";
        }

        throw new Exception($response['errors']['message'] ?? 'خطا در ارتباط با زرین پال', 500);
    }

    private function sendNotifications($user, $orderCode, $amount)
    {

        $customerSmsText = "مشتری گرامی {$user->Name} پیش فاکتور {$orderCode} به مبلغ {$amount} در سیستم ثبت گردید.";
        SendSmsJob::dispatchSync($user->Mobile, $customerSmsText);

        $adminsList  = $this->customerRepository->fetchAdminsList();
        if ($adminsList) {
            foreach ($adminsList as $admin) {
                $adminPhone = $admin->Mobile;
                $adminSmsText = "پیش فاکتور جدید به شماره {$orderCode} به مبلغ {$amount} برای کاربر {$user->Name} ثبت گردید.";
                SendSmsJob::dispatchSync($adminPhone, $adminSmsText);
            }
        }
    }
}
