<?php

namespace App\Http\Controllers\V2\InnerPages;

use App\Http\Controllers\Controller;
use App\Models\CategoryModel;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Intervention\Image\ImageManagerStatic as Image;

class CategoriesController extends Controller
{
    protected $active_company = null;
    protected $financial_period = null;

    protected function CreateCategoryPath()
    {
        $paths = [
            "category-images",
            "category-images/original",
            "category-images/webp"
        ];

        foreach ($paths as $path) {
            $fullPath = public_path($path);
            if (!File::isDirectory($fullPath)) {
                File::makeDirectory($fullPath, 0755, true, true);
            }
        }
    }

    public function __construct(Request $request)
    {
        try {
            $this->CreateCategoryPath();

            $this->active_company = DB::table('Company')
                ->where('DeviceSelected', 1)
                ->pluck('Code')
                ->first();

            if ($this->active_company) {
                $this->financial_period = DB::table('DoreMali')
                    ->where('CodeCompany', $this->active_company)
                    ->where('DeviceSelected', 1)
                    ->pluck('Code')
                    ->first();
            }
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    protected function removeCategoryImage($data)
    {
        if (!empty($data->PicName)) {
            $webpPath = public_path("category-images/webp/" . $data->PicName . ".webp");
            if (File::exists($webpPath)) {
                File::delete($webpPath);
            }
        }
    }

    protected function CreateCategoryImages($data, $picName)
    {
        if (empty($data->Pic)) {
            return;
        }

        $imagePath = public_path("category-images/original/" . $picName . ".jpg");
        $webpPath = public_path("category-images/webp/" . $picName . ".webp");

        File::put($imagePath, $data->Pic);

        Image::configure(['driver' => 'gd']);
        Image::make($imagePath)->encode('webp', 100)->resize(250, 250)->save($webpPath);

        File::delete($imagePath);
    }

    public function listCategories(Request $request)
    {
        try {
            $search = $request->input('search'); 

            $categotyImageCreation = CategoryModel::select('Pic', 'Code', 'CChangePic', 'PicName')
                ->where('CodeCompany', $this->active_company)
                ->when($search, function ($query, $search) {
                    return $query->where('Name', 'like', '%' . $search . '%');
                })
                ->orderBy('Code', 'DESC')
                ->paginate(24);
    
            foreach ($categotyImageCreation as $categoryImage) {
                if ($categoryImage->CChangePic == 1) {
                    if (!empty($categoryImage->PicName)) {
                        $this->removeCategoryImage($categoryImage);
                    }
    
                    if (!empty($categoryImage->Pic)) {
                        $picName = ceil($categoryImage->Code) . "_" . rand(10000, 99999);
                        $this->CreateCategoryImages($categoryImage, $picName);
                        $updateData = ['CChangePic' => 0, 'PicName' => $picName];
                    } else {
                        $updateData = ['CChangePic' => 0, 'PicName' => null];
                    }
    
                    DB::table('KalaGroup')->where('Code', $categoryImage->Code)->update($updateData);
                }
            }
            
            $categoriesList = CategoryModel::select('Code', 'Name', 'Comment', 'PicName')
                ->where('CodeCompany', $this->active_company)
                ->when($search, function ($query, $search) {
                    return $query->where('Name', 'like', '%' . $search . '%');
                })
                ->orderBy('Code', 'DESC')
                ->paginate(24);
    
            return response()->json([
                'result' => [
                    'categories' => $categoriesList
                ],
                'message' => 'دریافت اطلاعات با موفقیت انجام شد'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}