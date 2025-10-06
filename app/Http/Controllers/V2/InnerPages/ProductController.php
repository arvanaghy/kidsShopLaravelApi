<?php

namespace App\Http\Controllers\V2\InnerPages;

use App\Http\Controllers\Controller;
use App\Services\ProductService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    protected $productService;

    /**
     * Constructor to set up active company and financial period.
     */
    public function __construct(ProductService $productService)
    {
        try {
            $this->productService = $productService;
        } catch (Exception $e) {
            abort(500, $e->getMessage());
        }
    }

    /**
     * Show a single product with related and offered products.
     */
    public function showProduct($code)
    {
        try {
            $result = $this->productService->showSingleProduct($code);
            return response()->json([
                'product' => $result,
                'relatedProducts' => $this->productService->relatedProducts($result->GCode, $result->SCode, $result->Code),
                'suggestedProducts' => $this->productService->suggestedProducts($code),
                'message' => trans('messages.product_displayed_successfully')
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function listAllProducts(Request $request)
    {
        try {
            $productPage = $request->query('product_page', 1);

            $allProducts = $this->list_products();
            if ($allProducts instanceof \Illuminate\Http\JsonResponse) {
                return $allProducts;
            }
            $allProducts->appends(['product_page' => $productPage]);

            return response()->json([
                'result' => [
                    'products' => $allProducts,
                    'colors' => $this->list_colors('0', 'all'),
                    'sizes' => $this->list_sizes('0', 'all'),
                    'prices' => $this->list_prices($allProducts),
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


    public function listBestSeller(Request $request)
    {
        try {
            $productPage = $request->query('product_page', 1);
            $allBestSellingProducts = $this->productService->listBestSelling($request);
            if ($allBestSellingProducts instanceof \Illuminate\Http\JsonResponse) {
                return $allBestSellingProducts;
            }
            $allBestSellingProducts->appends(['product_page' => $productPage]);

            return response()->json([
                'result' => [
                    'products' => $allBestSellingProducts,
                    // 'colors' => $this->productService->listProductColors('0', 'all'),
                    // 'sizes' => $this->productService->listProductSizes('0', 'all'),
                    // 'prices' => $this->productService->listProductPrices($allBestSellingProducts),
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
