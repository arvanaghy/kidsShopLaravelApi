<?php

namespace App\Http\Controllers\V1;

use App\Models\CategoryModel;
use App\Models\SubCategoryModel;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManagerStatic as Image;

class CategoryController extends Controller
{
    protected $active_company = null;
    protected $financial_period = null;

    public function __construct()
    {
        $active_company = DB::table('Company')->where('DeviceSelected', 1)->first();
        if ($active_company) {
            $this->active_company = $active_company->Code;
            $active_financial_period = DB::table('DoreMali')->where('CodeCompany', $active_company->Code)->where('DeviceSelected', 1)->first();
            if ($active_financial_period) {
                $this->financial_period = $active_financial_period->Code;
            }
        }
    }

    protected function CreateCategoryPath()
    {
        if (!File::isDirectory(public_path("category-images"))) {
            File::makeDirectory("category-images", 0755, true, true);
        }

        if (!File::isDirectory(public_path("category-images/original"))) {
            File::makeDirectory("category-images/original", 0755, true, true);
        }

        if (!File::isDirectory(public_path("category-images/webp"))) {
            File::makeDirectory("category-images/webp", 0755, true, true);
        }
    }

    protected function CreateCategoryImages($data, $picName)
    {

        $imagePath = "category-images/original/" . $picName . ".jpg";
        $webpPath =  "category-images/webp/" . $picName . ".webp";

        File::put(public_path($imagePath), $data->Pic);
        Image::configure(['driver' => 'gd']);
        Image::make($imagePath)
            ->resize(1200, 1600, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            })
            ->encode('webp', 100)
            ->save($webpPath);
        File::delete($imagePath);
    }

    protected function removeCategoryImage($data)
    {
        $webpPath =  "category-images/webp/" . $data->PicName . ".webp";
        File::delete($webpPath);
    }

    protected function CreateSubCategoryPath()
    {
        if (!File::isDirectory(public_path("subCategory-images"))) {
            File::makeDirectory("subCategory-images", 0755, true, true);
        }

        if (!File::isDirectory(public_path("subCategory-images/original"))) {
            File::makeDirectory("subCategory-images/original", 0755, true, true);
        }

        if (!File::isDirectory(public_path("subCategory-images/webp"))) {
            File::makeDirectory("subCategory-images/webp", 0755, true, true);
        }
    }

    protected function CreateSubCategoryImages($data, $picName)
    {

        $imagePath = "subCategory-images/original/" . $picName . ".jpg";
        $webpPath =  "subCategory-images/webp/" . $picName . ".webp";

        File::put(public_path($imagePath), $data->Pic);
        Image::configure(['driver' => 'gd']);
        Image::make($imagePath)
            ->resize(1200, 1600, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            })
            ->encode('webp', 100)
            ->save($webpPath);
        File::delete($imagePath);
    }

    protected function removeSubCategoryImage($data)
    {
        $webpPath =  "subCategory-images/webp/" . $data->PicName . ".webp";
        File::delete($webpPath);
    }

    public function list_categories()
    {

        try {
            $imageCreation = CategoryModel::select('Pic', 'Code', 'CChangePic', 'PicName')->where('CodeCompany', $this->active_company)->orderBy('Code', 'DESC')->paginate(12);
            $this->CreateCategoryPath();
            foreach ($imageCreation as $image) {
                if ($image->CChangePic == 1) {
                    $this->removeCategoryImage($image);
                    Log::info('Image remove image for list_categories:', [
                        'Code' => $image->Code,
                        'PicName' => $image->PicName,
                    ]);
                    DB::table('KalaGroup')->Where('Code', $image->Code)->update(['CChangePic' => 0, 'PicName' => null]);
                    if ($image->Pic != null) {
                        $picName = ceil($image->Code) . "_" . rand(10000, 99999);
                        $this->CreateCategoryImages($image,  $picName);
                        Log::info('Image creation image for list_categories:', [
                            'Code' => $image->Code,
                            'PicName' => $picName,
                        ]);
                        DB::table('KalaGroup')->Where('Code', $image->Code)->update(['PicName' => $picName]);
                    }
                }
            }

            return response()->json([
                "message" => "عملیات با موفقیت انجام شد",
                "result" => CategoryModel::select('Code', 'Name', 'Comment', 'PicName')->where('CodeCompany', $this->active_company)->orderBy('Code', 'DESC')->paginate(12),
            ], 200);
        } catch (Exception $e) {

            return response()->json([
                'message' => $e->getMessage(),
                'result' => null
            ], 503);
        }
    }

    public function search_categories($search)
    {

        try {
            $searchPhrases = explode(' ', $search);
            $categories1 = CategoryModel::query();
            foreach ($searchPhrases as $phrase) {
                $categories1->where('Name', 'like', "%$phrase%");
            }
            $imageCreation = $categories1->select('Pic', 'Code', 'CChangePic', 'PicName')->where('CodeCompany', $this->active_company)->orderBy('Code', 'DESC')->paginate(12);

            $this->CreateCategoryPath();
            foreach ($imageCreation as $image) {
                if ($image->CChangePic == 1) {
                    $this->removeCategoryImage($image);
                    Log::info('Image remove image for list_categories:', [
                        'Code' => $image->Code,
                        'PicName' => $image->PicName,
                    ]);
                    DB::table('KalaGroup')->Where('Code', $image->Code)->update(['CChangePic' => 0, 'PicName' => null]);
                    if ($image->Pic != null) {
                        $picName = ceil($image->Code) . "_" . rand(10000, 99999);
                        $this->CreateCategoryImages($image,  $picName);
                        Log::info('Image creation image for list_categories:', [
                            'Code' => $image->Code,
                            'PicName' => $picName,
                        ]);
                        DB::table('KalaGroup')->Where('Code', $image->Code)->update(['PicName' => $picName]);
                    }
                }
            }

            $categories = CategoryModel::query();
            foreach ($searchPhrases as $phrase) {
                $categories->where('Name', 'like', "%$phrase%");
            }

            return response()->json([
                "message" => "عملیات با موفقیت انجام شد",
                "result" => $categories->select('Code', 'Name', 'Comment', 'PicName')->where('CodeCompany', $this->active_company)->orderBy('Code', 'DESC')->paginate(12),
            ], 200);
        } catch (Exception $e) {

            return response()->json([
                'message' => $e->getMessage(),
                'result' => null
            ], 503);
        }
    }

    public function search_category_by_code($Code)
    {
        try {

            return response()->json([
                "message" => "عملیات با موفقیت انجام شد",
                'result' => CategoryModel::select('Code', 'Name', 'Comment')->where('Code', $Code)->where('CodeCompany', $this->active_company)->orderBy('Code', 'DESC')->first()
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'result' => null
            ], 503);
        }
    }

    public function search_subcategory_by_code($Code)
    {
        try {
            return response()->json([
                "message" => "عملیات با موفقیت انجام شد",
                "result" => SubCategoryModel::select('Code', 'Name', 'CodeGroup')->where('CodeCompany', $this->active_company)->where('Code', $Code)->orderBy('Code', 'DESC')->first(),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'result' => null
            ], 503);
        }
    }

    public function list_subcategories($Code)
    {
        try {
            $imageCreation = SubCategoryModel::select('Pic', 'Code', 'CChangePic', 'PicName')->where('CodeCompany', $this->active_company)->where('CodeGroup', $Code)->orderBy('Code', 'DESC')->paginate(12);

            $this->CreateSubCategoryPath();
            foreach ($imageCreation as $image) {

                if ($image->CChangePic == 1) {
                    $this->removeSubCategoryImage($image);
                    Log::info('Image remove image for list_subcategories:', [
                        'Code' => $image->Code,
                        'PicName' => $image->PicName,
                    ]);
                    DB::table('KalaSubGroup')->Where('Code', $image->Code)->update(['CChangePic' => 0, 'PicName' => null]);
                    if ($image->Pic != null) {
                        $picName = ceil($image->Code) . "_" . rand(10000, 99999);
                        $this->CreateSubCategoryImages($image,  $picName);
                        Log::info('Image creation image for list_subcategories:', [
                            'Code' => $image->Code,
                            'PicName' => $picName,
                        ]);
                        DB::table('KalaSubGroup')->Where('Code', $image->Code)->update(['PicName' => $picName]);
                    }
                }
            }

            return response()->json([
                "message" => "عملیات با موفقیت انجام شد",
                "result" => SubCategoryModel::select('Code', 'Name', 'CodeGroup', 'PicName')->where('CodeCompany', $this->active_company)->where('CodeGroup', $Code)->orderBy('Code', 'DESC')->paginate(12),
            ], 200);
        } catch (Exception $e) {

            return response()->json([
                'message' => $e->getMessage(),
                'result' => null
            ], 503);
        }
    }

    public function search_subcategories($search)
    {

        try {
            $searchPhrases = explode(' ', $search);

            $categories1 = SubCategoryModel::query();
            foreach ($searchPhrases as $phrase) {
                $categories1->where('Name', 'like', "%$phrase%");
            }
            $imageCreation = $categories1->select('Pic', 'Code', 'CChangePic', 'PicName')->where('CodeCompany', $this->active_company)->orderBy('Code', 'DESC')->paginate(12);


            $this->CreateSubCategoryPath();
            foreach ($imageCreation as $image) {

                if ($image->CChangePic == 1) {
                    $this->removeSubCategoryImage($image);
                    Log::info('Image remove image for list_subcategories:', [
                        'Code' => $image->Code,
                        'PicName' => $image->PicName,
                    ]);
                    DB::table('KalaSubGroup')->Where('Code', $image->Code)->update(['CChangePic' => 0, 'PicName' => null]);
                    if ($image->Pic != null) {
                        $picName = ceil($image->Code) . "_" . rand(10000, 99999);
                        $this->CreateSubCategoryImages($image,  $picName);
                        Log::info('Image creation image for list_subcategories:', [
                            'Code' => $image->Code,
                            'PicName' => $picName,
                        ]);
                        DB::table('KalaSubGroup')->Where('Code', $image->Code)->update(['PicName' => $picName]);
                    }
                }
            }


            $categories = SubCategoryModel::query();
            foreach ($searchPhrases as $phrase) {
                $categories->where('Name', 'like', "%$phrase%");
            }

            return response()->json([
                "message" => "عملیات با موفقیت انجام شد",
                "result" => $categories->select('Code', 'Name', 'CodeGroup', 'PicName')->where('CodeCompany', $this->active_company)->orderBy('Code', 'DESC')->paginate(12),
            ], 200);
        } catch (Exception $e) {

            return response()->json([
                'message' => $e->getMessage(),
                'result' => null
            ], 503);
        }
    }
}
