<?php

namespace App\Services;

use App\Helpers\StringHelper;
use App\Models\ProductModel;
use App\Repositories\CategoryRepository;
use App\Services\ImageServices\CategoryImageService;
use App\Traits\Cacheable;
use Illuminate\Support\Str;

class CategoryService
{
    protected $categoryImageService;
    private $ttl = 60 * 30;
    protected $categoryRepository;
    protected $active_company;

    use Cacheable;

    public function __construct(
        CategoryImageService $categoryImageService,
        CategoryRepository $categoryRepository,
        CompanyService $companyService
    ) {
        $this->categoryImageService = $categoryImageService;
        $this->categoryRepository = $categoryRepository;
        $this->active_company = $companyService->getActiveCompany();
    }

    public function listCategories($request = null)
    {

        $queryParams = $request ? $request->query() : [];
        $page = $request ? $request->query('page', 1) : 1;

        $cacheKey = 'list_categories_' . md5(json_encode($queryParams) . '_page_' . $page);

        return $this->cacheQuery($cacheKey, $this->ttl, function () use ($request) {
            if ($search = $request?->query('search')) {
                $search = StringHelper::normalizePersianCharacters($search);
            }

            $categories = $this->categoryRepository->listCategories($search);

            $this->setRandomProductImages($categories);

            // $categories->setCollection($categories->getCollection()->map(function ($item) {
            //     unset($item->Pic);
            //     return $item;
            // }));

            return $categories;
        })->appends($request->query());
    }

    public function listMenuCategories()
    {
        return $this->cacheQuery('menu_categories', $this->ttl, function () {

            $categories = $this->categoryRepository->listMenuCategories();

            $this->setRandomProductImages($categories);

            // $categories = $categories->map(function ($item) {
            //     unset($item->Pic);
            //     return $item;
            // });

            return $categories;
        });
    }

    protected function processCategoryImages($categories)
    {
        foreach ($categories as $category) {
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

                $this->categoryRepository->updateCategoryImage($category, $updateData);
            }
        }
    }

    protected function getProductsByCategory($gCode)
    {
        $cacheKey = "products_by_category_{$gCode}";
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

    protected function setRandomProductImages($categories)
    {
        foreach ($categories as $category) {
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
                    'PicName' => $randomProduct->GCode . "/" . $randomProduct->SCode . "/" . $randomProduct->PicName
                ];
            } else {
                $updateData = ['CChangePic' => 0, 'PicName' => null];
            }

            $this->categoryRepository->updateCategoryImage($category, $updateData);
        }
    }
}
