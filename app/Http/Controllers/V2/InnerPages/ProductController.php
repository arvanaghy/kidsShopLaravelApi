<?php

namespace App\Http\Controllers\V2\InnerPages;

use App\Http\Controllers\Controller;
use App\Models\ProductImagesModel;
use App\Models\ProductModel;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Intervention\Image\ImageManagerStatic as Image;



class ProductController extends Controller
{
    protected $active_company = null;
    protected $financial_period = null;

    protected function CreateProductPath()
    {
        $paths = [
            "products-image",
            "products-image/original",
            "products-image/webp"
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
            $this->CreateProductPath();
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

    protected function CreateProductImages($data, $picName)
    {

        $GCodePathOriginal = public_path('products-image/original/' . ceil($data->GCode));
        if (!File::isDirectory($GCodePathOriginal)) {
            File::makeDirectory($GCodePathOriginal, 0755, true, true);
        }

        $SCodePathOriginal = public_path('products-image/original/' . ceil($data->GCode) . '/' . ceil($data->SCode));
        if (!File::isDirectory($SCodePathOriginal)) {
            File::makeDirectory($SCodePathOriginal, 0755, true, true);
        }

        $GCodePathWebp = public_path('products-image/webp/' . ceil($data->GCode));
        if (!File::isDirectory($GCodePathWebp)) {
            File::makeDirectory($GCodePathWebp, 0755, true, true);
        }

        $SCodePathWebp = public_path('products-image/webp/' . ceil($data->GCode) . '/' . ceil($data->SCode));
        if (!File::isDirectory($SCodePathWebp)) {
            File::makeDirectory($SCodePathWebp, 0755, true, true);
        }

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

    protected function cleanupUnusedImages($product, $productImages)
    {
        try {
            $webpDir = public_path('products-image/webp/' . ceil($product->GCode) . '/' . ceil($product->SCode));
            $validPicNames = $productImages
                ->pluck('PicName')
                ->filter()
                ->map(function ($name) {
                    return $name . '.webp';
                })
                ->toArray();

            if (File::isDirectory($webpDir)) {
                $files = File::files($webpDir);
                foreach ($files as $file) {
                    $filename = $file->getFilename();
                    if (!in_array($filename, $validPicNames)) {
                        File::delete($file->getPathname());
                    }
                }
            }
        } catch (\Exception $e) {
            return;
        }
    }

    public function showProduct(Request $request, $Code)
    {
        try {
            $product = ProductModel::where('Code', $Code)->firstOrFail();

            $productImages = ProductImagesModel::where('CodeKala', $product->Code)->get();

            foreach ($productImages as $image) {
                if (!empty($image->Pic) && !empty($image->PicName)) {
                    $image->GCode = $product->GCode;
                    $image->SCode = $product->SCode;
                    $createdAt = Carbon::parse($image->created_at);
                    $picName = ceil($image->Code) . "_" . $createdAt->getTimestamp();
                    $this->CreateProductImages($image, $picName);
                    DB::table('KalaImage')->where('Code', $image->Code)->update(['PicName' => $picName]);
                }
            }

            DB::table('Kala')->where('Code', $product->Code)->update(['CChangePic' => 0]);

            $this->cleanupUnusedImages($product, $productImages);

            $result = ProductModel::with(['productSizeColor', 'productImages' => function ($query) {
                $query->select('Code', 'PicName', 'Def', 'CodeKala');
            }])->where('Code', $Code)->select(
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
            )->first();

            return response()->json([
                'product' => $result,

                'message' => 'محصول با موفقیت نمایش داده شد'
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
