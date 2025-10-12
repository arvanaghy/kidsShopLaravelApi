<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Services\OrderAndPaymentService;
use App\Models\CustomerModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class OrderAndPaymentController extends Controller
{
    public function process(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'products' => 'required|array|min:1',
                'products.*.KCode' => 'required|numeric|exists:Kala,Code',
                'products.*.Tedad' => 'required|numeric|min:1',
                'products.*.ColorCode' => 'required',
                'products.*.SizeNum' => 'required',
                'products.*.RGB' => 'required',
                'description' => 'nullable|string|max:255',
                'CodeKhadamat' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors()->first(),
                    'result' => null
                ], 422);
            }

            $user = CustomerModel::where('UToken', $request->bearerToken())->first();
            if (!$user) {
                return response()->json([
                    'message' => 'کاربری با این توکن یافت نشد',
                    'result' => null
                ], 404);
            }

            $service = new OrderAndPaymentService();
            $redirectUrl = $service->processOrderAndPayment($user, $request->all());

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
