<?php

namespace App\Http\Controllers\V2\InnerPages;

use App\Http\Controllers\Controller;
use App\Models\CategoryModel;
use App\Models\ProductModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Intervention\Image\ImageManagerStatic as Image;
use Exception;
use Carbon\Carbon;

class HomeController extends Controller
{
    private const IMAGE_QUALITY = 100;
    private const CATEGORY_IMAGE_SIZE = [250, 250];
    private const BANNER_SIZES = [
        'desktop' => [1360, 786],
        'mobile' => [390, 844]
    ];
    private const LIMIT_DEFAULT = 16;
    private const LIMIT_BANNERS = 6;

    private ?string $active_company = null;
    private ?string $financial_period = null;

    public function __construct(Request $request)
    {
        try {
            $this->createDirectories([
                'category-images' => ['original', 'webp'],
                'banner-images' => ['original', 'webp'],
                'products-image' => ['original', 'webp']
            ]);

            $this->active_company = DB::table('Company')
                ->where('DeviceSelected', 1)
                ->value('Code');

            if ($this->active_company) {
                $this->financial_period = DB::table('DoreMali')
                    ->where('CodeCompany', $this->active_company)
                    ->where('DeviceSelected', 1)
                    ->value('Code');
            }
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    private function createDirectories(array $structure): void
    {
        foreach ($structure as $base => $subdirs) {
            foreach ($subdirs as $subdir) {
                $path = public_path("$base/$subdir");
                if (!File::isDirectory($path)) {
                    File::makeDirectory($path, 0755, true);
                }
            }
        }
    }

    private function removeImage(string $path, string $filename, array $suffixes = ['.webp']): void
    {
        foreach ($suffixes as $suffix) {
            $fullPath = public_path("$path/{$filename}{$suffix}");
            if (File::exists($fullPath)) {
                File::delete($fullPath);
            }
        }
    }

    private function processImage(string $sourcePath, string $destPath, string $filename, array $size, string $sourceData): void
    {
        $originalPath = public_path("$sourcePath/$filename.jpg");
        $webpPath = public_path("$destPath/$filename.webp");

        File::put($originalPath, $sourceData);
        Image::configure(['driver' => 'gd']);
        Image::make($originalPath)
            ->encode('webp', self::IMAGE_QUALITY)
            ->resize(...$size)
            ->save($webpPath, self::IMAGE_QUALITY);

        File::delete($originalPath);
    }

    private function processCategoryImages(object $data, string $picName): void
    {
        if (!empty($data->Pic)) {
            $this->processImage(
                'category-images/original',
                'category-images/webp',
                $picName,
                self::CATEGORY_IMAGE_SIZE,
                $data->Pic
            );
        }
    }

    private function processBannerImages(object $data, string $picName): void
    {
        if (!empty($data->Pic)) {
            foreach (self::BANNER_SIZES as $type => $size) {
                $this->processImage(
                    'banner-images/original',
                    'banner-images/webp',
                    "{$picName}_{$type}",
                    $size,
                    $data->Pic
                );
            }
        }
    }

    private function processProductImages(object $data, ?string $picName): void
    {
        if (!empty($data->Pic) && $picName !== null) {
            $path = "products-image";
            $subPath = ceil($data->GCode) . "/" . ceil($data->SCode);
            $this->createDirectories([$path => ["original/$subPath", "webp/$subPath"]]);
            $this->processImage(
                "$path/original/$subPath",
                "$path/webp/$subPath",
                $picName,
                self::CATEGORY_IMAGE_SIZE,
                $data->Pic
            );
        }
    }

    private function errorResponse(string $message, int $status): \Illuminate\Http\JsonResponse
    {
        return response()->json(['status' => false, 'message' => $message], $status);
    }

    private function updateImages(string $table, string $imagePath, array $queryConditions, callable $imageProcessor): mixed
    {
        try {
            $images = DB::table($table)
                ->where($queryConditions)
                ->where('CChangePic', 1)
                ->select('Pic', 'Code', 'CChangePic', 'PicName')
                ->limit(self::LIMIT_DEFAULT)
                ->get();

            foreach ($images as $image) {
                if (!empty($image->PicName)) {
                    $this->removeImage($imagePath, $image->PicName);
                }

                $picName = !empty($image->Pic) 
                    ? ceil($image->Code) . "_" . rand(10000, 99999)
                    : null;

                $imageProcessor($image, $picName);
                
                DB::table($table)
                    ->where('Code', $image->Code)
                    ->update(['CChangePic' => 0, 'PicName' => $picName]);
            }

            return DB::table($table)
                ->where($queryConditions)
                ->orderBy('Code', 'DESC')
                ->limit(self::LIMIT_DEFAULT)
                ->get();
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 503);
        }
    }

    protected function list_categories(): mixed
    {
        return $this->updateImages(
            'KalaGroup',
            'category-images/webp',
            ['CodeCompany' => $this->active_company],
            fn($image, $picName) => $this->processCategoryImages($image, $picName)
        );
    }

    protected function fetchBanners(): mixed
    {
        return $this->updateImages(
            'DeviceHeaderImage',
            'banner-images/webp',
            ['CodeCompany' => $this->active_company],
            fn($image, $picName) => $this->processBannerImages($image, $picName)
        );
    }

    private function processProductUpdates(string $table, array $conditions, array $selectFields): mixed
    {
        try {
            $images = ProductModel::where($conditions)
                ->where('CChangePic', 1)
                ->select(['Pic', 'ImageCode', 'created_at', 'GCode', 'SCode', 'Code', 'PicName'])
                ->orderBy('UCode', 'ASC')
                ->limit(self::LIMIT_DEFAULT)
                ->get();
    
            foreach ($images as $image) {
                $picName = !empty($image->Pic)
                    ? ceil($image->ImageCode) . "_" . Carbon::parse($image->created_at)->getTimestamp()
                    : null;
    
                $this->processProductImages($image, $picName);
    
                if ($picName !== null) {  // Only update if we have a valid picName
                    DB::table('KalaImage')->where('Code', $image->ImageCode)->update(['PicName' => $picName]);
                }
                DB::table('Kala')->where('Code', $image->Code)->update(['CChangePic' => 0]);
            }
    
            return ProductModel::where($conditions)
                ->select($selectFields)
                ->orderBy('UCode', 'ASC')
                ->limit(self::LIMIT_DEFAULT)
                ->get();
        } catch (Exception $e) {
            return $this->errorResponse("Error: " . $e->getMessage(), 503);
        }
    }

    protected function fetchNewestProducts(): mixed
    {
        $fields = [
            'CodeCompany', 'CanSelect', 'GCode', 'GName', 'Comment', 'SCode', 'SName',
            'Code', 'CodeKala', 'Name', 'Model', 'UCode', 'Vahed', 'KMegdar', 'KPrice',
            'SPrice', 'KhordePrice', 'OmdePrice', 'HamkarPrice', 'AgsatPrice', 'CheckPrice',
            'DForoosh', 'CShowInDevice', 'CFestival', 'GPoint', 'KVahed', 'PicName'
        ];
        
        return $this->processProductUpdates(
            'Kala',
            ['CodeCompany' => $this->active_company, 'CShowInDevice' => 1],
            $fields
        );
    }

    protected function offerd_products(): mixed
    {
        $fields = [
            'CodeCompany', 'CanSelect', 'GCode', 'GName', 'Comment', 'SCode', 'SName',
            'Code', 'CodeKala', 'Name', 'Model', 'UCode', 'Vahed', 'KMegdar', 'KPrice',
            'SPrice', 'KhordePrice', 'OmdePrice', 'HamkarPrice', 'AgsatPrice', 'CheckPrice',
            'DForoosh', 'CShowInDevice', 'CFestival', 'GPoint', 'KVahed', 'PicName'
        ];
        
        return $this->processProductUpdates(
            'Kala',
            ['CodeCompany' => $this->active_company, 'CShowInDevice' => 1, 'CFestival' => 1],
            $fields
        );
    }

    protected function bestSeller(): mixed
    {
        try {
            $images = DB::table('AV_KalaTedadForooshKol_View')
                ->where('CShowInDevice', 1)
                ->where('CodeCompany', $this->active_company)
                ->where('CChangePic', 1)
                ->select(['Pic', 'KCode as Code', 'ImageCode', 'created_at', 'GCode', 'SGCode as SCode', 'PicName'])
                ->limit(self::LIMIT_DEFAULT)
                ->get();
    
            foreach ($images as $image) {
                $picName = !empty($image->Pic)
                    ? ceil($image->ImageCode) . "_" . Carbon::parse($image->created_at)->getTimestamp()
                    : null;
    
                $this->processProductImages($image, $picName);
    
                if ($picName !== null) {  // Only update if we have a valid picName
                    DB::table('KalaImage')->where('Code', $image->ImageCode)->update(['PicName' => $picName]);
                }
                DB::table('Kala')->where('Code', $image->Code)->update(['CChangePic' => 0]);
            }
    
            return DB::table('AV_KalaTedadForooshKol_View')
                ->select([
                    'GCode', 'GName', 'SGCode as SCode', 'SGName as SName', 'KCode as Code',
                    'KName as Name', 'Vahed', 'Comment', 'KMegdar', 'SPrice', 'KhordePrice',
                    'OmdePrice', 'HamkarPrice', 'AgsatPrice', 'CheckPrice', 'DForoosh',
                    'CShowInDevice', 'GPoint', 'KVahed', 'PicName'
                ])
                ->where('CodeCompany', $this->active_company)
                ->where('CShowInDevice', 1)
                ->orderBy('KMegdar', 'DESC')
                ->limit(self::LIMIT_DEFAULT)
                ->get();
        } catch (Exception $e) {
            return $this->errorResponse("Error: " . $e->getMessage(), 503);
        }
    }

    public function index(): \Illuminate\Http\JsonResponse
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
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}