<?php

namespace App\Services;

use App\Helpers\StringHelper;
use App\Models\BestSellModel;
use App\Models\ProductImagesModel;
use App\Models\ProductModel;
use App\Repositories\ImageUpdater;
use App\Services\ImageServices\ProductImageService;
use App\Traits\Cacheable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductService
{

    protected $companyService;
    protected $productImageService;
    protected $active_company;
    protected $financial_period;
    protected $imageUpdater;

    // private $ttl = 60 * 30;
    private $ttl = 0;

    use Cacheable;


    public function __construct(CompanyService $companyService, ProductImageService $productImageService, ImageUpdater $imageUpdater)
    {
        $this->companyService = $companyService;
        $this->productImageService = $productImageService;
        $this->active_company = $this->companyService->getActiveCompany();
        $this->imageUpdater = $imageUpdater;
        if ($this->active_company) {
            $this->financial_period = $this->companyService->getFinancialPeriod($this->active_company);
        }
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
                // 'CChangePic',
                'GCode',
                'GName',
                'SCode',
                'SName',
                'Code',
                'CodeKala',
                'Name',
                'Vahed',
                'SPrice',
                // 'PicName',
                // 'ImageCode',
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

        $this->imageUpdater->productImagesUpdate($updates);
    }


    public function showSingleProduct($product_code)
    {
        $product = ProductModel::with([
            'productSizeColor',
            'productImages' => fn($query) => $query->select('Code', 'PicName', 'Def', 'CodeKala')
        ])
            ->where('Code', $product_code)
            ->where('CodeCompany', $this->active_company)
            ->whereHas('productSizeColor', function ($query) {
                $query->havingRaw('SUM(Mande) > 0');
            })
            ->select([
                // 'CChangePic',
                'GCode',
                'GName',
                'Comment',
                'SCode',
                'SName',
                'Code',
                'CodeKala',
                'Name',
                'Vahed',
                'SPrice',
                // 'PicName',
                'created_at'
            ])
            ->first();

        if (!$product) {
            return null;
        }

        // if ($product->CChangePic == 1) {
        //     $updates = [];
        //     foreach ($product->productImages as $image) {
        //         if ($image->Pic && $image->PicName == null) {
        //             $picName = Str::random(16);
        //             if ($this->productImageService->processProductImage($product, $image, $picName)) {
        //                 $updates[] = ['Code' => $image->Code, 'PicName' => $picName];
        //             }
        //         }
        //     }

        //     if (!empty($updates)) {
        //         $this->imageUpdater->productImagesUpdate($updates);
        //         $product->update(['CChangePic' => 0]);
        //     }
        // }

        // if ($product->productImages) {
        //     $product->productImages->each(function ($image) {
        //         unset($image->Pic);
        //     });
        // }

        return $product;
    }

    /**
     * Get related products, excluding the specified product code.
     */
    public function relatedProducts($GCode, $SCode, $excludeCode = null)
    {
        $cacheKey = "kidsShopRedis_related_products_{$GCode}_{$SCode}_" . ($excludeCode ?? 'no_exclude');
        return $this->cacheQuery($cacheKey, $this->ttl, function () use ($GCode, $SCode, $excludeCode) {
            $baseQuery = $this->baseProductQuery()
                ->where('GCode', $GCode)
                ->where('SCode', $SCode)
                ->where('CShowInDevice', 1)
                ->when($excludeCode, fn($query) => $query->where('Code', '!=', $excludeCode))
                ->orderBy('Code', 'ASC')
                ->limit(16);

            $results = $baseQuery->get();

            // $this->processProductListImageCreation($results);

            // $results = $results->map(function ($item) {
            //     unset($item->Pic);
            //     return $item;
            // });

            return $results;
        });
    }

    /**
     * Get offered (festival) products.
     */
    public function suggestedProducts($excludeCode = null)
    {

        $cacheKey = "kidsShopRedis_suggested_products_" . ($excludeCode ?? 'no_exclude');
        return $this->cacheQuery($cacheKey, $this->ttl, function () use ($excludeCode) {

            $baseQuery = $this->baseProductQuery()
                ->where('CShowInDevice', 1)
                ->where('CFestival', 1)
                ->when($excludeCode, fn($query) => $query->where('Code', '!=', $excludeCode))
                ->orderBy('Code', 'ASC')
                ->limit(16);

            $results = $baseQuery->get();

            // $this->processProductListImageCreation($results);

            // $results = $results->map(function ($item) {
            //     unset($item->Pic);
            //     return $item;
            // });

            return $results;
        });
    }

    /**
     * Get newest products, excluding products with zero Mande.
     * Products are ordered by Code in ascending order and limited to 8 records.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function homePageNewestProducts()
    {

        return $this->cacheQuery('kidsShopRedis_home_page_newest_products', $this->ttl, function () {
            $baseQuery = $this->baseProductQuery()
                ->orderBy('Code', 'DESC')
                ->limit(8);

            $results = $baseQuery->get();

            // $this->processProductListImageCreation($results);
            return $results->map(function ($item) {
                unset($item->Pic);
                return $item;
            });
        });
    }

    /**
     * Get offered products, excluding products with zero Mande.
     * Products are ordered by Code in ascending order and limited to 8 records.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function homePageOfferedProducts()
    {
        return $this->cacheQuery('kidsShopRedis_home_page_offered_products', $this->ttl, function () {
            $baseQuery = $this->baseProductQuery()
                ->where('CFestival', 1)
                ->orderBy('Code', 'ASC')
                ->limit(8);

            $results = $baseQuery->get();

            // $this->processProductListImageCreation($results);
            $results = $results->map(function ($item) {
                unset($item->Pic);
                return $item;
            });

            return $results;
        });
    }

    public function homePageBestSellingProducts()
    {
        return $this->cacheQuery('kidsShopRedis_home_page_best_selling_products', $this->ttl, function () {

            $baseQuery = BestSellModel::with(['productSizeColor'])
                ->where('CodeDoreMali', $this->financial_period)
                ->whereHas('productSizeColor', function ($query) {
                    $query->havingRaw('SUM(Mande) > 0');
                })
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
                    'Tedad',
                    'Foroosh',
                    'Sood',
                    'CChangePic',
                    'PicName',
                    // 'Pic',
                    'ImageCode',
                    'created_at'
                ])
                ->orderBy('Foroosh', 'DESC')
                ->limit(8);

            $results = $baseQuery->get();

            // $this->processProductListImageCreation($results);

            $results = $results->map(function ($item) {
                unset($item->Pic);
                return $item;
            });

            return $results;
        });
    }


    public function listProductColors($productResult = null, $hasRequestFilter = false, $type = 'all')
    {
        $productCodes = null;
        $cacheKeyPrefix = 'kidsShopRedis_product_colors_' . $this->active_company;

        switch ($type) {
            case 'bestseller':
                $query = ProductModel::where('CodeCompany', $this->active_company)->where('CShowInDevice', 1);
                break;
            case 'offers':
                $query = ProductModel::where('CodeCompany', $this->active_company)->where('CFestival', 1)->where('CShowInDevice', 1);
                break;
            case 'all':
            default:
                $query = ProductModel::where('CodeCompany', $this->active_company)->where('CShowInDevice', 1);
                break;
        }

        if (!$hasRequestFilter) {
            $cacheKey = $cacheKeyPrefix . '_all_' . $type;
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
                    if (!empty($product->ColorName)) {
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
                if (!empty($product->ColorName)) {
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


    public function listProductSizes($productResult = null, $hasRequestFilter = false, $type = 'all')
    {
        $productCodes = null;
        $cacheKeyPrefix = 'kidsShopRedis_product_sizes_' . $this->active_company;

        switch ($type) {
            case 'bestseller':
                $query = ProductModel::where('CodeCompany', $this->active_company)->where('CShowInDevice', 1);
                break;
            case 'offers':
                $query = ProductModel::where('CodeCompany', $this->active_company)->where('CFestival', 1)->where('CShowInDevice', 1);
                break;
            case 'all':
            default:
                $query = ProductModel::where('CodeCompany', $this->active_company)->where('CShowInDevice', 1);
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
                    if ($product->SizeNum !== null) {
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

    protected function setRequestFilter($request, $baseQuery = null)
    {
        if ($search = $request?->query('search')) {
            $search = StringHelper::normalizePersianCharacters($search);
            $baseQuery->where('Name', 'LIKE', "%{$search}%");
        }

        if ($request?->has('size')) {
            $size = $request->query('size');
            $sizes = explode(',', $size);
            $baseQuery->whereHas('productSizeColor', function ($query) use ($sizes) {
                $query->whereIn('SizeNum', $sizes);
            });
        }

        if ($request?->has('color')) {
            $color = $request->query('color');
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


    public function listBestSellingProducts($request = null)
    {
        $queryParams = $request ? $request->query() : [];
        $page = $request ? $request->query('product_page', 1) : 1;
        $cacheKey = 'kidsShopRedis_list_best_selling_' . md5(json_encode($queryParams) . '_page_' . $page);

        $results = $this->cacheQuery($cacheKey, $this->ttl, function () use ($request) {
            $baseQuery = BestSellModel::with(['productSizeColor'])
                ->where('CodeDoreMali', $this->financial_period)
                ->whereHas('productSizeColor', function ($query) {
                    $query->havingRaw('SUM(Mande) > 0');
                });

            $baseQuery = $this->setRequestFilter($request, $baseQuery);

            $baseQuery->select([
                'GCode',
                'GName',
                'SCode',
                'SName',
                'Code',
                'CodeKala',
                'Name',
                'Vahed',
                'SPrice',
                'Tedad',
                'Foroosh',
                'Sood',
                'CChangePic',
                'PicName',
                // 'Pic',
                'ImageCode',
                'created_at'
            ]);

            $results = $baseQuery->paginate(24, ['*'], 'product_page');

            // $this->processProductListImageCreation($results->items());

            // $results->setCollection($results->getCollection()->map(function ($item) {
            //     unset($item->Pic);
            //     return $item;
            // }));

            return $results;
        });

        if ($request) {
            $results->appends($request->query());
        }

        return $results;
    }

    public function listAllProducts($request = null)
    {
        $queryParams = $request ? $request->query() : [];
        $page = $request ? $request->query('product_page', 1) : 1;
        $cacheKey = 'kidsShopRedis_list_all_products_' . md5(json_encode($queryParams) . '_page_' . $page);

        $results = $this->cacheQuery($cacheKey, $this->ttl, function () use ($request) {
            $baseQuery = $this->baseProductQuery();

            $baseQuery = $this->setRequestFilter($request, $baseQuery);

            $results = $baseQuery->paginate(24, ['*'], 'product_page');

            // $this->processProductListImageCreation($results->items());

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

    public function listOfferedProducts($request = null)
    {
        $queryParams = $request ? $request->query() : [];
        $page = $request ? $request->query('product_page', 1) : 1;
        $cacheKey = 'kidsShopRedis_list_offered_products_' . md5(json_encode($queryParams) . '_page_' . $page);

        $results = $this->cacheQuery($cacheKey, $this->ttl, function () use ($request) {
            $baseQuery = $this->baseProductQuery();

            $baseQuery->where('CFestival', 1);

            $baseQuery = $this->setRequestFilter($request, $baseQuery);

            $results = $baseQuery->paginate(24, ['*'], 'product_page');

            $this->processProductListImageCreation($results->items());

            $results->setCollection($results->getCollection()->map(function ($item) {
                unset($item->Pic);
                return $item;
            }));

            return $results;
        })
            ->appends($request->query());

        return $results;
    }

    public function insertProductImages($request, $productCode)
    {
        try {
            return DB::transaction(function () use ($request, $productCode) {

                if ($request->hasFile('images')) {
                    $images = $request->file('images');
                } elseif ($request->has('images')) {
                    $images = $request->input('images');
                } elseif ($request->allFiles()) {
                    $images = $request->allFiles()['images'] ?? [];
                } else {
                    throw new \Exception('تصویری وارد نشده است');
                }

                if (empty($images)) {
                    throw new \Exception('تصویری وارد نشده است');
                }

                $product = DB::table('Kala')->where('Code', $productCode)->first();
                if (!$product) {
                    throw new \Exception('محصول مورد نظر یافت نشد');
                }

                foreach ($images as $image) {
                    $picName = Str::random(16);
                    $picName = $productCode . '-' . $picName;

                    if ($image instanceof \Illuminate\Http\UploadedFile) {
                        $this->productImageService->processWebProductImageMaker($productCode, file_get_contents($image->getRealPath()), $picName);
                    } elseif (is_string($image)) {
                        $this->productImageService->processWebProductImageMaker($productCode, $image, $picName);
                    } else {
                        $this->productImageService->processWebProductImageMaker($productCode, $image, $picName);
                    }

                    $productPicName =  env('APP_URL', 'https://api.kidsshop110.ir') . '/web-products-image/webp/' . $productCode . '/' . $picName . '.webp';

                    $insertedPicResult = DB::table('KalaImage')->insert([
                        'CodeKala' => $productCode,
                        'PicName' => $productPicName,
                        'Def' => 0,
                        'Pic' => null,
                    ]);

                    if (!$insertedPicResult) {
                        throw new \Exception('خطا در ذخیره تصویر');
                    }
                    $hasDefPic = $this->checkProductHasDefPic($productCode);

                    if (!$hasDefPic) {
                        DB::table('KalaImage')->where('CodeKala', $productCode)->where('Def', 0)->update(['Def' => 1]);
                    }
                }
            });
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    protected function checkProductHasDefPic($productCode): bool
    {
        $hasDefPic = DB::table('KalaImage')->where('CodeKala', $productCode)->where('Def', 1)->first();
        return $hasDefPic ? true : false;
    }

    public function deleteProductImage($productCode, $imageCode)
    {
        return DB::transaction(function () use ($productCode, $imageCode) {

            $image = DB::table('KalaImage')->where('CodeKala', $productCode)->where('Code', $imageCode)->first();
            if (!$image) {
                throw new \Exception('تصویر مورد نظر یافت نشد');
            }

            $this->productImageService->deleteProductImage($productCode, $image->PicName);

            DB::table('KalaImage')->where('Code', $imageCode)->delete();
            $hasDefPic = $this->checkProductHasDefPic($productCode);

            if (!$hasDefPic) {
                $result = DB::table('KalaImage')->where('CodeKala', $productCode)->first();
                if ($result) {
                    DB::table('KalaImage')->where('CodeKala', $productCode)->where('Code', $result->Code)->where('Def', 0)->update(['Def' => 1]);
                }
            }
        });
    }

    public function insertProductComment($request, $productCode)
    {
        $comment = $request->input('comment');

        DB::table('Kala')->where('Code', $productCode)->update([
            'Comment' => $comment
        ]);
    }


    // public function letsCreateProductImages($request)
    // {
    //     try {
    //         return DB::transaction(function () use ($request) {

    //             $per_page = $request->input('per_page', 24);
    //             $is_reset = $request->input('is_reset', false);

    //             $storage_path = storage_path('app/');
    //             $status_file = $storage_path . 'processed_product_codes.json';

    //             if (!file_exists($storage_path)) {
    //                 mkdir($storage_path, 0755, true);
    //             }

    //             if ($is_reset && file_exists($status_file)) {
    //                 unlink($status_file);
    //             }

    //             $done_items = [];
    //             if (file_exists($status_file)) {
    //                 $content = file_get_contents($status_file);
    //                 $done_items = json_decode($content, true) ?: [];
    //             }

    //             $all_product_codes = [
    //                 8,
    //                 46,
    //                 110,
    //                 117,
    //                 121,
    //                 137,
    //                 152,
    //                 154,
    //                 185,
    //                 205,
    //                 264,
    //                 265,
    //                 267,
    //                 271,
    //                 272,
    //                 273,
    //                 274,
    //                 280,
    //                 285,
    //                 288,
    //                 289,
    //                 291,
    //                 319,
    //                 322,
    //                 323,
    //                 324,
    //                 326,
    //                 327,
    //                 328,
    //                 329,
    //                 330,
    //                 350,
    //                 361,
    //                 362,
    //                 382,
    //                 388,
    //                 390,
    //                 391,
    //                 392,
    //                 393,
    //                 399,
    //                 401,
    //                 403,
    //                 404,
    //                 411,
    //                 412,
    //                 424,
    //                 425,
    //                 486,
    //                 503,
    //                 504,
    //                 506,
    //                 509,
    //                 512,
    //                 513,
    //                 516,
    //                 517,
    //                 518,
    //                 519,
    //                 520,
    //                 521,
    //                 522,
    //                 523,
    //                 524,
    //                 525,
    //                 534,
    //                 538,
    //                 539,
    //                 541,
    //                 543,
    //                 544,
    //                 546,
    //                 549,
    //                 552,
    //                 554,
    //                 558,
    //                 559,
    //                 561,
    //                 562,
    //                 563,
    //                 564,
    //                 566,
    //                 568,
    //                 570,
    //                 572,
    //                 573,
    //                 574,
    //                 575,
    //                 577,
    //                 580,
    //                 584,
    //                 587,
    //                 588,
    //                 589,
    //                 590,
    //                 591,
    //                 592,
    //                 593,
    //                 594,
    //                 595,
    //                 603,
    //                 604,
    //                 610,
    //                 618,
    //                 619,
    //                 623,
    //                 624,
    //                 626,
    //                 628,
    //                 632,
    //                 635,
    //                 637,
    //                 639,
    //                 641,
    //                 642,
    //                 643,
    //                 644,
    //                 645,
    //                 646,
    //                 647,
    //                 648,
    //                 649,
    //                 650,
    //                 651,
    //                 652,
    //                 653,
    //                 654,
    //                 655,
    //                 656,
    //                 658,
    //                 659,
    //                 660,
    //                 661,
    //                 662,
    //                 663,
    //                 665,
    //                 666,
    //                 669,
    //                 670,
    //                 672,
    //                 673,
    //                 674,
    //                 675,
    //                 676,
    //                 677,
    //                 678,
    //                 679,
    //                 680,
    //                 681,
    //                 682,
    //                 683,
    //                 684,
    //                 685,
    //                 686,
    //                 687,
    //                 696,
    //                 697,
    //                 698,
    //                 699,
    //                 700,
    //                 701,
    //                 702,
    //                 703,
    //                 704,
    //                 705,
    //                 706,
    //                 707
    //             ];

    //             $remaining_codes = array_values(array_diff($all_product_codes, $done_items));

    //             if ($is_reset && empty($done_items)) {
    //                 $message = "پردازش از ابتدا شروع شد.";
    //             } elseif ($is_reset) {
    //                 $message = "پردازش بازنشانی شد و از ابتدا شروع می‌شود.";
    //             }

    //             if (empty($remaining_codes)) {
    //                 return [
    //                     'images' => [],
    //                     'current_count' => 0,
    //                     'processed_count' => count($done_items),
    //                     'remaining_count' => 0,
    //                     'is_completed' => true,
    //                     'total_count' => count($all_product_codes),
    //                     'status' => 'completed',
    //                     'message' => isset($message) ? $message . ' همه محصولات پردازش شده‌اند' : 'همه محصولات پردازش شده‌اند'
    //                 ];
    //             }

    //             $current_page_codes = array_slice($remaining_codes, 0, $per_page);

    //             $imagesCodes = [];
    //             foreach ($current_page_codes as $product_code) {
    //                 $images = ProductImagesModel::where('Pic', '!=', null)
    //                     ->where('CodeKala', $product_code)
    //                     ->get();

    //                 foreach ($images as $image) {
    //                     $picName = Str::random(16);
    //                     $picName = $image->Code . '-' . $picName;
    //                     $this->productImageService->processProductImageMaker($product_code, $image, $picName);

    //                     $imagesCodes[] = [
    //                         'Code' => $image->Code,
    //                         'CodeKala' => $product_code
    //                     ];
    //                     $productPicName =  env('APP_URL', 'https://api.kidsshop110.ir') . '/web-products-image/webp/' . $product_code . '/' . $picName . '.webp';
    //                     $image->PicName = $productPicName;
    //                     $image->save();

    //                     \Log::info($productPicName . ' - ' . $image->CodeKala . ' - ' . $image->Code);
    //                 }

    //                 $done_items[] = $product_code;
    //             }

    //             $result = file_put_contents($status_file, json_encode($done_items, JSON_PRETTY_PRINT));

    //             if ($result === false) {
    //                 throw new \Exception("ذخیره فایل وضعیت با خطا مواجه شد");
    //             }

    //             $remaining_codes = array_values(array_diff($all_product_codes, $done_items));

    //             $response = [
    //                 'images' => $imagesCodes,
    //                 'current_processed' => $current_page_codes,
    //                 'current_count' => count($current_page_codes),
    //                 'processed_count' => count($done_items),
    //                 'remaining_count' => count($remaining_codes),
    //                 'is_completed' => empty($remaining_codes),
    //                 'total_count' => count($all_product_codes),
    //                 'status' => empty($remaining_codes) ? 'completed' : 'in_progress',
    //                 'status_file' => $status_file,
    //                 'message' => isset($message) ? $message . ' عملیات با موفقیت انجام شد' : 'عملیات با موفقیت انجام شد',
    //                 'was_reset' => $is_reset
    //             ];

    //             return $response;
    //         });
    //     } catch (\Exception $e) {
    //         $is_reset = $request->input('is_reset', false);

    //         return [
    //             'error' => $e->getMessage(),
    //             'images' => [],
    //             'current_count' => 0,
    //             'processed_count' => 0,
    //             'remaining_count' => 0,
    //             'is_completed' => false,
    //             'total_count' => 0,
    //             'status' => 'error',
    //             'message' => 'خطا در پردازش',
    //             'was_reset' => $is_reset
    //         ];
    //     }
    // }


    public function makeProductImageMain($productCode, $imageCode)
    {

        ProductImagesModel::where('CodeKala', $productCode)->update(['Def' => 0]);

        $productImage = ProductImagesModel::where('Code', $imageCode)->first();
        if ($productImage) {
            $productImage->Def = 1;
            $productImage->save();
        }
    }
}
