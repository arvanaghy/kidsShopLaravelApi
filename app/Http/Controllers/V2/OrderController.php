<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\CustomerModel;
use App\Models\OrderModel;
use App\Models\ProductModel;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    protected $active_company;
    protected $financial_period;

    /**
     * Initialize active company and financial period.
     */
    public function __construct()
    {
        try {
            $this->active_company = DB::table('Company')
                ->where('DeviceSelected', 1)
                ->value('Code');

            if ($this->active_company) {
                $this->financial_period = DB::table('DoreMali')
                    ->where('CodeCompany', $this->active_company)
                    ->where('DeviceSelected', 1)
                    ->value('Code');
            }

            if (!$this->active_company || !$this->financial_period) {
                throw new Exception('Invalid company or financial period configuration.');
            }
        } catch (Exception $e) {
            Log::error("OrderController initialization failed: {$e->getMessage()}");
            abort(500, trans('messages.server_error'));
        }
    }

    /**
     * Send SMS via WebOne API.
     */
    protected function sendSms(string $phone, string $message): bool
    {
        try {
            $response = Http::get('https://webone-sms.ir/SMSInOutBox/SendSms', [
                'username' => '09354278334',
                'password' => '414411',
                'from' => '10002147',
                'to'       => $phone,
                'text'     => $message,
            ]);

            if ($response->failed()) {
                throw new Exception('SMS API request failed: ' . $response->body());
            }

            return true;
        } catch (Exception $e) {
            Log::error("SMS sending failed for phone {$phone}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Submit a new order with transaction lock.
     */
    public function submitOrder(Request $request)
    {
        try {
            // Validate bearer token
            $token = $request->bearerToken();
            if (empty($token)) {
                return response()->json([
                    'message' => trans('messages.invalid_token'),
                    'result'  => null,
                ], 401);
            }

            // Fetch customer
            $customer = CustomerModel::where('UToken', $token)->first();
            if (!$customer) {
                return response()->json([
                    'message' => trans('messages.user_not_found'),
                    'result'  => null,
                ], 404);
            }

            // Validate request data
            $validated = $request->validate([
                'description'  => 'nullable',
                'CodeKhadamat' => 'nullable',
                'MKhadamat'    => 'nullable',
                'products'     => 'required',
                'products.*.KCode' => 'required',
                'products.*.basket' => 'required',
                'products.*.basket.*.quantity' => 'required',
                'products.*.basket.*.feature.Mablaq' => 'required',
                'products.*.basket.*.feature.SizeNum' => 'nullable',
                'products.*.basket.*.feature.ColorCode' => 'nullable',
            ]);

            // Process order within a transaction
            $order = DB::transaction(function () use ($customer, $validated) {
                // Create order
                $order = OrderModel::create([
                    'CCode'         => $customer->Code,
                    'CodeDoreMali'  => $this->financial_period,
                    'Comment'       => $validated['description'],
                    'CodeKhadamat'  => $validated['CodeKhadamat'],
                    'MKhadamat'     => $validated['MKhadamat'],
                ]);

                // Insert order items
                $orderItems = [];
                foreach ($validated['products'] as $productValue) {
                    // Verify product exists
                    $product = ProductModel::where('Code', $productValue['KCode'])->select('Code')->first();
                    if (!$product) {
                        throw new Exception(trans('messages.product_not_found'));
                    }

                    foreach ($productValue['basket'] as $basketValue) {
                        $orderItems[] = [
                            'SCode'      => $order->Code,
                            'KCode'      => $productValue['KCode'],
                            'Tedad'      => $basketValue['quantity'],
                            'Fee'        => $basketValue['feature']['Mablaq'],
                            'KTedad'     => 0,
                            'KMegdar'    => 0,
                            'KFee'       => 0,
                            'DTakhfif'   => 0,
                            'MTakhfif'   => 0,
                            'SizeNum'    => $basketValue['feature']['SizeNum'] ?? null,
                            'ColorCode'  => $basketValue['feature']['ColorCode'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                // Bulk insert order items
                DB::table('SOrderKala')->insert($orderItems);

                return $order;
            });

            // Fetch order details for response
            $orderDetails = OrderModel::with('items')->where('Code', $order->Code)->first();
            $totalPrice = DB::table('AV_SOrder_View')->where('Code', $order->Code)->value('JamKol') ?? 0;

            // Send SMS notifications
            $clientMessage = trans('messages.order_confirmation', [
                'name' => $customer->Name,
                'order_code' => $order->Code,
                'total' => $totalPrice,
            ]);
            $this->sendSms($customer->Mobile, $clientMessage);

            $adminMessage = trans('messages.new_order_notification', [
                'order_code' => $order->Code,
                'total' => $totalPrice,
                'name' => $customer->Name,
            ]);
            // $this->sendSms('09354278334', $adminMessage);

            return response()->json([
                'message' => trans('messages.order_created_successfully'),
                'result'  => $orderDetails,
            ], 201);
        } catch (Exception $e) {
            Log::error("Order submission failed: {$e->getMessage()}");
            return response()->json([
                'message' => $e->getMessage(),
                'result'  => null,
            ], 500);
        }
    }
}
