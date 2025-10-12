<?php

namespace App\Services;

use App\Helpers\StringHelper;
use App\Repositories\CategoryRepository;
use App\Services\ImageServices\CategoryImageService;
use App\Traits\Cacheable;
use Illuminate\Support\Str;

class CategoryService
{
    protected $categoryImageService;
    private $ttl = 60 * 30;
    protected $categoryRepository;

    use Cacheable;

    public function __construct(
        CategoryImageService $categoryImageService,
        CategoryRepository $categoryRepository
    ) {
        $this->categoryImageService = $categoryImageService;
        $this->categoryRepository = $categoryRepository;
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

            $this->processCategoryImages($categories);

            $categories->setCollection($categories->getCollection()->map(function ($item) {
                unset($item->Pic);
                return $item;
            }));

            return $categories;
        })->appends($request->query());
    }

    public function listMenuCategories()
    {
        return $this->cacheQuery('menu_categories', $this->ttl, function () {

            $categories = $this->categoryRepository->listMenuCategories();

            $this->processCategoryImages($categories);

            $categories = $categories->map(function ($item) {
                unset($item->Pic);
                return $item;
            });

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
}
