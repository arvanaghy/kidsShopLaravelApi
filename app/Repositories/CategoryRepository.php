<?php

namespace App\Repositories;

use App\Models\CategoryModel;
use App\Services\CompanyService;
use Illuminate\Support\Facades\DB;

class CategoryRepository
{
    protected $active_company;

    public function __construct(
        CompanyService $companyService
    ) {
        $this->active_company = $companyService->getActiveCompany();
    }

    public function listMenuCategories()
    {
        return CategoryModel::where('CodeCompany', $this->active_company)->orderBy('Code', 'DESC')->limit(18)->get();
    }

    public function updateCategoryImage($category, $data)
    {
        DB::table('KalaGroup')
            ->where('Code', $category->Code)
            ->update($data);
    }

    public function listCategories($search = null)
    {
        return CategoryModel::where('CodeCompany', $this->active_company)
            ->when($search, function ($query, $search) {
                return $query->where('Name', 'like', "%{$search}%");
            })
            ->orderBy('Code', 'DESC')->paginate(8);
    }
}
