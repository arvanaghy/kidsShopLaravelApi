<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Services\CategoryService;
use App\Services\GeneralService;
use Carbon\Carbon;
use Exception;


class GeneralController extends Controller
{
    protected $generalService = null;
    protected $categoryService = null;


    public function __construct(GeneralService $generalService, CategoryService $categoryService)
    {
        try {
            $this->generalService = $generalService;
            $this->categoryService = $categoryService;
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }


    public function topMenu()
    {
        try {
            $categories = $this->categoryService->listMenuCategories();

            return response()->json([
                'result' => [
                    'categories' => $categories
                ],
                'message' => 'دریافت اطلاعات با موفقیت انجام شد'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function companyInfo()
    {
        try {
            $companyInfo = $this->generalService->getCompanyInfo();
            return response()->json(['status' => true, 'company_info' => $companyInfo], 200);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function currencyUnit()
    {
        try {
            $unit = $this->generalService->getCurrencyUnit();
            return response()->json([
                'result' =>
                [
                    'value' => $unit,
                    'last_fetched_at' => Carbon::now()->format('Y-m-d H:i:s')
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function listTransferServices()
    {
        try {
            return response()->json([
                $transferServices = $this->generalService->listTransferServices(),
                'result' => $transferServices,
                "message" => "دریافت اطلاعات با موفقیت انجام شد",
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'result' => null,
                'message' => $e->getMessage(),
            ], 503);
        }
    }

    public function checkOnlinePaymentAvailable()
    {
        try {
            $bankAccount = $this->generalService->checkOnlinePaymentAvailable();
            if ($bankAccount) {
                return response()->json([
                    'result' => $bankAccount,
                    "message" => "درگاه پرداخت اینترنتی فعال است"
                ], 201);
            } else {
                return response()->json([
                    'result' => null,
                    "message" => "درگاه پرداخت اینترنتی یافت نشد"
                ], 404);
            }
        } catch (Exception $e) {
            return response()->json([
                'result' => null,
                'message' => $e->getMessage(),
            ], 503);
        }
    }


    public function faq()
    {
        try {
            return response()->json([
                $faq = $this->generalService->faq(),
                'result' => $faq,
                "message" => "دریافت اطلاعات با موفقیت انجام شد",
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'result' => null,
                'message' => $e->getMessage(),
            ], 503);
        }
    }

    public function aboutUs()
    {
        try {
            return response()->json([
                $aboutUs = $this->generalService->aboutUs(),
                'result' => $aboutUs,
                "message" => "دریافت اطلاعات با موفقیت انجام شد",
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'result' => null,
                'message' => $e->getMessage(),
            ], 503);
        }
    }

    public function isServerOnline()
    {
        try {
            return response()->json([
                'result' => null,
                'message' => 'اتصال به سرور با موفقیت برقرار شد',

            ], 202);
        } catch (Exception $e) {
            return response()->json([
                'result' => null,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
