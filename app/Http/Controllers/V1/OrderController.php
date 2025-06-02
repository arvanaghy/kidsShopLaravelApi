<?php

namespace App\Http\Controllers\V1;

use App\Models\OrderModel;
use Illuminate\Http\Request;
use App\Models\CustomerModel;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Exception;

class OrderController extends Controller
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

    protected function send_sms_via_webone($phoneNO, $text)
    {
        $base_url = 'https://webone-sms.ir/SMSInOutBox/SendSms';
        $params = array(
            'username' => '09354278334',
            'password' => '414411',
            'from' => '10002147',
            'text' => $text,
            'to' => $phoneNO
        );
        $url = $base_url . '?' . http_build_query($params);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }

    public function submit_order(Request $request)
    {
        try {
            $token = $request->bearerToken();
            if (!$token or $token == "") {
                return response()->json([
                    "message" => "Token نامعتبر ا��ت",
                    "result" => null,
                ], 505);
            }
            $userResult = CustomerModel::where('UToken', $token)->first();

            if (!$userResult) {
                return response()->json([
                    "message" => "کاربری با این توکن یافت نشد",
                    "result" => null,
                ], 404);
            }


            $insertion = OrderModel::create([
                'CCode' => $userResult->Code,
                'CodeDoreMali' => $this->financial_period,
                'Comment' => $request['description'],
                'CodeKhadamat' => $request['CodeKhadamat'],
                'MKhadamat' => $request['MKhadamat']
            ]);

            if (!$insertion) {
                return response()->json([
                    "message" => "خطا در ��بت سفارش",
                    "result" => null,
                ], 500);
            }

            if ($insertion) {
                $result = OrderModel::where('CCode', $userResult->Code)->where('CodeDoreMali', $this->financial_period)->orderBy('Code', 'DESC')->first();
                $total_price = 0;
                foreach ($request['products'] as $value) {
                    $product = DB::table('Kala')->select('KMegdar', 'SPrice', 'KhordePrice', 'OmdePrice', 'HamkarPrice', 'AgsatPrice', 'CheckPrice')->where('Code', $value['KCode'])->first();
                    $price = 0;
                    switch ($userResult->ForooshType) {
                        case '0':
                            $price = $product->SPrice;
                            break;
                        case '1':
                            $price = $product->KhordePrice;
                            break;
                        case '2':
                            $price = $product->OmdePrice;
                            break;
                        case '3':
                            $price = $product->AgsatPrice;
                            break;
                        case '4':
                            $price = $product->CheckPrice;
                            break;
                        default:
                            $price = $product->KhordePrice;
                            break;
                    }

                    DB::table('SOrderKala')->insert([
                        'SCode' => $result->Code,
                        'KCode' => $value['KCode'],
                        'Tedad' => $value['Tedad'],
                        'Fee' => $price,
                        'KTedad' => $value['KTedad'],
                        'KMegdar' => $product->KMegdar,
                        'KFee' => $price * $product->KMegdar,
                        'DTakhfif' => 0,
                        'MTakhfif' => 0
                    ]);
                }

                $Sorder  = DB::table('AV_SOrder_View')->where('Code', $result->Code)->first();

                $client_text_message = "مشتری گرامی " . $userResult->Name . " پیش فاکتور " . $result->Code . " به مبلغ " . $Sorder->JamKol . " در سیستم ثبت گردید. با تشکر کیدزشاپ";
                $this->send_sms_via_webone($userResult->Mobile, $client_text_message);
                $admin_text_message = "پیش فاکتور جدید به شماره " . $result->Code . " به مبلغ " . $Sorder->JamKol .  "برای کاربر" . $userResult->Name .  " در سیستم ثبت گردید.";
                $this->send_sms_via_webone('09354278334', $admin_text_message);

                return response()->json([
                    'message' => 'پیش فاکتور با موفقیت ثبت شد',
                    'result' => $result
                ], 201);
            } else {
                return response()->json([
                    'message' => 'خطا در ثبت پیش فاکتور',
                    'result' => null
                ], 401);
            }
        } catch (Exception $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'result' => null
            ], 503);
        }
    }
}
