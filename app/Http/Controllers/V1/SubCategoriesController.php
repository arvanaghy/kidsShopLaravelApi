<?php

namespace App\Http\Controllers\V1;

use App\Helpers\FilterChecker;
use App\Http\Controllers\Controller;
use App\Services\SubcategoryService;
use Exception;
use Illuminate\Http\Request;


class SubCategoriesController extends Controller
{

    protected $subcategoryService;

    public function __construct(SubcategoryService $subcategoryService)
    {
        $this->subcategoryService = $subcategoryService;
    }

    public function listCategorySubcategoriesAndProducts(Request $request, $Code)
    {
        try {

            $subcategories = $this->subcategoryService->listCategorySubcategories($request, $Code);

            $categoryProducts = $this->subcategoryService->listCategoryProducts($request, $Code);

            $hasRequestFilter = FilterChecker::hasFilters($request);

            $category = $this->subcategoryService->fetchCategory($Code);

            $listCategoriesProductColors = $this->subcategoryService->listCategorySubCategoryProductColors($Code, 'category', $categoryProducts, $hasRequestFilter);
            $listCategoriesProductSizes = $this->subcategoryService->listCategorySubCategoryProductSizes($Code, 'category', $categoryProducts, $hasRequestFilter);

            return response()->json([
                'result' => [
                    'subcategories' => $subcategories,
                    'category' => $category,
                    'products' => $categoryProducts,
                    'colors' => $listCategoriesProductColors,
                    'sizes' => $listCategoriesProductSizes,
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

    public function listSubcategoryProducts(Request $request, $Code)
    {
        try {

            $subcategoryProducts = $this->subcategoryService->listSubcategoryProducts($request, $Code);

            $hasRequestFilter = FilterChecker::hasFilters($request);

            $subcategory = $this->subcategoryService->fetchSubCategory($Code);

            $listSubcategoriesProductColors = $this->subcategoryService->listCategorySubCategoryProductColors($Code, 'subcategory', $subcategoryProducts, $hasRequestFilter);
            $listSubcategoriesProductSizes = $this->subcategoryService->listCategorySubCategoryProductSizes($Code, 'subcategory', $subcategoryProducts, $hasRequestFilter);

            return response()->json([
                'result' => [
                    'subcategory' => $subcategory,
                    'products' => $subcategoryProducts,
                    'colors' => $listSubcategoriesProductColors,
                    'sizes' => $listSubcategoriesProductSizes,

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
