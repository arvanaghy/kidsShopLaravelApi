<?php

namespace App\Services;

use App\Helpers\StringHelper;
use App\Models\ProductModel;
use App\Models\SubCategoryModel;
use App\Services\ImageServices\CategoryImageService;
use App\Services\ImageServices\ProductImageService;
use App\Services\ImageServices\SubcategoryImageService;
use App\Traits\Cacheable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubcategoryService
{

    protected $subcategoryImageService;
    protected $categoryImageService;
    protected $productImageService;
    protected $active_company;
    protected $companyService;
    protected $financial_period = null;
    // protected $ttl = 60 * 30;
    protected $ttl = 0;

    use Cacheable;


    public function __construct(SubcategoryImageService $subcategoryImageService, CompanyService $companyService, CategoryImageService $categoryImageService, ProductImageService $productImageService)
    {
        $this->subcategoryImageService = $subcategoryImageService;
        $this->active_company = $companyService->getActiveCompany();
        $this->companyService = $companyService;
        $this->categoryImageService = $categoryImageService;
        $this->productImageService = $productImageService;
    }


    protected function getProductsByCategory($gCode)
    {
        $cacheKey = "kidsShopRedis_products_by_category_{$gCode}";
        return $this->cacheQuery($cacheKey, $this->ttl, function () use ($gCode) {
            return ProductModel::with(['productSizeColor'])
                ->where('CodeCompany', $this->active_company)
                ->whereHas('productSizeColor', function ($query) {
                    $query->havingRaw('SUM(Mande) > 0');
                })
                ->where('CShowInDevice', 1)
                ->select([
                    'GCode',
                    'GName',
                    'SCode',
                    'SName',
                    'Code',
                    'CodeKala',
                    'PicName',
                ])->where('GCode', $gCode)
                ->where('CShowInDevice', 1)
                ->whereNotNull('PicName')
                ->orderBy('Code', 'ASC')
                ->limit(10)
                ->get();
        });
    }

    protected function setRandomProductImagesForCategory($category)
    {
        $products = $this->getProductsByCategory($category->Code);

        $randomProduct = null;
        if ($products->isNotEmpty()) {
            $validProducts = $products->whereNotNull('PicName');
            if ($validProducts->isNotEmpty()) {
                $randomProduct = $validProducts->random(1)->first();
            }
        }

        if ($randomProduct && !empty($randomProduct->PicName)) {
            $updateData = [
                'CChangePic' => 0,
                'PicName' => $randomProduct->PicName
            ];
        } else {
            $updateData = ['CChangePic' => 0, 'PicName' => null];
        }

        DB::table('KalaGroup')->where('Code', $category->Code)->update($updateData);
    }

    protected function setRequestFilter($request, $baseQuery = null)
    {
        if ($search = $request?->query('search')) {
            $search = StringHelper::normalizePersianCharacters($search);
            $baseQuery->where('Name', 'LIKE', "%{$search}%");
        }

        if ($size = $request?->query('size')) {
            $sizes = explode(',', $size);
            $baseQuery->whereHas('productSizeColor', function ($query) use ($sizes) {
                $query->whereIn('SizeNum', $sizes);
            });
        }

        if ($color = $request?->query('color')) {
            $colors = explode(',', $color);
            $baseQuery->whereHas('productSizeColor', function ($query) use ($colors) {
                $query->whereIn('ColorCode', $colors);
            });
        }

        if ($sortPrice = $request?->query('sort_price')) {
            $sortPrice = in_array($sortPrice, ['asc', 'desc']) ? $sortPrice : 'asc';
            $baseQuery->orderBy('SPrice', $sortPrice);
        } else {
            $baseQuery->orderBy('Code', 'DESC');
        }

        return $baseQuery;
    }


    protected function baseProductQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return ProductModel::with(['productSizeColor', 'productImages' => fn($query) => $query->select('Code', 'PicName', 'Def', 'CodeKala')])
            ->where('CodeCompany', $this->active_company)
            ->whereHas('productSizeColor', function ($query) {
                $query->havingRaw('SUM(Mande) > 0');
            })
            ->where('CShowInDevice', 1)
            ->select([
                'GCode',
                'GName',
                'SCode',
                'SName',
                'Code',
                'CodeKala',
                'Name',
                'Vahed',
                'SPrice',
                'created_at'
            ]);
    }


    public function listCategorySubCategoryProductColors($Code = null, $type = 'category',  $productResult = null, $hasRequestFilter = false)
    {
        $productCodes = null;
        $cacheKeyPrefix = 'kidsShopRedis_product_colors_' . $type . '_' . $Code . '_' . $this->active_company;

        switch ($type) {
            case 'subcategory':
                $query = ProductModel::where('CodeCompany', $this->active_company)->where('CShowInDevice', 1)
                    ->where('SCode', $Code);
                break;
            case 'category':
            default:
                $query = ProductModel::where('CodeCompany', $this->active_company)->where('CShowInDevice', 1)
                    ->where('GCode', $Code);
                break;
        }

        if (!$hasRequestFilter) {
            $cacheKey = $cacheKeyPrefix . '_all_' . $type . '_' . $Code;
            return $this->cacheQuery($cacheKey, $this->ttl, function () use ($query) {
                $query = $query
                    ->whereHas('productSizeColor', function ($subQuery) {
                        $subQuery->havingRaw('SUM(Mande) > 0');
                    })
                    ->join('AV_KalaSizeColorMande_View', 'AV_KalaSizeColorMande_View.CodeKala', '=', 'AV_KalaList_View.Code')
                    ->select('AV_KalaSizeColorMande_View.ColorCode', 'AV_KalaSizeColorMande_View.ColorName', 'AV_KalaSizeColorMande_View.RGB')
                    ->orderBy('AV_KalaList_View.Code', 'DESC');

                $products = $query->get();

                if ($products->isEmpty()) {
                    return [];
                }

                $colors = [];
                foreach ($products as $product) {
                    if (!empty($product->ColorCode) && !empty($product->ColorName)) {
                        $colors[] = [
                            'ColorCode' => $product->ColorCode,
                            'ColorName' => $product->ColorName,
                            'RGB' => $product->RGB
                        ];
                    }
                }

                if (empty($colors)) {
                    return [];
                }

                return array_values(array_unique($colors, SORT_REGULAR));
            });
        }

        if ($productResult instanceof \Illuminate\Database\Eloquent\Model) {
            $productCodes = [$productResult->Code];
        } elseif ($productResult instanceof \Illuminate\Database\Eloquent\Collection || $productResult instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $productCodes = $productResult->pluck('Code')->toArray();
        }

        $cacheKey = $cacheKeyPrefix . '_' . ($productCodes ? md5(json_encode($productCodes)) : 'empty');

        return $this->cacheQuery($cacheKey, $this->ttl, function () use ($query, $productCodes) {
            $query = $query->whereHas('productSizeColor', function ($subQuery) {
                $subQuery->havingRaw('SUM(Mande) > 0');
            })
                ->join('AV_KalaSizeColorMande_View', 'AV_KalaSizeColorMande_View.CodeKala', '=', 'AV_KalaList_View.Code')
                ->select('AV_KalaSizeColorMande_View.ColorCode', 'AV_KalaSizeColorMande_View.ColorName', 'AV_KalaSizeColorMande_View.RGB');

            if ($productCodes && !empty($productCodes)) {
                $query->whereIn('AV_KalaList_View.Code', $productCodes);
            } else {
                return [];
            }

            $query->orderBy('AV_KalaList_View.Code', 'DESC');

            $products = $query->get();

            if ($products->isEmpty()) {
                return [];
            }

            $colors = [];
            foreach ($products as $product) {
                if (!empty($product->ColorCode) && !empty($product->ColorName)) {
                    $colors[] = [
                        'ColorCode' => $product->ColorCode,
                        'ColorName' => $product->ColorName,
                        'RGB' => $product->RGB
                    ];
                }
            }

            if (empty($colors)) {
                return [];
            }

            return array_values(array_unique($colors, SORT_REGULAR));
        });
    }


    public function listCategorySubCategoryProductSizes($Code = null, $type = 'category',  $productResult = null, $hasRequestFilter = false)
    {
        $productCodes = null;
        $cacheKeyPrefix = 'kidsShopRedis_product_sizes_' . $type . '_' . $Code . '_' .  $this->active_company;

        switch ($type) {
            case 'subcategory':
                $query = ProductModel::where('CodeCompany', $this->active_company)->where('CShowInDevice', 1)->where('SCode', $Code);
                break;
            case 'category':
            default:
                $query = ProductModel::where('CodeCompany', $this->active_company)->where('CShowInDevice', 1)
                    ->where('GCode', $Code);
                break;
        }

        if (!$hasRequestFilter) {
            $cacheKey = $cacheKeyPrefix . '_all_' . $type;
            return $this->cacheQuery($cacheKey, $this->ttl, function () use ($query) {
                $query = $query->whereHas('productSizeColor', function ($subQuery) {
                    $subQuery->havingRaw('SUM(Mande) > 0');
                })
                    ->join('AV_KalaSizeColorMande_View', 'AV_KalaSizeColorMande_View.CodeKala', '=', 'AV_KalaList_View.Code')
                    ->select('AV_KalaSizeColorMande_View.SizeNum')
                    ->orderBy('AV_KalaList_View.Code', 'DESC');

                $products = $query->get();

                if ($products->isEmpty()) {
                    return [];
                }

                $sizes = [];
                foreach ($products as $product) {
                    if (!empty($product->SizeNum)) {
                        $sizes[] = $product->SizeNum;
                    }
                }

                if (empty($sizes)) {
                    return [];
                }

                $uniqueSizes = array_values(array_unique($sizes, SORT_REGULAR));
                sort($uniqueSizes);
                return $uniqueSizes;
            });
        }

        if ($productResult instanceof \Illuminate\Database\Eloquent\Model) {
            $productCodes = [$productResult->Code];
        } elseif ($productResult instanceof \Illuminate\Database\Eloquent\Collection || $productResult instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $productCodes = $productResult->pluck('Code')->toArray();
        }

        $cacheKey = $cacheKeyPrefix . '_' . ($productCodes ? md5(json_encode($productCodes)) : 'empty');

        return $this->cacheQuery($cacheKey, $this->ttl, function () use ($query, $productCodes) {
            $query = $query->whereHas('productSizeColor', function ($subQuery) {
                $subQuery->havingRaw('SUM(Mande) > 0');
            })
                ->join('AV_KalaSizeColorMande_View', 'AV_KalaSizeColorMande_View.CodeKala', '=', 'AV_KalaList_View.Code')
                ->select('AV_KalaSizeColorMande_View.SizeNum');

            if ($productCodes && !empty($productCodes)) {
                $query->whereIn('AV_KalaList_View.Code', $productCodes);
            } else {
                return [];
            }

            $query->orderBy('AV_KalaList_View.Code', 'DESC');

            $products = $query->get();

            if ($products->isEmpty()) {
                return [];
            }

            $sizes = [];
            foreach ($products as $product) {
                if (!empty($product->SizeNum)) {
                    $sizes[] = $product->SizeNum;
                }
            }

            if (empty($sizes)) {
                return [];
            }

            $uniqueSizes = array_values(array_unique($sizes, SORT_REGULAR));
            sort($uniqueSizes);
            return $uniqueSizes;
        });
    }

    protected function getProductsBySubcategory($sCode)
    {
        $cacheKey = "kidsShopRedis_products_by_subcategory_{$sCode}";
        $products = $this->cacheQuery($cacheKey, $this->ttl, function () use ($sCode) {
            $query = ProductModel::with(['productSizeColor'])
                ->where('CodeCompany', $this->active_company)
                ->whereHas('productSizeColor', function ($query) {
                    $query->havingRaw('SUM(Mande) > 0');
                })
                ->where('CShowInDevice', 1)
                ->select([
                    'GCode',
                    'GName',
                    'SCode',
                    'SName',
                    'Code',
                    'CodeKala',
                    'PicName',
                ])
                ->where('SCode', $sCode)
                ->whereNotNull('PicName')
                ->orderBy('Code', 'ASC')
                ->limit(10)
                ->get();
            return $query;
        });
        return $products;
    }

    protected function setRandomProductImagesForSubcategories($subcategories)
    {
        foreach ($subcategories as $subcategory) {
            $products = $this->getProductsBySubcategory($subcategory->Code);


            $randomProduct = null;
            if ($products->isNotEmpty()) {
                $validProducts = $products->whereNotNull('PicName');
                if ($validProducts->isNotEmpty()) {
                    $randomProduct = $validProducts->random(1)->first();
                }
            }

            if ($randomProduct && !empty($randomProduct->PicName)) {
                $updateData = [
                    'CChangePic' => 0,
                    'PicName' => $randomProduct->PicName
                ];
            } else {
                $updateData = ['CChangePic' => 0, 'PicName' => null];
            }

            DB::table('KalaSubGroup')->where('Code', $subcategory->Code)->update($updateData);
        }
    }

    public function listCategorySubcategories($request, $Code)
    {

        $queryParams = $request ? $request->query() : [];
        $page = $request ? $request->query('subcategory_page', 1) : 1;
        $cacheKey = 'kidsShopRedis_list_category_subcategories_' . $Code . '_' . md5(json_encode($queryParams) . '_page_' . $page);

        $results = $this->cacheQuery($cacheKey, $this->ttl, function () use ($Code) {
            $subcategories = SubCategoryModel::select('Code', 'Name', 'PicName')->where('CodeCompany', $this->active_company)
                ->where('CodeGroup', $Code)->orderBy('Code', 'DESC')->paginate(12, ['*'], 'subcategory_page');

            $this->setRandomProductImagesForSubcategories($subcategories->items());

            return $subcategories;
        });

        if ($request) {
            $results->appends($request->query());
        }

        return $results;
    }

    public function fetchCategory($Code)
    {
        $cache_key = 'kidsShopRedis_category_' . md5($Code . '_' . $this->active_company);
        return $this->cacheQuery($cache_key, $this->ttl, function () use ($Code) {

            $result = SubCategoryModel::where('CodeCompany', $this->active_company)->where('CodeGroup', $Code)->first();

            $this->setRandomProductImagesForCategory($result);

            return $result;
        });
    }

    public function fetchSubCategory($Code)
    {

        $cahce_key = 'kidsShopRedis_subcategory_' . md5($Code . '_' . $this->active_company);

        return $this->cacheQuery($cahce_key, $this->ttl, function () use ($Code) {

            $subcategory = SubCategoryModel::where('CodeCompany', $this->active_company)->where('Code', $Code)->first();

            $this->setRandomProductImagesForSubcategories([$subcategory]);
            unset($subcategory->Pic);
            unset($subcategory->CPic);

            return $subcategory;
        });
    }

    public function listCategoryProducts($request, $categoryCode)
    {

        $queryParams = $request ? $request->query() : [];
        $productPage = $request ? $request->query('product_page', 1) : 1;
        $subcategoryPage = $request ? $request->query('subcategory_page', 1) : 1;
        $cacheKey = 'kidsShopRedis_list_category_products_' . md5(json_encode($queryParams) . '_category_' . $categoryCode . '_product_page_' . $productPage . '_subcategory_page_' . $subcategoryPage);

        $results = $this->cacheQuery($cacheKey, $this->ttl, function () use ($request, $categoryCode) {
            $baseQuery = $this->baseProductQuery();

            $baseQuery->where('GCode', $categoryCode);

            $baseQuery = $this->setRequestFilter($request, $baseQuery);

            $results = $baseQuery->paginate(24, ['*'], 'product_page');

            return $results;
        });

        if ($request) {
            $results->appends($request->query());
        }

        return $results;
    }

    public function listSubcategoryProducts($request, $subcategoryCode)
    {

        $queryParams = $request ? $request->query() : [];
        $productPage = $request ? $request->query('product_page', 1) : 1;
        $cacheKey = 'kidsShopRedis_list_subcategory_products_' . md5(json_encode($queryParams) . '_subcategory_' . $subcategoryCode . '_product_page_' . $productPage);

        $results = $this->cacheQuery($cacheKey, $this->ttl, function () use ($request, $subcategoryCode) {
            $baseQuery = $this->baseProductQuery();

            $baseQuery->where('SCode', $subcategoryCode);

            $baseQuery = $this->setRequestFilter($request, $baseQuery);

            $results = $baseQuery->paginate(24, ['*'], 'product_page');


            return $results;
        });

        if ($request) {
            $results->appends($request->query());
        }

        return $results;
    }
}
