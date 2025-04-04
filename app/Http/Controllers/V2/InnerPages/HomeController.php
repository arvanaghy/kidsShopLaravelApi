<?php

namespace App\Http\Controllers\V2\InnerPages;

use App\Http\Controllers\Controller;
use App\Models\CategoryModel;
use Illuminate\Http\Request;
use App\Models\ProductModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Intervention\Image\ImageManagerStatic as Image;
use Exception;
use Carbon\Carbon;


class HomeController extends Controller
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

    protected function CreateBannerPath()
    {
        $paths = [
            "banner-images",
            "banner-images/original",
            "banner-images/webp"
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
            $this->CreateBannerPath();

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

    protected function removeBannerImage($data)
    {
        if (!empty($data->PicName)) {
            $webpPathDesktop = public_path("banner-images/webp/" . $data->PicName . "_desktop.webp");
            $webpPathMobile = public_path("banner-images/webp/" . $data->PicName . "_mobile.webp");
            if (File::exists($webpPathDesktop)) {
                File::delete($webpPathDesktop);
            }
            if (File::exists($webpPathMobile)) {
                File::delete($webpPathMobile);
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

    protected function CreateBannerImages($data, $picName)
    {
        $sizes = [
            'desktop' => [1360, 786],
            'mobile' => [390, 844]
        ];

        foreach ($sizes as $type => $size) {
            $imagePath = public_path("banner-images/original/{$picName}_{$type}.jpg");
            $webpPath = public_path("banner-images/webp/{$picName}_{$type}.webp");

            File::put($imagePath, $data->Pic);
            Image::configure(['driver' => 'gd']);
            Image::make($imagePath)
                ->encode('webp', 100)
                ->resize($size[0], $size[1])
                ->save($webpPath, 100);

            File::delete($imagePath);
        }
    }

    protected function list_categories()
    {
        try {
            $imageCreation = CategoryModel::select('Pic', 'Code', 'CChangePic', 'PicName')
                ->where('CodeCompany', $this->active_company)
                ->orderBy('Code', 'DESC')
                ->limit(16)
                ->get();

            foreach ($imageCreation as $image) {
                if ($image->CChangePic == 1) {
                    if (!empty($image->PicName)) {
                        $this->removeCategoryImage($image);
                    }

                    if (!empty($image->Pic)) {
                        $picName = ceil($image->Code) . "_" . rand(10000, 99999);
                        $this->CreateCategoryImages($image, $picName);
                        $updateData = ['CChangePic' => 0, 'PicName' => $picName];
                    } else {
                        $updateData = ['CChangePic' => 0, 'PicName' => null];
                    }

                    DB::table('KalaGroup')->where('Code', $image->Code)->update($updateData);
                }
            }

            return CategoryModel::select('Code', 'Name', 'Comment', 'PicName')
                ->where('CodeCompany', $this->active_company)
                ->orderBy('Code', 'DESC')
                ->limit(16)
                ->get();
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'result' => null
            ], 503);
        }
    }

    protected function fetchBanners()
    {
        try {
            $imageResult = DB::table('DeviceHeaderImage')
                ->select('Pic', 'Code', 'CChangePic', 'PicName')
                ->where('CodeCompany', $this->active_company)
                ->limit(6)
                ->get();

            foreach ($imageResult as $image) {
                if ($image->CChangePic == 1) {
                    if (!empty($image->PicName)) {
                        $this->removeBannerImage($image);
                    }

                    if (!empty($image->Pic)) {
                        $picName = ceil($image->Code) . "_" . rand(10000, 99999);
                        $this->CreateBannerImages($image, $picName);
                        $updateData = ['CChangePic' => 0, 'PicName' => $picName];
                    } else {
                        $updateData = ['CChangePic' => 0, 'PicName' => null];
                    }

                    DB::table('DeviceHeaderImage')->where('Code', $image->Code)->update($updateData);
                }
            }

            return DB::table('DeviceHeaderImage')
                ->select('Comment', 'PicName', 'Code')
                ->where('CodeCompany', $this->active_company)
                ->whereNotNull('Pic')
                ->orderBy('Code', 'DESC')
                ->limit(6)
                ->get();
        } catch (Exception $e) {
            return response()->json([
                'result' => null,
                'message' => $e->getMessage(),
            ], 503);
        }
    }

    protected function CreateProductPath($data)
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

    protected function fetchNewestProducts()
    {
        try {
            $imageResults = ProductModel::where('CodeCompany', $this->active_company)
                ->where('CShowInDevice', 1)
                ->select('Pic', 'ImageCode', 'created_at', 'GCode', 'SCode', 'Code', 'PicName')
                ->orderBy('UCode', 'ASC')
                ->limit(16)
                ->get();

            foreach ($imageResults as $image) {
                if ($image->CChangePic == 1) {
                    $this->removeProductImages($image);
                }
                if (!empty($image->Pic)) {
                    $this->CreateProductPath($image);
                    $picName = ceil($image->ImageCode) . "_" . $image->created_at->getTimestamp();
                    $this->CreateProductImages($image, $picName);
                    DB::table('KalaImage')->where('Code', $image->ImageCode)->update(['PicName' => $picName]);
                }

                DB::table('Kala')->where('Code', $image->Code)->update(['CChangePic' => 0]);
            }

            return ProductModel::where('CodeCompany', $this->active_company)
                ->where('CShowInDevice', 1)
                ->select(
                    'CodeCompany',
                    'CanSelect',
                    'GCode',
                    'GName',
                    'Comment',
                    'SCode',
                    'SName',
                    'Code',
                    'CodeKala',
                    'Name',
                    'Model',
                    'UCode',
                    'Vahed',
                    'KMegdar',
                    'KPrice',
                    'SPrice',
                    'KhordePrice',
                    'OmdePrice',
                    'HamkarPrice',
                    'AgsatPrice',
                    'CheckPrice',
                    'DForoosh',
                    'CShowInDevice',
                    'CFestival',
                    'GPoint',
                    'KVahed',
                    'PicName'
                )
                ->orderBy('UCode', 'ASC')
                ->limit(16)
                ->get();
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error: ' . $e->getMessage(),
                'result'  => null,
            ], 503);
        }
    }


    protected function offerd_products()
    {

        try {
            $imageResults = ProductModel::where('CodeCompany', $this->active_company)
                ->where('CShowInDevice', 1)
                ->where('CFestival', 1)
                ->select('Pic', 'ImageCode', 'created_at', 'GCode', 'SCode', 'Code', 'PicName')
                ->orderBy('UCode', 'ASC')
                ->limit(16)
                ->get();

            foreach ($imageResults as $image) {
                if ($image->CChangePic == 1) {
                    $this->removeProductImages($image);
                }
                if (!empty($image->Pic)) {
                    $this->CreateProductPath($image);
                    $picName = ceil($image->ImageCode) . "_" . $image->created_at->getTimestamp();
                    $this->CreateProductImages($image, $picName);
                    DB::table('KalaImage')->where('Code', $image->ImageCode)->update(['PicName' => $picName]);
                }

                DB::table('Kala')->where('Code', $image->Code)->update(['CChangePic' => 0]);
            }

            return ProductModel::where('CodeCompany', $this->active_company)
                ->where('CShowInDevice', 1)
                ->select(
                    'CodeCompany',
                    'CanSelect',
                    'GCode',
                    'GName',
                    'Comment',
                    'SCode',
                    'SName',
                    'Code',
                    'CodeKala',
                    'Name',
                    'Model',
                    'UCode',
                    'Vahed',
                    'KMegdar',
                    'KPrice',
                    'SPrice',
                    'KhordePrice',
                    'OmdePrice',
                    'HamkarPrice',
                    'AgsatPrice',
                    'CheckPrice',
                    'DForoosh',
                    'CShowInDevice',
                    'CFestival',
                    'GPoint',
                    'KVahed',
                    'PicName'
                )
                ->where('CFestival', 1)
                ->orderBy('UCode', 'ASC')
                ->limit(16)
                ->get();
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error: ' . $e->getMessage(),
                'result'  => null,
            ], 503);
        }
    }

    protected function bestSeller()
    {
        try {
            $imageResults = DB::table('AV_KalaTedadForooshKol_View')->select('Pic', 'KCode as Code', 'ImageCode', 'created_at', 'CChangePic', 'GCode', 'SGCode as SCode', 'PicName')
                ->where('CShowInDevice', 1)
                ->where('CodeCompany', $this->active_company)
                ->limit(16)
                ->get();

            foreach ($imageResults as $image) {
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

            return  DB::table('AV_KalaTedadForooshKol_View')->select(
                'GCode',
                'GName',
                'SGCode as SCode',
                'SGName as SName',
                'KCode as Code',
                'KName as Name',
                'Vahed',
                'Comment',
                'KMegdar',
                'SPrice',
                'KhordePrice',
                'OmdePrice',
                'HamkarPrice',
                'AgsatPrice',
                'CheckPrice',
                'DForoosh',
                'CShowInDevice',
                'GPoint',
                'KVahed',
                'PicName'
            )->where('CodeCompany', $this->active_company)->where('CShowInDevice', 1)->orderBy('KMegdar', 'DESC')->limit(16)->get();
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error: ' . $e->getMessage(),
                'result'  => null,
            ], 503);
        }
    }

    public function homePage()
    {
        try {
            return response()->json([
                'result' => [
                    'categories' => $this->list_categories(),
                    'banners' => $this->fetchBanners(),
                    'newestProducts' => $this->fetchNewestProducts(),
                    'offeredProducts' => $this->offerd_products(),
                    'bestSeller' => $this->bestSeller(),

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
