<?php

namespace App\Http\Controllers\V2\InnerPages;

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
                'status' => true,
                'result' =>
                [
                    'status' => true,
                    'value' => $unit->MVahed,
                    'last_fetched_at' => Carbon::now()->format('Y-m-d H:i:s')
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
