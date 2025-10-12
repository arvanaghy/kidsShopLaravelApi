<?php

namespace App\Http\Controllers\V1;

use App\Helpers\FilterChecker;
use App\Http\Controllers\Controller;
use App\Services\ProductService;
use Exception;
use Illuminate\Http\Request;

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

            $product = $this->productService->showSingleProduct($code);
            $relatedProducts = $this->productService->relatedProducts($product->GCode, $product->SCode, $product->Code);
            $suggestedProducts = $this->productService->suggestedProducts($product->Code);

            return response()->json([
                'product' => $product,
                'relatedProducts' => $relatedProducts,
                'suggestedProducts' => $suggestedProducts,
                'message' => trans('messages.product_displayed_successfully')
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function listAllProducts(Request $request)
    {
        try {

            $allProducts = $this->productService->listAllProducts($request);
            $hasRequestFilter = FilterChecker::hasFilters($request);
            $colors = $this->productService->listProductColors($allProducts, $hasRequestFilter, 'all');
            $sizes = $this->productService->listProductSizes($allProducts, $hasRequestFilter, 'all');

            return response()->json([
                'result' => [
                    'products' => $allProducts,
                    'colors' => $colors,
                    'sizes' => $sizes,
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


    public function listBestSellingProducts(Request $request)
    {
        try {
            $allBestSellingProducts = $this->productService->listBestSellingProducts($request);
            $hasRequestFilter = FilterChecker::hasFilters($request);

            $colors = $this->productService->listProductColors($allBestSellingProducts, $hasRequestFilter, 'bestseller');
            $sizes = $this->productService->listProductSizes($allBestSellingProducts, $hasRequestFilter, 'bestseller');

            return response()->json([
                'result' => [
                    'products' => $allBestSellingProducts,
                    'colors' => $colors,
                    'sizes' => $sizes,
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


    public function listOfferedProducts(Request $request)
    {
        try {
            $allOfferedProducts = $this->productService->listOfferedProducts($request);
            $hasRequestFilter = FilterChecker::hasFilters($request);

            $colors = $this->productService->listProductColors($allOfferedProducts, $hasRequestFilter, 'offers');
            $sizes = $this->productService->listProductSizes($allOfferedProducts, $hasRequestFilter, 'offers');

            return response()->json([
                'result' => [
                    'products' => $allOfferedProducts,
                    'colors' => $colors,
                    'sizes' => $sizes,
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
