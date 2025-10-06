<?php

namespace App\Services;

use App\Helpers\StringHelper;
use App\Models\BestSellModel;
use App\Models\ProductImagesModel;
use App\Models\ProductModel;
use App\Services\ImageServices\ProductImageService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductService
{

    protected $companyService;
    protected $productImageService;
    protected $active_company;
    protected $financial_period;


    public function __construct(CompanyService $companyService, ProductImageService $productImageService)
    {
        $this->companyService = $companyService;
        $this->productImageService = $productImageService;
        $this->active_company = $this->companyService->getActiveCompany();
        if (!$this->active_company) {
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
            $imageCode = is_array($image) ? ($image['ImageCode'] ?? null) : ($image->ImageCode ?? null);
            $createdAt = is_array($image) ? ($image['created_at'] ?? null) : ($image->created_at ?? null);

            $product = [
                'GCode' => $gCode,
                'SCode' => $sCode,
            ];

            if ($cChangePic == 1 && !empty($pic) && $picName == null) {
                $picName = ceil($imageCode) . '_' . Carbon::parse($createdAt)->timestamp;
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


    public function showSingleProduct($product_code)
    {
        $product = ProductModel::where('Code', $product_code)->firstOrFail();
        $productImages = ProductImagesModel::where('CodeKala', $product->Code)->get();

        if ($product->CChangePic == 1) {
            foreach ($productImages as $image) {
                if (!empty($image->Pic) && $image->PicName == null) {
                    $picName = ceil($image->Code) . '_' . Carbon::parse($image->created_at)->timestamp;
                    if ($this->productImageService->processProductImage($product, $image, $picName)) {
                        DB::table('KalaImage')->where('Code', $image->Code)->update(['PicName' => $picName]);
                    }
                }
            }
            DB::table('Kala')->where('Code', $product->Code)->update(['CChangePic' => 0]);
        }

        $result = ProductModel::with([
            'productSizeColor',
            'productImages' => fn($query) => $query->select('Code', 'PicName', 'Def', 'CodeKala')
        ])
            ->where('Code', $product_code)
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
                'PicName'
            ])->first();

        return $result;
    }

    /**
     * Get related products, excluding the specified product code.
     */
    public function relatedProducts($GCode, $SCode, $excludeCode = null)
    {
        $imageQuery = ProductModel::with(['productSizeColor'])->where('CodeCompany', $this->active_company)->whereHas('productSizeColor', function ($query) {
            $query->havingRaw('SUM(Mande) > 0');
        })->where('GCode', $GCode)
            ->where('SCode', $SCode)
            ->where('CShowInDevice', 1)
            ->when($excludeCode, fn($query) => $query->where('Code', '!=', $excludeCode))
            ->select(['Pic', 'ImageCode', 'created_at', 'GCode', 'SCode', 'Code', 'CChangePic', 'PicName'])
            ->orderBy('Code', 'ASC')
            ->limit(16);

        $this->processProductListImageCreation($imageQuery->get());

        return $this->baseProductQuery()
            ->with(['productSizeColor'])
            ->whereHas('productSizeColor', function ($query) {
                $query->havingRaw('SUM(Mande) > 0');
            })
            ->where('GCode', $GCode)
            ->where('SCode', $SCode)
            ->when($excludeCode, fn($query) => $query->where('Code', '!=', $excludeCode))
            ->orderBy('Code', 'ASC')
            ->limit(16)
            ->get();
    }

    /**
     * Get offered (festival) products.
     */
    public function suggestedProducts($excludeCode = null)
    {

        $imageQuery = ProductModel::with(['productSizeColor'])->where('CodeCompany', $this->active_company)
            ->whereHas('productSizeColor', function ($query) {
                $query->havingRaw('SUM(Mande) > 0');
            })
            ->where('CShowInDevice', 1)
            ->where('CFestival', 1)
            ->when($excludeCode, fn($query) => $query->where('Code', '!=', $excludeCode))
            ->select(['Pic', 'ImageCode', 'created_at', 'GCode', 'SCode', 'Code', 'CChangePic', 'PicName'])
            ->orderBy('Code', 'ASC')
            ->limit(16);

        $this->processProductListImageCreation($imageQuery->get());

        return $this->baseProductQuery()
            ->with(['productSizeColor'])
            ->whereHas('productSizeColor', function ($query) {
                $query->havingRaw('SUM(Mande) > 0');
            })
            ->where('CFestival', 1)
            ->when($excludeCode, fn($query) => $query->where('Code', '!=', $excludeCode))
            ->orderBy('Code', 'ASC')
            ->limit(16)
            ->get();
    }

    /**
     * Get newest products, excluding products with zero Mande.
     * Products are ordered by Code in ascending order and limited to 8 records.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function homePageNewestProducts()
    {

        return Cache::remember('home_page_newest_products', 60 * 30, function () {
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
        return Cache::remember('home_page_offered_products', 60 * 30, function () {
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
        return Cache::remember('home_page_best_selling_products', 60 * 30, function () {

            $baseQuery = BestSellModel::with(['productSizeColor'])
                // ->where('CodeDoreMali', $this->financial_period)
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


    public function listProductColors($categoryCode, $mode)
    {
        return [];
    }


    public function listProductSizes($categoryCode, $mode)
    {
        return [];
    }

    public function listProductPrices($result)
    {
        return [];
    }



    public function listBestSelling($request = null)
    {
        $queryParams = $request ? $request->query() : [];
        $page = $request ? $request->query('product_page', 1) : 1;
        $cacheKey = 'list_best_selling_' . md5(json_encode($queryParams) . '_page_' . $page);

        $results = Cache::remember($cacheKey, 60 * 30, function () use ($request) {
            $baseQuery = BestSellModel::with(['productSizeColor'])
                // ->where('CodeDoreMali', $this->financial_period)
                ->whereHas('productSizeColor', function ($query) {
                    $query->havingRaw('SUM(Mande) > 0');
                });

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
                $baseQuery->orderBy('Foroosh', 'DESC');
            }

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
}
