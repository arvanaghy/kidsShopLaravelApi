<?php

namespace App\Services;

use App\Models\ProductImagesModel;
use App\Models\ProductModel;
use App\Services\ImageServices\ProductImageService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ProductService
{

    protected $companyService;
    protected $productImageService;
    protected $active_company;


    public function __construct(CompanyService $companyService, ProductImageService $productImageService)
    {
        $this->companyService = $companyService;
        $this->productImageService = $productImageService;
        $this->active_company = $this->companyService->getActiveCompany();
    }

    /**
     * Base query for products.
     */
    protected function baseProductQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return ProductModel::where('CodeCompany', $this->active_company)
            ->where('CShowInDevice', 1)
            ->select([
                'CChangePic',
                'GCode',
                'GName',
                'Comment',
                'SCode',
                'SName',
                'Code',
                'CodeKala',
                'Name',
                'UCode',
                'Vahed',
                'SPrice',
                'PicName'
            ]);
    }


    public function showSingleProduct($product_code)
    {
        $product = ProductModel::where('Code', $product_code)->firstOrFail();
        $productImages = ProductImagesModel::where('CodeKala', $product->Code)->get();

        foreach ($productImages as $image) {
            if (!empty($image->Pic)) {
                $picName = ceil($image->Code) . '_' . Carbon::parse($image->created_at)->timestamp;
                if ($this->productImageService->processSingleProductImage($product, $image, $picName)) {
                    DB::table('KalaImage')->where('Code', $image->Code)->update(['PicName' => $picName]);
                }
            }
        }

        DB::table('Kala')->where('Code', $product->Code)->update(['CChangePic' => 0]);

        $result = ProductModel::with([
            'productSizeColor',
            'productImages' => fn($query) => $query->select('Code', 'PicName', 'Def', 'CodeKala')
        ])
            ->where('Code', $product_code)
            ->baseProductQuery()
            ->first();

        return $result;
    }

    /**
     * Get related products, excluding the specified product code.
     */
    public function relatedProducts($GCode, $SCode, $excludeCode = null)
    {
        try {
            $imageQuery = ProductModel::where('CodeCompany', $this->active_company)
                ->where('GCode', $GCode)
                ->where('SCode', $SCode)
                ->where('CShowInDevice', 1)
                ->when($excludeCode, fn($query) => $query->where('Code', '!=', $excludeCode))
                ->select(['Pic', 'ImageCode', 'created_at', 'GCode', 'SCode', 'Code', 'CChangePic', 'PicName'])
                ->orderBy('UCode', 'ASC')
                ->limit(16);

            $this->productImageService->updateProductImages($imageQuery->get());

            return $this->baseProductQuery()
                ->with(['productSizeColor'])
                ->where('GCode', $GCode)
                ->where('SCode', $SCode)
                ->when($excludeCode, fn($query) => $query->where('Code', '!=', $excludeCode))
                ->orderBy('UCode', 'ASC')
                ->limit(16)
                ->get();
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage(), 'result' => null], 503);
        }
    }

    /**
     * Get offered (festival) products.
     */
    public function suggestedProducts()
    {
        try {
            $imageQuery = ProductModel::where('CodeCompany', $this->active_company)
                ->where('CShowInDevice', 1)
                ->where('CFestival', 1)
                ->select(['Pic', 'ImageCode', 'created_at', 'GCode', 'SCode', 'Code', 'CChangePic', 'PicName'])
                ->orderBy('UCode', 'ASC')
                ->limit(16);

            $this->productImageService->updateProductImages($imageQuery->get());

            return $this->baseProductQuery()
                ->with(['productSizeColor'])
                ->where('CFestival', 1)
                ->orderBy('UCode', 'ASC')
                ->limit(16)
                ->get();
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage(), 'result' => null], 503);
        }
    }

    /**
     * List all products with pagination
     */
    public function listAllProducts(Request $request)
    {
        try {
            $productsQuery = $this->baseProductQuery();

            if ($sortPrice = $request->query('sortPrice')) {
                $productsQuery->orderBy('SPrice', $sortPrice);
            }

            if ($search = $request->query('search')) {
                $productsQuery->where('Name', 'LIKE', "%{$search}%");
            }

            $imageProducts = $productsQuery->clone()
                ->select(['Pic', 'ImageCode', 'created_at', 'CodeK', 'GCode', 'SCode', 'PicName'])
                ->paginate(8);

            $this->productImageService->updateProductImages($imageProducts);

            $products = $productsQuery->with(['productSizeColor'])
                ->paginate(8, ['*'], 'page');

            return response()->json([
                'products' => $products,
                'message' => trans('messages.products_listed_successfully')
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
