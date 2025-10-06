<?php

namespace App\Http\Controllers\V2\InnerPages;

use App\Http\Controllers\Controller;
use App\Services\CategoryService;
use App\Services\GeneralService;
use App\Services\ProductService;
use Exception;

class HomeController extends Controller
{

    protected $generalService;
    protected $categoryService;
    protected $productService;

    public function __construct(GeneralService $generalService, CategoryService $categoryService, ProductService $productService)
    {
        $this->generalService = $generalService;
        $this->categoryService = $categoryService;
        $this->productService = $productService;
    }


    public function homePage()
    {
        try {
            return response()->json([
                'result' => [
                    'categories' => $this->categoryService->listMenuCategories(),
                    'banners' => $this->generalService->fetchBanners(),
                    'newestProducts' => $this->productService->homePageNewestProducts(),
                    'offeredProducts' => $this->productService->homePageOfferedProducts(),
                    'bestSeller' => $this->productService->homePageBestSellingProducts(),
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
}
