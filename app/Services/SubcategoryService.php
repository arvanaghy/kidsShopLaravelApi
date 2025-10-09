<?php

namespace App\Services;

use App\Helpers\StringHelper;
use App\Models\ProductModel;
use App\Models\SubCategoryModel;
use App\Services\ImageServices\CategoryImageService;
use App\Services\ImageServices\ProductImageService;
use App\Services\ImageServices\SubcategoryImageService;
use Illuminate\Support\Facades\Cache;
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


    public function __construct(SubcategoryImageService $subcategoryImageService, CompanyService $companyService, CategoryImageService $categoryImageService, ProductImageService $productImageService)
    {
        $this->subcategoryImageService = $subcategoryImageService;
        $this->active_company = $companyService->getActiveCompany();
        $this->companyService = $companyService;
        $this->categoryImageService = $categoryImageService;
        $this->productImageService = $productImageService;
    }

    protected function processCategoryImage($category)
    {
        if ($category->CChangePic == 1) {
            if (!empty($category->PicName)) {
                $this->categoryImageService->removeCategoryImage($category);
            }

            if (!empty($category->Pic)) {
                $picName = Str::random(16);
                $this->categoryImageService->processCategoryImage($category, $picName);
                $updateData = ['CChangePic' => 0, 'PicName' => $picName];
            } else {
                $updateData = ['CChangePic' => 0, 'PicName' => null];
            }

            DB::table('KalaGroup')->where('Code', $category->Code)->update($updateData);
        }
    }

    protected function processSubCategoryImage($subcategory)
    {
        if ($subcategory->CChangePic == 1) {
            if (!empty($subcategory->PicName)) {
                $this->subcategoryImageService->removeSubcategoryImage($subcategory);
            }

            if (!empty($subcategory->Pic)) {
                $picName = Str::random(16);
                $this->subcategoryImageService->processSubcategoryImage($subcategory, $picName);
                $updateData = ['CChangePic' => 0, 'PicName' => $picName];
            } else {
                $updateData = ['CChangePic' => 0, 'PicName' => null];
            }

            DB::table('KalaSubGroup')->where('Code', $subcategory->Code)->update($updateData);
        }
    }


    protected function processSubcategoriesListImageCreation($subcategories)
    {
        foreach ($subcategories as $image) {
            if ($image->CChangePic == 1) {
                if (!empty($image->PicName)) {
                    $this->subcategoryImageService->removeSubCategoryImage($image);
                }

                if (!empty($image->Pic)) {
                    $picName = Str::random(16);
                    $this->subcategoryImageService->processSubcategoryImage($image, $picName);
                    $updateData = ['CChangePic' => 0, 'PicName' => $picName];
                } else {
                    $updateData = ['CChangePic' => 0, 'PicName' => null];
                }

                DB::table('KalaSubGroup')->where('Code', $image->Code)->update($updateData);
            }
        }
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

    /**
     * Base query for products.
     */
    protected function baseProductQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return ProductModel::with(['productSizeColor'])
            ->where('CodeCompany', $this->active_company)
            ->whereHas('productSizeColor', function ($query) {
                $query->havingRaw('SUM(Mande) > 0');
            })
            ->where('CShowInDevice', 1)
            ->select([
                'CChangePic',
                'GCode',
                'GName',
                'SCode',
                'SName',
                'Code',
                'CodeKala',
                'Name',
                'Vahed',
                'SPrice',
                'PicName',
                'Pic',
                'ImageCode',
                'created_at'
            ]);
    }


    protected function processProductListImageCreation($images): void
    {
        if (empty($images)) {
            return;
        }
        $updates = [];
        foreach ($images as $image) {
            $gCode = is_array($image) ? ($image['GCode'] ?? null) : ($image->GCode ?? null);
            $sCode = is_array($image) ? ($image['SCode'] ?? null) : ($image->SCode ?? null);
            $cChangePic = is_array($image) ? ($image['CChangePic'] ?? null) : ($image->CChangePic ?? null);
            $pic = is_array($image) ? ($image['Pic'] ?? null) : ($image->Pic ?? null);
            $picName = is_array($image) ? ($image['PicName'] ?? null) : ($image->PicName ?? null);

            $product = [
                'GCode' => $gCode,
                'SCode' => $sCode,
            ];

            if ($cChangePic == 1 && !empty($pic) && $picName == null) {
                $picName = Str::random(16);
                if ($this->productImageService->processProductImage($product, $image, $picName)) {
                    $updates[] = ['Code' => $image->ImageCode, 'PicName' => $picName];
                }
            }
        }
        if (!empty($updates)) {
            DB::transaction(function () use ($updates) {
                foreach ($updates as $update) {
                    DB::table('KalaImage')->where('Code', $update['Code'])->update(['PicName' => $update['PicName']]);
                }
            });
        }
    }


    public function listCategorySubCategoryProductColors($Code = null, $type = 'category',  $productResult = null, $hasRequestFilter = false)
    {
        $productCodes = null;
        $cacheKeyPrefix = 'product_colors_' . $type . '_' . $Code . '_' . $this->active_company;

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
            return Cache::remember($cacheKey, 60 * 30, function () use ($query) {
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

        return Cache::remember($cacheKey, 60 * 30, function () use ($query, $productCodes) {
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
        $cacheKeyPrefix = 'product_sizes_' . $type . '_' . $Code . '_' .  $this->active_company;

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
            return Cache::remember($cacheKey, 60 * 30, function () use ($query) {
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

        return Cache::remember($cacheKey, 60 * 30, function () use ($query, $productCodes) {
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

    public function listCategorySubcategories($request, $Code)
    {

        $queryParams = $request ? $request->query() : [];
        $page = $request ? $request->query('subcategory_page', 1) : 1;
        $cacheKey = 'list_category_subcategories_' . md5(json_encode($queryParams) . '_page_' . $page);

        $results = Cache::remember($cacheKey, 60 * 30, function () use ($Code) {
            $baseQuery = SubCategoryModel::select('Pic', 'Code', 'CChangePic', 'PicName')
                ->where('CodeCompany', $this->active_company)
                ->where('CodeGroup', $Code)
                ->orderBy('Code', 'DESC');

            $results = $baseQuery->paginate(12, ['*'], 'subcategory_page');

            $this->processSubcategoriesListImageCreation($results->items());

            $results->setCollection($results->getCollection()->map(function ($item) {
                unset($item->Pic);
                return $item;
            }));

            return $results;
        });

        if ($request) {
            $results->appends($request->query());
        }

        return $results;

        // $subcategories->appends(['subcategory_page' => $subcategoryPage, 'product_page' => $productPage]);
    }

    public function fetchCategory($Code)
    {
        $cache_key = 'category_' . md5($Code . '_' . $this->active_company);
        return Cache::remember($cache_key, 60 * 30, function () use ($Code) {

            $result = SubCategoryModel::where('CodeCompany', $this->active_company)->where('CodeGroup', $Code)->first();

            $this->processCategoryImage($result);

            unset($result->Pic);

            return $result;
        });
    }

    public function fetchSubCategory($Code)
    {

        $cahce_key = 'subcategory_' . md5($Code . '_' . $this->active_company);

        return Cache::remember($cahce_key, 60 * 30, function () use ($Code) {

            $subcategory = SubCategoryModel::where('CodeCompany', $this->active_company)->where('Code', $Code)->first();

            $this->processSubCategoryImage($subcategory);

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
        $cacheKey = 'list_category_products_' . md5(json_encode($queryParams) . '_category_' . $categoryCode . '_product_page_' . $productPage . '_subcategory_page_' . $subcategoryPage);

        $results = Cache::remember($cacheKey, 60 * 30, function () use ($request, $categoryCode) {
            $baseQuery = $this->baseProductQuery();

            $baseQuery->where('GCode', $categoryCode);

            $baseQuery = $this->setRequestFilter($request, $baseQuery);

            $results = $baseQuery->paginate(24, ['*'], 'product_page');

            $this->processProductListImageCreation($results->items());

            $results->setCollection($results->getCollection()->map(function ($item) {
                unset($item->Pic);
                return $item;
            }));

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
        $cacheKey = 'list_subcategory_products_' . md5(json_encode($queryParams) . '_subcategory_' . $subcategoryCode . '_product_page_' . $productPage);

        $results = Cache::remember($cacheKey, 60 * 30, function () use ($request, $subcategoryCode) {
            $baseQuery = $this->baseProductQuery();

            $baseQuery->where('SCode', $subcategoryCode);

            $baseQuery = $this->setRequestFilter($request, $baseQuery);

            $results = $baseQuery->paginate(24, ['*'], 'product_page');

            $this->processProductListImageCreation($results->items());

            $results->setCollection($results->getCollection()->map(function ($item) {
                unset($item->Pic);
                return $item;
            }));

            return $results;
        });

        if ($request) {
            $results->appends($request->query());
        }

        return $results;
    }
}
