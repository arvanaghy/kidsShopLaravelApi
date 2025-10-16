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
                "customer" => null
            ], 202);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "customer" => null
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
                'message' => 'بروزرسانی با موفقیت انجام شد',
                'customer' => $user,
            ], 202);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "customer" => null
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
                'customer' => $user,
                "message" => "اطلاعات با موفقیت بروزرسانی شد",
            ], 202);
        } catch (Exception $e) {
            return response()->json([
                'customer' => null,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 503);
        }
    }

    public function resendSms(ResendSmsRequest $request)
    {
        try {
            $validated = $request->validated();
            $this->customerService->resendSms($validated['phone_number']);

            return response()->json([
                'message' => 'کد ورود مجدد ارسال شد',
                'customer' => null,
            ], 202);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'customer' => null,
            ], $e->getCode() ?: 503);
        }
    }

    public function verifySms(VerifySmsRequest $request)
    {
        try {
            $validated = $request->validated();
            $customer = $this->customerService->verifySms($validated['phone_number'], $validated['sms']);

            return response()->json([
                'message' => 'ورود با موفقیت انجام شد',
                'customer' => $customer,
            ], 202);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'customer' => null,
            ], $e->getCode() ?: 503);
        }
    }

    public function verifyToken(VerifyTokenRequest $request)
    {
        try {
            $validated = $request->validated();
            $customer = $this->customerService->verifyToken($validated['UToken']);

            return response()->json([
                'message' => 'توکن با موفقیت تایید شد',
                'customer' => $customer,
            ], 202);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "customer" => null
            ], $e->getCode() ?: 503);
        }
    }

    public function register(RegisterRequest $request)
    {
        try {

            $validated = $request->validated();

            $result = $this->customerService->register($validated['phone_number'], $validated['name'], $validated['Address']);
            return response()->json([
                'message' => $result['message'],
                'customer' => null,
            ], $result['status_code']);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "customer" => null
            ], 503);
        }
    }


    public function login(LoginRequest $request)
    {
        try {
            $validated = $request->validated();

            $result = $this->customerService->login($validated['phone_number']);
            return response()->json([
                'message' => $result['message'],
                'customer' => $result['customer'],
            ], $result['status_code']);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "customer" => null
            ], $e->getCode() ?: 503);
        }
    }

    public function customerCategory($Code)
    {
        try {

            return response()->json([
                'message' => 'موفقیت آمیز بود',
                'result' => $this->customerService->customerCategory($Code),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }
}
