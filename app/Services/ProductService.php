<?php

namespace App\Services;

use App\Helpers\StringHelper;
use App\Models\BestSellModel;
use App\Models\ProductModel;
use App\Repositories\ImageUpdater;
use App\Services\ImageServices\ProductImageService;
use App\Traits\Cacheable;
use Illuminate\Support\Str;

class ProductService
{

    protected $companyService;
    protected $productImageService;
    protected $active_company;
    protected $financial_period;
    protected $imageUpdater;

    private $ttl = 60 * 30;

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

        $this->imageUpdater->productImagesUpdate($updates);
    }


    public function showSingleProduct($product_code)
    {
        $product = ProductModel::with([
            'productSizeColor',
            'productImages' => fn($query) => $query->select('Code', 'PicName', 'Def', 'CodeKala', 'Pic')
        ])
            ->where('Code', $product_code)
            ->where('CodeCompany', $this->active_company)
            ->whereHas('productSizeColor', function ($query) {
                $query->havingRaw('SUM(Mande) > 0');
            })
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
                'Vahed',
                'SPrice',
                'PicName',
                'created_at'
            ])
            ->first();

            if (!$product) {
                return null;
            }

        if ($product->CChangePic == 1) {
            $updates = [];
            foreach ($product->productImages as $image) {
                if ($image->Pic && $image->PicName == null) {
                    $picName = Str::random(16);
                    if ($this->productImageService->processProductImage($product, $image, $picName)) {
                        $updates[] = ['Code' => $image->Code, 'PicName' => $picName];
                    }
                }
            }

            if (!empty($updates)) {
                $this->imageUpdater->productImagesUpdate($updates);
                $product->update(['CChangePic' => 0]);
            }
        }

        if ($product->productImages) {
            $product->productImages->each(function ($image) {
                unset($image->Pic);
            });
        }

        return $product;
    }

    /**
     * Get related products, excluding the specified product code.
     */
    public function relatedProducts($GCode, $SCode, $excludeCode = null)
    {
        $cacheKey = "related_products_{$GCode}_{$SCode}_" . ($excludeCode ?? 'no_exclude');
        return $this->cacheQuery($cacheKey, $this->ttl, function () use ($GCode, $SCode, $excludeCode) {
            $baseQuery = $this->baseProductQuery()
                ->where('GCode', $GCode)
                ->where('SCode', $SCode)
                ->where('CShowInDevice', 1)
                ->when($excludeCode, fn($query) => $query->where('Code', '!=', $excludeCode))
                ->orderBy('Code', 'ASC')
                ->limit(16);

            $results = $baseQuery->get();

            $this->processProductListImageCreation($results);

            $results = $results->map(function ($item) {
                unset($item->Pic);
                return $item;
            });

            return $results;
        });
    }

    /**
     * Get offered (festival) products.
     */
    public function suggestedProducts($excludeCode = null)
    {

        $cacheKey = "suggested_products_" . ($excludeCode ?? 'no_exclude');
        return $this->cacheQuery($cacheKey, $this->ttl, function () use ($excludeCode) {

            $baseQuery = $this->baseProductQuery()
                ->where('CShowInDevice', 1)
                ->where('CFestival', 1)
                ->when($excludeCode, fn($query) => $query->where('Code', '!=', $excludeCode))
                ->orderBy('Code', 'ASC')
                ->limit(16);

            $results = $baseQuery->get();

            $this->processProductListImageCreation($results);

            $results = $results->map(function ($item) {
                unset($item->Pic);
                return $item;
            });

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

        return $this->cacheQuery('home_page_newest_products', $this->ttl, function () {
            $baseQuery = $this->baseProductQuery()
                ->orderBy('Code', 'DESC')
                ->limit(8);

            $results = $baseQuery->get();

            $this->processProductListImageCreation($results);
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
        return $this->cacheQuery('home_page_offered_products', $this->ttl, function () {
            $baseQuery = $this->baseProductQuery()
                ->where('CFestival', 1)
                ->orderBy('Code', 'ASC')
                ->limit(8);

            $results = $baseQuery->get();

            $this->processProductListImageCreation($results);
            $results = $results->map(function ($item) {
                unset($item->Pic);
                return $item;
            });

            return $results;
        });
    }

    public function homePageBestSellingProducts()
    {
        return $this->cacheQuery('home_page_best_selling_products', $this->ttl, function () {

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
                    'Pic',
                    'ImageCode',
                    'created_at'
                ])
                ->orderBy('Foroosh', 'DESC')
                ->limit(8);

            $results = $baseQuery->get();

            $this->processProductListImageCreation($results);

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
        $cacheKeyPrefix = 'product_colors_' . $this->active_company;

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
            $cacheKey = $cacheKeyPrefix . '_sdfsdall_' . $type;
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
        $cacheKeyPrefix = 'product_sizes_' . $this->active_company;

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
            $cacheKey = $cacheKeyPrefix . '_fsdfall_' . $type;
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
        $cacheKey = 'list_best_selling_' . md5(json_encode($queryParams) . '_page_' . $page);

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
                'Pic',
                'ImageCode',
                'created_at'
            ]);

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

    public function listAllProducts($request = null)
    {
        $queryParams = $request ? $request->query() : [];
        $page = $request ? $request->query('product_page', 1) : 1;
        $cacheKey = 'list_all_products_' . md5(json_encode($queryParams) . '_page_' . $page);

        $results = $this->cacheQuery($cacheKey, $this->ttl, function () use ($request) {
            $baseQuery = $this->baseProductQuery();

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

    public function listOfferedProducts($request = null)
    {
        $queryParams = $request ? $request->query() : [];
        $page = $request ? $request->query('product_page', 1) : 1;
        $cacheKey = 'list_offered_products_' . md5(json_encode($queryParams) . '_page_' . $page);

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
}
