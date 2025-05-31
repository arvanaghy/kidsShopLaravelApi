<?php

namespace App\Http\Controllers;

use App\Models\CustomerModel;
use App\Models\PaymentsModel;
use App\Models\WebPaymentModel;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckOutController extends Controller
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


    public function index()
    {
        return view('checkout');
    }

    public function zarinpal_payment_callback(Request $request)
    {
        try {
            $paymentResult = WebPaymentModel::where('TrID', $request->Authority)->first();
            $customerResult = CustomerModel::where('Code', $paymentResult->CCode)->first();
            $bankAccount = DB::table('AV_ShomareHesab_VIEW')->where('Def', 1)->where('CodeCompany', $this->active_company)->first();
            $bCode = PaymentsModel::where('CodeDoreMali', $this->financial_period)->max("BCode");
            $code = PaymentsModel::where('CodeDoreMali', $this->financial_period)->max("Code");

            if (!$paymentResult) {
                throw new Exception('تراکنش پیدا نشد');
            }

            $data = array("merchant_id" => "87955b91-59e8-4753-af27-b2815b9c6b40", "authority" => $paymentResult->TrID, "amount" => (int)$paymentResult->Mablag);
            $jsonData = json_encode($data);
            $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/verify.json');
            curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v4');
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
                throw new Exception('خطا در ارتباط  با زرین پال ');
            } else {
                if ($result['data'] and $result['data']['code'] == 100) {
                    // update WebPaymentModel
                    $paymentResult->UUID = $result['data']['ref_id'];
                    $paymentResult->save();
                    // create PaymentModel                  

                    PaymentsModel::create([
                        'Code' => (float)$code + 1,
                        'CodeCompany' => $this->active_company,
                        'CodeDoreMali' => $this->financial_period,
                        'Index1' => 0,
                        'Index2' => 1,
                        'SIndex1' => 'طرف حساب',
                        'SIndex2' => 'بانک',
                        'Code1' => $paymentResult->CCode,
                        'Code2' => $bankAccount->Code,
                        'SDaryaft' => $customerResult->Name,
                        'SPardakht' => $bankAccount->BankName . ' - ' . $bankAccount->ShHesab,
                        'BCode' => $bCode + 1,
                        'Mablag' => $paymentResult->Mablag,
                        'Babat' => $paymentResult->Comment .  ' با کد رهگیری  ' . $result['data']['ref_id']
                    ]);
                    return redirect('https://kidsshop110.ir/payment-success/' . urlencode($result['data']['ref_id']));
                } else {
                    throw new Exception($result['errors']['message'] .  $result['errors']['code']);
                }
            }
        } catch (Exception $e) {
            return redirect('https://kidsshop110.ir/payment-failed?exception=' . urlencode($e->getMessage()));
        }
    }


    public function zarinpal_payment_callback_mobile(Request $request)
    {
        try {
            $paymentResult = WebPaymentModel::where('TrID', $request->Authority)->first();
            $customerResult = CustomerModel::where('Code', $paymentResult->CCode)->first();
            $bankAccount = DB::table('AV_ShomareHesab_VIEW')->where('Def', 1)->where('CodeCompany', $this->active_company)->first();
            $bCode = PaymentsModel::where('CodeDoreMali', $this->financial_period)->max("BCode");
            $code = PaymentsModel::where('CodeDoreMali', $this->financial_period)->max("Code");

            if (!$paymentResult) {
                throw new Exception('تراکنش پیدا نشد');
            }

            $data = array("merchant_id" => "87955b91-59e8-4753-af27-b2815b9c6b40", "authority" => $paymentResult->TrID, "amount" => (int)$paymentResult->Mablag);
            $jsonData = json_encode($data);
            $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/verify.json');
            curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v4');
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
                throw new Exception('خطا در ارتباط  با زرین پال ');
            } else {
                if ($result['data'] and $result['data']['code'] == 100) {
                    // update WebPaymentModel
                    $paymentResult->UUID = $result['data']['ref_id'];
                    $paymentResult->save();
                    // create PaymentModel                  

                    PaymentsModel::create([
                        'Code' => (float)$code + 1,
                        'CodeCompany' => $this->active_company,
                        'CodeDoreMali' => $this->financial_period,
                        'Index1' => 0,
                        'Index2' => 1,
                        'SIndex1' => 'طرف حساب',
                        'SIndex2' => 'بانک',
                        'Code1' => $paymentResult->CCode,
                        'Code2' => $bankAccount->Code,
                        'SDaryaft' => $customerResult->Name,
                        'SPardakht' => $bankAccount->BankName . ' - ' . $bankAccount->ShHesab,
                        'BCode' => $bCode + 1,
                        'Mablag' => $paymentResult->Mablag,
                        'Babat' => $paymentResult->Comment .  ' با کد رهگیری  ' . $result['data']['ref_id']
                    ]);
                    return redirect('https://kidsshop110.ir/payment-success-mobile/' . urlencode($result['data']['ref_id']));
                } else {
                    throw new Exception($result['errors']['message'] .  $result['errors']['code']);
                }
            }
        } catch (Exception $e) {
            return redirect('https://kidsshop110.ir/payment-failed-mobile?exception=' . urlencode($e->getMessage()));
        }
    }

    public function zarinpal_success_payment(Request $request)
    {
        try {
            $validated = $request->validate([
                'referenceId' => 'required',
            ], [
                'referenceId.required' => 'کد رهگیری یافت نشد',
            ]);
            $referenceId = $request->referenceId;
            return redirect('https://kidsshop110.ir/payment-success/' . urlencode($referenceId));
        } catch (Exception $e) {
            return redirect('https://kidsshop110.ir/payment-failed?exception=' . urlencode($e->getMessage()));
        }
    }

    public function zarinpal_unsuccess_payment(Request $request)
    {
        try {

            $validated = $request->validate([
                'exception' => 'required',
            ], [
                'exception.required' => 'خطایی در پرداخت اتفاق افتاده است',
            ]);
            $exception = $request->exception;
            return redirect('https://kidsshop110.ir/payment-failed?exception=' . urlencode($exception));
        } catch (Exception $e) {
            return redirect('https://kidsshop110.ir/payment-failed?exception=' . urlencode($e->getMessage()));
        }
    }

    public function bad_request()
    {
        return redirect('https://kidsshop110.ir/payment-failed?exception=badRequestPage');
    }
}
