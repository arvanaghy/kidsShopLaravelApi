<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Shetabit\Multipay\Invoice;
use Shetabit\Payment\Facade\Payment;
use App\Models\CustomerModel;
use App\Models\WebPaymentModel;
use Exception;

class WebPaymentController extends Controller
{
    protected $active_company = null;
    protected $financial_period = null;

    public function __construct()
    {
        $active_company = DB::table('Company')->where('DeviceSelected', 1)->first();
        if ($active_company) {
            $this->active_company = $active_company->Code;
            $active_financial_period = DB::table('DoreMali')->where('CodeCompany', $active_company->Code)->where('DeviceSelected', 1)->first();
            if ($active_financial_period) {
                $this->financial_period = $active_financial_period->Code;
            }
        }
    }

    public function checkoutWithOrder(Request $request)
    {

        if ($request->BearerToken and $request->BearerToken != null and $request->orderCode and $request->orderCode != null) {
            $userResult = CustomerModel::where('UToken', $request->BearerToken)->first();
            if (!$userResult) {
                return response()->json(['message' => 'کاربری با این توکن یافت نشد', 'result' => null], 404);
            } else if ($userResult) {
                $Sorder  = DB::table('AV_SOrder_View')->where('Code', $request->orderCode)->first();
                if (!$Sorder) {
                    return response()->json(['message' => 'سفارشی با این کد وجود ندارد', 'result' => null], 404);
                }
                try {
                    $data = array(
                        "merchant_id" => "87955b91-59e8-4753-af27-b2815b9c6b40",
                        "amount" => (int)$Sorder->JamKK,
                        "callback_url" => "https://api.kidsshop110.ir/api/v1/zarinpal-payment-callback",
                        "description" => 'واریز کاربر ' . $userResult->Name . ' برای پیش فاکتور ' . $request->orderCode,
                        "metadata" => ["email" => $userResult->Email, "mobile" => $userResult->Mobile, "name" => $userResult->Name, "order_id" => $request->orderCode]
                    );
                    $jsonData = json_encode($data);

                    $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/request.json');
                    curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v1');
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen($jsonData)
                    ));

                    $result = curl_exec($ch);
                    $err = curl_error($ch);
                    $result = json_decode($result, true, JSON_PRETTY_PRINT);
                    curl_close($ch);

                    if ($err) {
                        return response()->json(['message' => '
                            خطا در ارتباط  با زرین پال 
                            ' .  $err, 'result' => null], 500);
                    } else {
                        if (empty($result['errors'])) {
                            if ($result['data']['code'] == 100) {

                                $webPayment = new WebPaymentModel();
                                $webPayment->TrID = $result['data']['authority'];
                                $webPayment->UUID = $result['data']['authority'];
                                $webPayment->SCode = (int)$request->orderCode;
                                $webPayment->CCode = (float)$userResult->Code;
                                $webPayment->Mablag = (int)$Sorder->JamKK;
                                $webPayment->Comment = 'واریز کاربر ' . $userResult->Name . 'برای پیش فاکتور ' . $request->orderCode;
                                $webPayment->Save();

                                header('Location: https://www.zarinpal.com/pg/StartPay/' . $result['data']["authority"]);
                            }
                        } else {
                            return response()->json([
                                'message' => $result['errors']['message'],
                                'result' => null
                            ], $result['errors']['code']);
                        }
                    }
                } catch (Exception $e) {
                    return response()->json([
                        'message' => $e->getMessage(),
                        'result' => null
                    ], 500);
                    var_dump($e->getMessage());
                }
            } else {
                return redirect('https://kidsshop110.ir/payment-failed');
            }
        } else {
            return redirect('https://kidsshop110.ir/payment-failed');
        }
    }

    public function checkoutWithoutOrder(Request $request)
    {
        if ($request->BearerToken and $request->BearerToken != null and $request->amount and $request->amount > 0) {
            $userResult = CustomerModel::where('UToken', $request->BearerToken)->first();

            if ($request->description and $request->description != null and $request->description != "") {
                $description = " پرداخت دستگاهی  " . $request->description;
            } else {
                $description = " پرداخت دستگاهی کاربر " . $userResult->Name . " به مبلغ " . $userResult->amount;
            }
            if ($userResult) {
                try {
                    $invoice = new Invoice;
                    $invoice->amount((int)$request->amount);
                    $invoice->detail(
                        'description',
                        $description
                    );
                    $id = WebPaymentModel::create([
                        'SCode' => (int)0,
                        'CCode' => (float)($userResult->Code),
                        'TrID' => $invoice->getTransactionId(),
                        'UUID' => $invoice->getUuid(),
                        'Comment' => $invoice->getDetails()['description'],
                        'Mablag' => $invoice->getAmount(),
                    ]);
                    return Payment::callbackUrl('https://kidsshop110.ir/web-payment')->purchase(
                        $invoice,
                        function ($driver, $transactionId) use ($id) {
                            WebPaymentModel::where('UUID', $id->UUID)->update([
                                'TrID' => $transactionId
                            ]);
                        }
                    )->pay()->render();
                } catch (Exception $e) {
                    return redirect('https://kidsshop110.ir/payment-failed');
                }
            } else {
                return redirect('https://kidsshop110.ir/payment-failed');
            }
        } else {
            return redirect('https://kidsshop110.ir/payment-failed');
        }
    }

    public function checkoutWithOrderMobile(Request $request)
    {

        if ($request->BearerToken and $request->BearerToken != null and $request->orderCode and $request->orderCode != null) {
            $userResult = CustomerModel::where('UToken', $request->BearerToken)->first();
            if (!$userResult) {
                return redirect('https://kidsshop110.ir/payment-failed-mobile?exception=' . urlencode('کاربری با این توکن وجود ندارد'));
            } else if ($userResult) {

                $Sorder  = DB::table('AV_SOrder_View')->where('Code', $request->orderCode)->first();
                if (!$Sorder) {
                    return response()->json(['message' => 'سفارشی با این کد وجود ندارد', 'result' => null], 404);
                }
                try {
                    $data = array(
                        "merchant_id" => "87955b91-59e8-4753-af27-b2815b9c6b40",
                        "amount" => (int)$Sorder->JamKK,
                        "callback_url" => "https://api.kidsshop.ir/api/v1/zarinpal-payment-callback-mobile",
                        "description" => 'واریز کاربر ' . $userResult->Name . ' برای پیش فاکتور ' . $request->orderCode,
                        "metadata" => ["email" => $userResult->Email, "mobile" => $userResult->Mobile, "name" => $userResult->Name, "order_id" => $request->orderCode]
                    );
                    $jsonData = json_encode($data);

                    $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/request.json');
                    curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v1');
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen($jsonData)
                    ));

                    $result = curl_exec($ch);
                    $err = curl_error($ch);
                    $result = json_decode($result, true, JSON_PRETTY_PRINT);
                    curl_close($ch);

                    if ($err) {
                        return redirect('https://kidsshop110.ir/payment-failed-mobile?exception=' . urlencode('خطا در ارتباط  با زرین پال ' .  $err));
                    } else {
                        if (empty($result['errors'])) {
                            if ($result['data']['code'] == 100) {

                                $webPayment = new WebPaymentModel();
                                $webPayment->TrID = $result['data']['authority'];
                                $webPayment->UUID = $result['data']['authority'];
                                $webPayment->SCode = (int)$request->orderCode;
                                $webPayment->CCode = (float)$userResult->Code;
                                $webPayment->Mablag = (int)$Sorder->JamKK;
                                $webPayment->Comment = 'واریز کاربر ' . $userResult->Name . 'برای پیش فاکتور ' . $request->orderCode;
                                $webPayment->Save();

                                header('Location: https://www.zarinpal.com/pg/StartPay/' . $result['data']["authority"]);
                            }
                        } else {
                            return redirect('https://kidsshop110.ir/payment-failed-mobile?exception=' . urlencode($result['errors']['message']));
                        }
                    }
                } catch (Exception $e) {
                    return redirect('https://kidsshop110.ir/payment-failed-mobile?exception=' . urlencode($e->getMessage()));
                }
            }
        } else {
            return redirect('https://kidsshop110.ir/payment-failed-mobile?exception=' . urlencode('کاربری با این توکن یافت نشد'));
        }
    }

    public function checkoutWithoutOrderMobile(Request $request)
    {
        if ($request->BearerToken and $request->BearerToken != null and $request->amount and $request->amount > 0) {
            $userResult = CustomerModel::where('UToken', $request->BearerToken)->first();

            if ($request->description and $request->description != null and $request->description != "") {
                $description = " پرداخت دستگاهی  " . $request->description;
            } else {
                $description = " پرداخت دستگاهی کاربر " . $userResult->Name . " به مبلغ " . $request->amount;
            }
            if ($userResult) {
                try {
                    $data = array(
                        "merchant_id" => "87955b91-59e8-4753-af27-b2815b9c6b40",
                        "amount" => $request->amount,
                        "callback_url" => "https://api.kidsshop110.ir/api/v1/zarinpal-payment-callback-mobile",
                        "description" => 'واریز کاربر ' . $userResult->Name . ' به مبلغ مستقیم ' . $request->amount,
                        "metadata" => ["email" => $userResult->Email, "mobile" => $userResult->Mobile, "name" => $userResult->Name]
                    );
                    $jsonData = json_encode($data);

                    $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/request.json');
                    curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v1');
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen($jsonData)
                    ));

                    $result = curl_exec($ch);
                    $err = curl_error($ch);
                    $result = json_decode($result, true, JSON_PRETTY_PRINT);
                    curl_close($ch);

                    if ($err) {
                        return response()->json(['message' => '
                        خطا در ارتباط  با زرین پال 
                        ' .  $err, 'result' => null], 500);
                    } else {
                        if (empty($result['errors'])) {
                            if ($result['data']['code'] == 100) {

                                $webPayment = new WebPaymentModel();
                                $webPayment->TrID = $result['data']['authority'];
                                $webPayment->UUID = $result['data']['authority'];
                                $webPayment->SCode = (int)$request->orderCode;
                                $webPayment->CCode = (float)$userResult->Code;
                                $webPayment->Mablag = $request->amount;
                                $webPayment->Comment = 'واریز کاربر ' . $userResult->Name . ' به مبلغ مستقیم ' . $request->amount;
                                $webPayment->Save();

                                header('Location: https://www.zarinpal.com/pg/StartPay/' . $result['data']["authority"]);
                            }
                        } else {
                            return redirect('https://kidsshop110.ir/payment-failed-mobile?exception=' . urlencode($result['errors']['message']));
                        }
                    }
                } catch (Exception $e) {
                    return redirect('https://kidsshop110.ir/payment-failed-mobile?exception=' . urlencode($e->getMessage()));
                }
            } else {
                return redirect('https://kidsshop110.ir/payment-failed-mobile?exception=' . urlencode('کاربری با این توکن یافت نشد'));
            }
        } else {
            return redirect('https://kidsshop110.ir/payment-failed-mobile?exception=' . urlencode('کاربری با این توکن یافت نشد'));
        }
    }
}
