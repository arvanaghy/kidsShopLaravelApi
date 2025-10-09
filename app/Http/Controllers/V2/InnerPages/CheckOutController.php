<?php

namespace App\Http\Controllers\V2\InnerPages;

use App\Http\Controllers\Controller;
use App\Services\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckOutController extends Controller
{
    protected $checkoutService;
    protected $frontend_url = null;

    public function __construct(CheckoutService $checkoutService)
    {
        $this->checkoutService = $checkoutService;
        $this->frontend_url = env('FRONTEND_URL' ?? 'https://kidsshop110.ir');
    }

    public function paymentCallback(Request $request)
    {
        try {
            $result = $this->checkoutService->paymentCallback($request);
            return redirect()->away("{$this->frontend_url}/payment-success/{$result['data']['ref_id']}");
        } catch (\Exception $e) {
            Log::error('Payment callback failed: ' . $e->getMessage());
            return redirect()->away("{$this->frontend_url}/payment-failed?exception=" . urlencode($e->getMessage()))
                ->setStatusCode(400);
        }
    }

    public function paymentCallbackMobile(Request $request)
    {
        try {
            $result = $this->checkoutService->paymentCallback($request);
            return redirect()->away("{$this->frontend_url}/payment-success-mobile/{$result['data']['ref_id']}");
        } catch (\Exception $e) {
            Log::error('Payment callback failed: ' . $e->getMessage());
            return redirect()->away("{$this->frontend_url}/payment-failed-mobile?exception=" . urlencode($e->getMessage()))
                ->setStatusCode(400);
        }
    }
}
