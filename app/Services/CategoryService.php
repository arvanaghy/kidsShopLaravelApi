<?php

namespace App\Services;

use App\Helpers\StringHelper;
use App\Models\CategoryModel;
use App\Services\ImageServices\CategoryImageService;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    protected $companyService;
    protected $categoryImageService;
    protected $active_company;

    public function __construct(
        CompanyService $companyService,
        CategoryImageService $categoryImageService
    ) {
        $this->companyService = $companyService;
        $this->categoryImageService = $categoryImageService;
        $this->active_company = $this->companyService->getActiveCompany();
    }

    public function listCategories(?string $search = null)
    {
        if ($search) {
            $search = StringHelper::normalizePersianCharacters($search);
        }

        $query = CategoryModel::where('CodeCompany', $this->active_company)
            ->when($search, function ($query, $search) {
                return $query->where('Name', 'like', "%{$search}%");
            })
            ->orderBy('Code', 'DESC');

        $categories = $query->paginate(8);

        $this->processCategoryImages($categories);

        $result = $query->select('Code', 'Name', 'Comment', 'PicName')->paginate(8);
        $result->appends(['search' => $search]);

        return $result;
    }


    public function listMenuCategories()
    {
        $baseQuery = CategoryModel::where('CodeCompany', $this->active_company)->orderBy('Code', 'DESC');

        $categories = $baseQuery->limit(18)->get();

        $this->processCategoryImages($categories);

        $categories = $categories->map(function ($item) {
            unset($item->Pic);
            return $item;
        });

        return $categories;
    }


    protected function processCategoryImages($categories)
    {
        foreach ($categories as $category) {
            if ($category->CChangePic == 1) {
                if (!empty($category->PicName)) {
                    $this->categoryImageService->removeCategoryImage($category);
                }

                if (!empty($category->Pic)) {
                    $picName = ceil($category->Code) . "_" . rand(10000, 99999);
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
