<?php

namespace App\Services;

use App\Helpers\StringHelper;
use App\Models\CategoryModel;
use App\Services\ImageServices\CategoryImageService;
use App\Traits\Cacheable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryService
{
    protected $companyService;
    protected $categoryImageService;
    protected $active_company;
    private $ttl = 60 * 30;

    use Cacheable;

    public function __construct(
        CompanyService $companyService,
        CategoryImageService $categoryImageService
    ) {
        $this->companyService = $companyService;
        $this->categoryImageService = $categoryImageService;
        $this->active_company = $this->companyService->getActiveCompany();
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

            $query = CategoryModel::where('CodeCompany', $this->active_company)
                ->when($search, function ($query, $search) {
                    return $query->where('Name', 'like', "%{$search}%");
                })
                ->orderBy('Code', 'DESC');

            $categories = $query->paginate(8);

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

            $baseQuery = CategoryModel::where('CodeCompany', $this->active_company)->orderBy('Code', 'DESC');

            $categories = $baseQuery->limit(18)->get();

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

                DB::table('KalaGroup')
                    ->where('Code', $category->Code)
                    ->update($updateData);
            }
        }
    }
}
