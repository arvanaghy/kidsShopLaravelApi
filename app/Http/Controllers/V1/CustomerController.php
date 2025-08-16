<?php

namespace App\Http\Controllers\V1;

use Illuminate\Http\Request;
use App\Models\CustomerModel;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Models\CustomerGroupModel;
use Carbon\Carbon;
use Exception;
use App\Services\SmsService;

class CustomerController extends Controller
{

    protected $active_company = null;
    protected $financial_period = null;
    protected $admin_phone_number = '';
    protected $app_user_type = 'کاربران پیش فرض';

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

    protected function is_verified($customer_id)
    {
        return CustomerModel::where('Code', $customer_id)->where('CodeCompany', $this->active_company)->where('VerifiedAT', '!=', null)->where('DeviceInfo', '!=', null)->where('UToken', '!=', null)->first();
    }

    protected function is_user_active($customer_id)
    {
        return CustomerModel::where('Code', $customer_id)->where('CodeCompany', $this->active_company)->where('Act', 1)->first();
    }
    protected function generate_token($customer_id, $customer_phone_number, $request)
    {
        $phrase = $customer_id . $customer_phone_number . time();
        $token = Hash::make($phrase);
        $customer = new CustomerModel;
        $customer->timestamps = false;
        $customer
            ->newModelQuery()
            ->where('Code', $customer_id)
            ->update([
                'UToken' => $token,
                'VerifiedAT' => now(),
                'DeviceInfo' => $request->ip(),
                'SMSTime' => null,
                'SMSCode' => null
            ]);
        return $token;
    }

    protected function check_sms_expire($customer_id)
    {
        $result = CustomerModel::where('Code', $customer_id)->where('CodeCompany', $this->active_company)->where('SMSTime', '>=', now())->first();
        if ($result) {
            return true;
        } else {
            return false;
        }
    }

    protected function generate_sms($customer_id)
    {
        $now = Carbon::now();

        if (!$this->check_sms_expire($customer_id)) {
            $expireTime = Carbon::now()->addMinutes(5);
            $rand = rand(1000, 9999);
            $customer = new CustomerModel;
            $customer->timestamps = false;
            $customer
                ->newModelQuery()
                ->where('Code', $customer_id)
                ->update([
                    'SMSTime' => $expireTime,
                    'SMSCode' => $rand
                ]);
            return $rand;
        } else {
            $result = CustomerModel::where('Code', $customer_id)->where('CodeCompany', $this->active_company)
                ->first();
            return $result['SMSCode'];
        }
    }

    protected function send_sms_via_webone($phoneNO, $text)
    {
        $base_url = 'https://webone-sms.ir/SMSInOutBox/SendSms';
        $params = array(
            'username' => '09354278334',
            'password' => '84332',
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

    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'phone_number' => 'required|regex:/^09[0-9]{9}$/',
            ], [
                'phone_number.regex' => 'شماره تلفن وارد شده صحیح نیست',
                'phone_number.required' => 'شماره تلفن ضروری است',
            ]);

            $result = CustomerModel::where('Mobile', $validated['phone_number'])->where('CodeCompany', $this->active_company)
                ->first();
            if ($result) {
                if ($this->is_user_active($result['Code'])) {
                    if ($this->is_verified($result['Code'])) {
                        return response()->json([
                            "message" => " ورود به سیستم با موفقیت انجام پذیرفت",
                            "result" => $result
                        ], 201);
                    } else {
                        $my_sms = $this->generate_sms($result['Code']);
                        $sms_text_message = 'کیدزشاپ ، کد ورود به سیستم ' . $my_sms;
                        // $smsService = new SmsService($validated['phone_number'], $sms_text_message);
                        // $smsResult = $smsService->send();
                        $this->send_sms_via_webone($validated['phone_number'], $sms_text_message);

                        return response()->json([
                            "message" => 'کد ورود به سیستم ارسال شد',
                            "result" => null
                        ], 202);
                    }
                } else {
                    return response()->json([
                        "message" => " حساب کاربری شما توسط مدیریت مسدود شده است و با پشتیبانی تمای بگیرید",
                        "result" => null
                    ], 403);
                }
            } else {

                return response()->json([
                    "message" => "  کاربری با این شماره تلفن وجود ندارد . ثبت نام کنید",
                    "result" => null
                ], 404);
            }
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    public function verify_sms(Request $request)
    {
        try {
            $validated = $request->validate([
                'phone_number' => 'required|min:10|regex:/^09[0-9]{9}$/',
                'sms' => 'required|size:4'
            ], [
                'sms.size' => 'کد وارد شده صحیح نیست',
                'sms.required' => 'کد وارد شده صحیح نیست',
                'phone_number.regex' => 'شماره تلفن وارد شده صحیح نیست',
                'phone_number.min' => 'شماره تلفن وارد شده صحیح نیست',
                'phone_number.required' => 'شماره تلفن ضروری است',
            ]);
            $result = CustomerModel::where('Mobile', $validated['phone_number'])->where('CodeCompany', $this->active_company)
                ->where('SMSCode', $validated['sms'])->where('SMSTime', '>=', now())->first();

            if ($result) {
                $token =  $this->generate_token($result['Code'], $result['Mobile'], $request);
                $reResult = CustomerModel::where('Mobile', $validated['phone_number'])->where('CodeCompany', $this->active_company)->where('UToken', $token)->first();
                if ($reResult) {
                    return response()->json([
                        "message" => " ورود به سیستم با موفقیت انجام پذیرفت",
                        "result" => $reResult
                    ], 202);
                } else {
                    return response()->json([
                        "message" => "خطایی در دریافت اطلاعات رخ داده است",
                        "result" => null
                    ], 404);
                }
            } else {
                return response()->json([
                    "message" => " مدت زمان پیام کوتاه منقضی شده است یا مقدار آن صحیح نیست",
                    "result" => null
                ], 403);
            }
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    public function verify_token(Request $request)
    {
        try {
            $validated = $request->validate([
                'UToken' => 'required',
            ], [
                'UToken.required' => 'اطلاعاتی در دستگاه ذخیره نشده است'
            ]);
            $result = CustomerModel::where('UToken', $validated['UToken'])->where('CodeCompany', $this->active_company)->first();
            if ($result) {
                return response()->json([
                    "message" => "ورود به سیستم با موفقیت انجام پذیرفت",
                    "result" => $result
                ], 202);
            } else {
                return response()->json([
                    "message" => "مقدار توکن صحیح نیست، نسبت به ورود اقدام نمایید",
                    "result" => null
                ], 403);
            }
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    public function logOut(Request $request)
    {
        try {
            $token = $request->bearerToken();
            CustomerModel::where('UToken', $token)->update([
                'UToken' => null,
                'VerifiedAT' => null,
                'SMSTime' => null,
                'SMSCode' => null
            ]);

            return response()->json([
                "message" => "خروج از حساب کاربری با موفقیت انجام پذیرفت",
                "result" => null
            ], 202);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    public function register(Request $request)
    {

        try {
            $validated = $request->validate([
                'phone_number' => 'required|min:10',
                'name' => 'required|min:3',
                'Address' => 'required',
            ], [
                'phone_number.required' => 'شماره تلفن را وارد کنید',
                'phone_number.min' => 'شماره تلفن باید بیشتر از 10 رقم باشد',
                'name.required' => 'نام را وارد کنید',
                'name.min' => 'نام باید بیشتر از 3 کاراکتر باشد',
                'Address.required' => 'لطفا آدرس را وارد کنید',
            ]);

            if ($this->financial_period) {
                // handle customer default group
                $customer_group = CustomerGroupModel::where('name', $this->app_user_type)->where('CodeCompany', $this->active_company)->first();
                if ($customer_group) {
                    $customer_group_code = $customer_group->Code;
                } else {
                    $customer_groupCode = CustomerGroupModel::max('Code') + 1;
                    CustomerGroupModel::create([
                        'Code' => $customer_groupCode,
                        'CodeCompany' => $this->active_company,
                        'Name' => $this->app_user_type,
                        'Kharidar' => 1,
                        'Forooshande' => 1,
                        'Personel' => 0,
                        'Tankhah' => 0,
                        'Owner' => 0,
                        'BazarYab' => 0,
                        'Peymankar' => 0,
                    ]);
                    $customer_group_code = $customer_groupCode;
                }
                // handle customer registeration
                $isUserExist = CustomerModel::where('Mobile', $validated['phone_number'])->first();
                if ($isUserExist) {
                    return response()->json([
                        "message" => "کاربری با این شماره همراه قبلا در سیستم ثبت شده است",
                        "result" => null
                    ], 302);
                } else {
                    $customerCode = CustomerModel::max('CodeCustomer') + 1;
                    $sms_code = rand(1000, 9999);
                    $expireTime = Carbon::now()->addMinutes(5);

                    CustomerModel::create([
                        'CodeCompany' => $this->active_company,
                        'CodeGroup' => $customer_group_code,
                        'PayerType' => 0,
                        'CodeCustomer' => (int)$customerCode,
                        'Name' => $validated['name'],
                        'Mobile' => $validated['phone_number'],
                        'Address' => (string)$request['Address'],
                        'Etebar' => 0,
                        'EtebarCheck' => 0,
                        'CityCode' => 0,
                        'Kharidar' => 1,
                        'Forooshande' => 1,
                        'Personel' => 0,
                        'Tankhah' => 0,
                        'Owner' => 0,
                        'BazarYab' => 0,
                        'Peymankar' => 0,
                        'ForooshType' => 1,
                        'DForoosh' => 0,
                        'PSahm' => 0,
                        'BPercent' => 0,
                        'BKalaPercent' => 1,
                        'DENUSLat' => 0,
                        'DENUSLong' => 0,
                        'CLocationOn' => 0,
                        'Act' => 1,
                        'CShowInDevice' => 1,
                        'VCar' => 0,
                        'SMSCode' => $sms_code,
                        'SMSTime' => $expireTime
                    ]);

                    $client_sms_text_message = 'کیدزشاپ  ، کد ورود به سیستم ' . $sms_code;
                    $admin_sms_text_message = 'کاربر جدید با نام ' . $validated['name'] .  ' و شماره تلفن ' . $validated['phone_number'] . ' در سیستم ثبت گردید. ';
                    $smsService = new SmsService($validated['phone_number'], $client_sms_text_message, true,  $admin_sms_text_message);
                    $smsService->send();

                    return response()->json([
                        "message" => "ثبت نام با موفقیت انجام پذیرفت  و کد پیام کوتاه برای شما ارسال شد",
                        "result" => null
                    ], 202);
                }
            } else {
                return response()->json([
                    "message" => "دوره ی مالی فعالی وجود ندارد",
                    "result" => null
                ], 451);
            }
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    public function resend_sms(Request $request)
    {
        try {
            $now = Carbon::now();

            $validated = $request->validate([
                'phone_number' => 'required|min:10|regex:/^09[0-9]{9}$/',
            ]);

            $result = CustomerModel::where('Mobile', $validated['phone_number'])->where('CodeCompany', $this->active_company)
                ->first();
            if ($result) {
                if ($this->is_user_active($result['Code'])) {
                    if (now() <= $result['SMSTime']) {
                        return response()->json([
                            "message" => ' بعد از  ' . ceil(abs($result->SMSTime->diffInMinutes($now))) + 1 . ' دقیقه از این زمان مجددا امتحان کنید',
                            "result" => null
                        ], 401);
                    } else {
                        $my_sms = $this->generate_sms($result['Code']);
                        $sms_text_message = 'کیدزشاپ  ، کد ورود به سیستم ' . $my_sms;
                        $smsService = new SmsService($validated['phone_number'], $sms_text_message);
                        $result = $smsService->send();
                        if ($result['status'] == 'success') {
                            return response()->json([
                                "message" => $result['message'],
                                "result" => null
                            ], 202);
                        }
                    }
                } else {
                    return response()->json([
                        "message" => " حساب کاربری شما توسط مدیریت مسدود شده است و با پشتیبانی تمای بگیرید",
                        "result" => null
                    ], 403);
                }
            } else {
                return response()->json([
                    "message" => " کاربری با این شماره تلفن وجود ندارد لطفا ثبت نام نمایید ",
                    "result" => null
                ], 404);
            }
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    public function customerCategory($Code)
    {
        try {

            return response()->json([
                'message' => 'موفقیت آمیز بود',
                'result' => CustomerGroupModel::where('Code', $Code)->firstOrFail(),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }
}
