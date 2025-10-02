<?php

namespace App\Http\Controllers\V2\InnerPages;

use App\Http\Controllers\Controller;
use App\Services\ProductService;
use Exception;


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
                'offeredProducts' => $this->productService->suggestedProducts(),
                'message' => trans('messages.product_displayed_successfully')
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
