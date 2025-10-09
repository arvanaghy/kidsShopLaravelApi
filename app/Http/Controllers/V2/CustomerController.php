<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\EditUserInfoRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResendSmsRequest;
use App\Http\Requests\UpdateUserAddressRequest;
use App\Http\Requests\VerifySmsRequest;
use App\Http\Requests\VerifyTokenRequest;
use App\Services\CustomerService;
use Exception;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    protected $customerService;
    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }
    public function logOut(Request $request)
    {
        try {
            $token = $request->bearerToken();
            $this->customerService->logOut($token);

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

    public function updateUserAddress(UpdateUserAddressRequest $request)
    {
        try {
            $validated = $request->validated();
            $token = $request->bearerToken();
            $user = $this->customerService->updateUserAddress($validated, $token);
            return response()->json([
                'message' => 'موفقیت آمیز بود',
                'result' => $user,
            ], 202);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    public function editUserInfo(EditUserInfoRequest $request)
    {
        try {
            $token = $request->bearerToken();
            $validated = $request->validated();
            $user = $this->customerService->editUserInfo($validated, $token);
            return response()->json([
                'result' => $user,
                "message" => "اطلاعات با موفقیت ثبت گردید"
            ], 202);
        } catch (Exception $e) {
            return response()->json([
                'result' => null,
                'message' => $e->getMessage(),
            ], 503);
        }
    }

    public function resendSms(ResendSmsRequest $request)
    {
        try {
            $validated = $request->validated();
            $this->customerService->resendSms($validated['phone']);

            return response()->json([
                'message' => 'پیغام شما با موفقیت دریافت شد',
                'result' => null,
            ], 202);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'result' => null,
            ], $e->getCode() ?: 503);
        }
    }

    public function verifySms(VerifySmsRequest $request)
    {
        try {
            $validated = $request->validated();
            $customer = $this->customerService->verifySms($validated['phone_number'], $validated['sms']);

            return response()->json([
                'message' => 'پیغام شما با موفقیت دریافت شد',
                'result' => $customer,
            ], 202);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'result' => null,
            ], $e->getCode() ?: 503);
        }
    }

    public function verifyToken(VerifyTokenRequest $request)
    {
        try {
            $validated = $request->validated();
            $customer = $this->customerService->verifyToken($validated['token']);

            return response()->json([
                'message' => 'پیغام شما با موفقیت دریافت شد',
                'result' => $customer,
            ], 202);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    public function register(RegisterRequest $request)
    {
        try {

            $validated = $request->validated();

            $result = $this->customerService->register($validated['phone_number'], $validated['name'], $validated['Address']);
            return response()->json([
                'message' => $result['message'],
                'result' => $result['sms'],
            ], $result['status_code']);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }


    public function login(LoginRequest $request)
    {
        try {
            $validated = $request->validated();

            $result = $this->customerService->login($validated['phone_number']);
            return response()->json([
                'message' => 'ورود به سیستم با موفقیت انجام پذیرفت',
                'result' => $result['customer'],
            ], $result['status_code']);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }
}
