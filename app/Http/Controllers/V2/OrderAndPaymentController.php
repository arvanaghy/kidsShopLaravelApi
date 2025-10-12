<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderPaymentRequest;
use App\Services\OrderAndPaymentService;
use Exception;

class OrderAndPaymentController extends Controller
{

    protected $orderAndPaymentService;

    public function __construct(OrderAndPaymentService $orderAndPaymentService)
    {
        $this->orderAndPaymentService = $orderAndPaymentService;
    }
    public function processOrderAndPayment(OrderPaymentRequest $request)
    {
        try {

            $redirectUrl = $this->orderAndPaymentService->processOrderAndPayment($request);

            return response()->json([
                'message' => 'در حال انتقال به درگاه پرداخت',
                'result' => ['redirect_url' => $redirectUrl]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'result' => null
            ], 500);
        }
    }
}
