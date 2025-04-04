<?php

namespace App\Http\Controllers\V2\InnerPages;

use App\Http\Controllers\Controller;
use App\Models\CategoryModel;
use Illuminate\Http\Request;
use App\Models\ProductModel;
use App\Models\SubCategoryModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Intervention\Image\ImageManagerStatic as Image;
use Exception;
use Illuminate\Http\Response;


class SubCategories extends Controller
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

    protected function CreateSubCategoryPath()
    {
        $paths = [
            "subCategory-images",
            "subCategory-images/original",
            "subCategory-images/webp"
        ];

        foreach ($paths as $path) {
            $fullPath = public_path($path);
            if (!File::isDirectory($fullPath)) {
                File::makeDirectory($fullPath, 0755, true, true);
            }
        }
    }

    public function __construct()
    {
        try {
            $this->CreateCategoryPath();
            $this->CreateSubCategoryPath();
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

    protected function CreateSubCategoryImages($data, $picName)
    {

        if (empty($data->Pic)) {
            return;
        }

        $imagePath = public_path("subCategory-images/original/" . $picName . ".jpg");
        $webpPath = public_path("subCategory-images/webp/" . $picName . ".webp");

        File::put($imagePath, $data->Pic);

        Image::configure(['driver' => 'gd']);
        Image::make($imagePath)->encode('webp', 80)->resize(250, 250)->save($webpPath);

        File::delete($imagePath);
    }

    protected function removeSubCategoryImage($data)
    {
        if (!empty($data->PicName)) {
            $webpPath = public_path("subCategory-images/webp/" . $data->PicName . ".webp");
            if (File::exists($webpPath)) {
                File::delete($webpPath);
            }
        }
    }

    protected function CreateProductImagesPath($data)
    {
        $basePath = public_path("products-image");
        $subPaths = ["original", "webp"];

        array_map(function ($type) use ($basePath, $data) {
            $dir = "$basePath/$type/" . ceil($data->GCode) . "/" . ceil($data->SCode);
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true, true);
            }
        }, $subPaths);
    }

    protected function CreateProductImages($data, $picName)
    {
        $dir = "products-image/original/" . ceil($data->GCode) . "/" . ceil($data->SCode);
        $webpDir = "products-image/webp/" . ceil($data->GCode) . "/" . ceil($data->SCode);

        $imagePath = "$dir/$picName.jpg";
        $webpPath = "$webpDir/$picName.webp";

        File::put(public_path($imagePath), $data->Pic);
        Image::configure(['driver' => 'gd']);

        $image = Image::make(public_path($imagePath));
        $image->encode('webp', 100)->resize(250, 250)->save(public_path($webpPath), 100);

        File::delete(public_path($imagePath));
    }

    protected function removeProductImages($data)
    {
        try {
            $dir = "products-image/original/" . ceil($data->GCode) . "/" . ceil($data->SCode);
            $webpDir = "products-image/webp/" . ceil($data->GCode) . "/" . ceil($data->SCode);
            if (File::exists($dir)) {
                File::deleteDirectory($dir);
            }
            if (File::exists($webpDir)) {
                File::deleteDirectory($webpDir);
            }
        } catch (\Exception $e) {
            return;
        }
    }


    protected function list_subcategories($Code)
    {
        try {
            $imageCreation = SubCategoryModel::select('Pic', 'Code', 'CChangePic', 'PicName')
                ->where('CodeCompany', $this->active_company)
                ->where('CodeGroup', $Code)
                ->orderBy('Code', 'DESC')
                ->paginate(12, ['*'], 'subcategory_page');

            foreach ($imageCreation as $image) {
                if ($image->CChangePic == 1) {
                    if (!empty($image->PicName)) {
                        $this->removeSubCategoryImage($image);
                    }

                    if (!empty($image->Pic)) {
                        $picName = ceil($image->Code) . "_" . rand(10000, 99999);
                        $this->CreateSubCategoryImages($image, $picName);
                        $updateData = ['CChangePic' => 0, 'PicName' => $picName];
                    } else {
                        $updateData = ['CChangePic' => 0, 'PicName' => null];
                    }

                    DB::table('KalaSubGroup')->where('Code', $image->Code)->update($updateData);
                }
            }

            return SubCategoryModel::select('Code', 'Name', 'CodeGroup', 'PicName')
                ->where('CodeCompany', $this->active_company)
                ->where('CodeGroup', $Code)
                ->orderBy('Code', 'DESC')
                ->paginate(12, ['*'], 'subcategory_page');
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'result' => null
            ], 503);
        }
    }


    protected function fetchCategoryName($Code)
    {
        try {
            return CategoryModel::select('Code', 'Name', 'Comment', 'PicName')->where('CodeCompany', $this->active_company)->where('Code', $Code)->first();
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'result' => null
            ], 503);
        }
    }


    public function list_category_products($categoryCode)
    {
        try {
            $request = request();

            // Base query for products
            $query = ProductModel::with(['productSizeColor'])
                ->where('CodeCompany', $this->active_company)
                ->where('GCode', $categoryCode)
                ->where('CShowInDevice', 1)
                ->join('AV_KalaSizeColorMande_View', 'AV_KalaList_View.Code', '=', 'AV_KalaSizeColorMande_View.CodeKala');

            // Apply sorting
            $sortPrice = $request->query('sort_price', 'asc');
            $query->orderBy('AV_KalaSizeColorMande_View.Mablag', in_array($sortPrice, ['asc', 'desc']) ? $sortPrice : 'asc');

            // Apply filters
            if ($search = $request->query('search')) {
                $query->where('Name', 'LIKE', "%{$search}%");
            }
            if ($size = $request->query('size')) {
                $sizes = explode(',', $size);
                $query->whereIn('AV_KalaSizeColorMande_View.SizeNum', $sizes);
            }
            if ($color = $request->query('color')) {
                $colors = explode(',', $color);
                $query->whereIn('AV_KalaSizeColorMande_View.ColorCode', $colors);
            }

            // Clone the query for image creation to avoid affecting the main query
            $imageQuery = clone $query;
            $imageCreation = $imageQuery->select('Pic', 'ImageCode', 'created_at', 'GCode', 'SCode', 'Code', 'PicName')
                ->paginate(24, ['*'], 'product_page');

            // Process images
            foreach ($imageCreation as $image) {
                if ($image->CChangePic == 1) {
                    $this->removeProductImages($image);
                }
                if (!empty($image->Pic)) {
                    $this->CreateProductPath($image);
                    $createdAt = Carbon::parse($image->created_at);
                    $picName = ceil($image->ImageCode) . "_" . $createdAt->getTimestamp();
                    $this->CreateProductImages($image, $picName);
                    DB::table('KalaImage')->where('Code', $image->ImageCode)->update(['PicName' => $picName]);
                }
                DB::table('Kala')->where('Code', $image->Code)->update(['CChangePic' => 0]);
            }

            // Get the final product result with a fresh paginator
            $productResult = $query->select('*')
                ->paginate(24, ['*'], 'product_page');

            // Append query parameters to pagination links
            $productResult->appends($request->query());

            // Return the paginator directly
            return $productResult;
        } catch (Exception $e) {
            return response()->json([
                'message' => 'خطا: ' . $e->getMessage(),
                'result' => null
            ], 503);
        }
    }

    public function index(Request $request, $Code)
    {
        try {
            $subcategoryPage = $request->query('subcategory_page', 1);
            $productPage = $request->query('product_page', 1);

            // Fetch subcategories
            $subcategories = $this->list_subcategories($Code);
            if ($subcategories instanceof \Illuminate\Http\JsonResponse) {
                return $subcategories; // Return error response immediately
            }
            $subcategories->appends(['subcategory_page' => $subcategoryPage, 'product_page' => $productPage]);

            // Fetch category products
            $categoryProducts = $this->list_category_products($Code);
            if ($categoryProducts instanceof \Illuminate\Http\JsonResponse) {
                return $categoryProducts; // Return error response immediately
            }
            $categoryProducts->appends(['subcategory_page' => $subcategoryPage, 'product_page' => $productPage]);

            // Fetch category name
            $category = $this->fetchCategoryName($Code);
            if ($category instanceof \Illuminate\Http\JsonResponse) {
                return $category; // Return error response immediately
            }

            return response()->json([
                'result' => [
                    'subcategories' => $subcategories,
                    'category' => $category,
                    'categoryProducts' => $categoryProducts,
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

    public function testIt()
    {
        try {
            return DB::table('AV_KalaSizeColorMande_View')->get();
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
