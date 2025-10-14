<?php

namespace App\Services;

use App\Jobs\SendSmsJob;
use App\Models\CustomerGroupModel;
use App\Models\CustomerModel;
use App\Repositories\CustomerRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    protected $active_company;
    protected $financial_period;
    protected $customerRepository;

    public function __construct(CompanyService $companyService, CustomerRepository $customerRepository)
    {
        $this->customerRepository = $customerRepository;
        $this->active_company = $companyService->getActiveCompany();

        if ($this->active_company) {
            $this->financial_period = $companyService->getFinancialPeriod($this->active_company);
        }
    }

    protected function isVerified($customer_id)
    {
        return CustomerModel::where('Code', $customer_id)
            ->where('CodeCompany', $this->active_company)
            ->whereNotNull('VerifiedAT')
            ->whereNotNull('DeviceInfo')
            ->whereNotNull('UToken')
            ->first();
    }

    protected function isUserActive($customer_id)
    {
        return CustomerModel::where('Code', $customer_id)
            ->where('CodeCompany', $this->active_company)
            ->where('Act', 1)
            ->first();
    }

    protected function checkSmsExpiry($customer_id)
    {
        return CustomerModel::where('Code', $customer_id)
            ->where('CodeCompany', $this->active_company)
            ->where('SMSTime', '>=', now())
            ->first();
    }

    protected function generateSmsCode($customer_id)
    {
        $existing = $this->checkSmsExpiry($customer_id);

        if ($existing) {
            return $existing->SMSCode;
        }

        $expireTime = now()->addMinutes(5);
        $smsCode = rand(1000, 9999);

        CustomerModel::where('Code', $customer_id)
            ->where('CodeCompany', $this->active_company)
            ->update([
                'SMSTime' => $expireTime,
                'SMSCode' => $smsCode,
                'updated_at' => null,
            ]);

        return $smsCode;
    }

    protected function generateToken($customer_id, $customer_phone_number, $request)
    {
        $token = Hash::make($customer_id . $customer_phone_number . $request->ip());

        CustomerModel::where('Code', $customer_id)->where('CodeCompany', $this->active_company)->update([
            'UToken' => $token,
            'VerifiedAT' => now(),
            'DeviceInfo' => $request->ip(),
            'SMSTime' => null,
            'SMSCode' => null,
            'updated_at' => null,
        ]);
    }

    public function logOut($token)
    {
        $this->customerRepository->logOut($token);
    }

    public function updateUserAddress(array $validated, $token)
    {
        CustomerModel::where('UToken', $token)->update($validated);

        return CustomerModel::where('UToken', $token)->first();
    }

    public function editUserInfo(array $validated, $token)
    {
        CustomerModel::where('UToken', $token)->update($validated);

        return CustomerModel::where('UToken', $token)->first();
    }

    public function resendSms($phone)
    {
        $customer = CustomerModel::where('Mobile', $phone)
            ->where('CodeCompany', $this->active_company)
            ->first();

        if (!$customer) {
            throw new ModelNotFoundException('مشتری با این شماره تلفن یافت نشد', 404);
        }

        if (!$this->isUserActive($customer->Code)) {
            throw new \Exception('حساب کاربری غیرفعال است', 403);
        }

        if ($customer->SMSTime && now()->lessThanOrEqualTo($customer->SMSTime)) {
            $minutesLeft = ceil($customer->SMSTime->diffInMinutes(now()) + 1);
            throw new \Exception("بعد از {$minutesLeft} دقیقه مجددا امتحان کنید", 429);
        }

        $smsCode = $this->generateSmsCode($customer->Code);
        $smsText = "کیدزشاپ\nکد ورود به سیستم: {$smsCode}\n{env('FRONTEND_URL')}";
        SendSmsJob::dispatchSync($phone, $smsText);

        return $smsCode;
    }

    public function verifySms($phone, $code)
    {
        $customer = CustomerModel::where('Mobile', $phone)->where('CodeCompany', $this->active_company)->first();

        if (!$customer) {
            throw new ModelNotFoundException('مشتری با این شماره تلفن یافت نشد', 404);
        }

        if (!$this->isUserActive($customer->Code)) {
            throw new \Exception('حساب کاربری غیرفعال است', 403);
        }

        if ($customer->SMSTime && now()->greaterThan($customer->SMSTime)) {
            throw new \Exception("مدت زمان پیام کوتاه منقضی شده است یا مقدار آن صحیح نیست", 429);
        }

        if ($customer->SMSCode != $code) {
            throw new \Exception('کد ورود اشتباه است', 400);
        }

        $this->generateToken($customer->Code, $customer->Mobile, request());

        $result = CustomerModel::where('Mobile', $phone)->where('CodeCompany', $this->active_company)->first();


        return $result;
    }

    public function verifyToken($token)
    {
        return $this->customerRepository->findByToken($token);
    }

    public function login($phone)
    {
        $customer = CustomerModel::where('Mobile', $phone)->where('CodeCompany', $this->active_company)->first();

        if (!$customer) {
            throw new ModelNotFoundException('کاربری با این شماره تلفن وجود ندارد. لطفا ثبت نام کنید', 404);
        }

        if (!$this->isUserActive($customer->Code)) {
            throw new \Exception('حساب کاربری شما توسط مدیریت مسدود شده است. لطفا با پشتیبانی تماس بگیرید', 403);
        }

        if ($this->isVerified($customer->Code)) {
            return [
                'sms_code' => null,
                'customer' => $customer,
                'status_code' => 201,
                'message' => 'شما قبلا وارد شده اید'
            ];
        }

        $smsCode = $this->generateSmsCode($customer->Code);
        $smsText = "کیدزشاپ\nکد ورود به سیستم: {$smsCode}\n{env('FRONTEND_URL')}";
        SendSmsJob::dispatchSync($phone, $smsText);

        return [
            'sms_code' => $smsCode,
            'customer' => $customer,
            'message' => 'کد ورود به سیستم ارسال شد',
            'status_code' => 202
        ];
    }

    protected function customerGroup()
    {
        $customer_group = CustomerGroupModel::where('name', 'کاربران پیش فرض')->where('CodeCompany', $this->active_company)->first();
        if ($customer_group) {
            return $customer_group->Code;
        } else {
            $customer_groupCode = CustomerGroupModel::max('Code') + 1;
            CustomerGroupModel::create([
                'Code' => $customer_groupCode,
                'CodeCompany' => $this->active_company,
                'Name' => 'کاربران پیش فرض',
                'Kharidar' => 1,
                'Forooshande' => 1,
                'Personel' => 0,
                'Tankhah' => 0,
                'Owner' => 0,
                'BazarYab' => 0,
                'Peymankar' => 0,
            ]);
            return $customer_groupCode;
        }
    }

    public function register($phone, $name, $address)
    {

        if (!$this->financial_period) {
            throw new \Exception('دوره مالی فعالی وجود ندارد', 451);
        }

        $customer = CustomerModel::where('Mobile', $phone)
            ->where('CodeCompany', $this->active_company)
            ->first();

        if ($customer) {
            if (!$this->isUserActive($customer->Code)) {
                throw new \Exception('حساب کاربری غیرفعال است', 403);
            }

            $smsCode = $this->generateSmsCode($customer->Code);

            $smsText = "کیدزشاپ\nکد ورود به سیستم: {$smsCode}\n{env('FRONTEND_URL')}";
            SendSmsJob::dispatchSync($phone, $smsText);


            return [
                'message' => '.کاربری با این شماره همراه قبلا ثبت شده است کد ورود به سیستم به شماره شما ارسال گردید',
                'sms' => $smsCode,
                'status_code' => 200
            ];
        }

        return DB::transaction(function () use ($phone, $name, $address) {
            $customerGroupCode = $this->customerGroup();
            $smsCode = rand(1000, 9999);
            $expireTime = now()->addMinutes(5);

            $customerCode = CustomerModel::lockForUpdate()->max('CodeCustomer') + 1;

            CustomerModel::create([
                'CodeCompany' => $this->active_company,
                'CodeGroup' => $customerGroupCode,
                'PayerType' => 0,
                'CodeCustomer' => $customerCode,
                'Name' => $name,
                'Mobile' => $phone,
                'Address' => $address,
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
                'SMSCode' => $smsCode,
                'SMSTime' => $expireTime
            ]);

            $smsText = "کیدزشاپ\nکد ورود به سیستم: {$smsCode}\n{env('FRONTEND_URL')}";
            SendSmsJob::dispatchSync($phone, $smsText);

            $admins = $this->customerRepository->fetchAdminsList();
            if ($admins) {
                foreach ($admins as $admin) {
                    $adminPhone = $admin->Mobile;
                    $adminSmsText = "کیدزشاپ. یک کاربر جدید با شماره همراه ' . $phone . ' و نام ' . $name . ' ثبت شده است\n{env('FRONTEND_URL')}";
                    SendSmsJob::dispatchSync($adminPhone, $adminSmsText);
                }
            }

            return [
                'message' => 'ثبت نام با موفقیت انجام شد و کد پیامک ارسال گردید',
                'sms' => $smsCode,
                'status_code' => 201
            ];
        });
    }

    public function customerCategory($Code)
    {
        return CustomerGroupModel::where('Code', $Code)->firstOrFail();
    }
}
