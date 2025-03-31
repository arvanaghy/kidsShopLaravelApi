<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\ProductModel;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManagerStatic as Image;


class SearchController extends Controller
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

    protected function CreatePath($data)
    {
        if (!File::isDirectory(public_path("products-image"))) {
            File::makeDirectory("products-image", 0755, true, true);
        }

        if (!File::isDirectory(public_path("products-image/original"))) {
            File::makeDirectory("products-image/original", 0755, true, true);
        }

        if (!File::isDirectory(public_path("products-image/original/" . floor($data->GCode)))) {
            File::makeDirectory("products-image/original/" . floor($data->GCode), 0755, true, true);
        }

        if (!File::isDirectory(public_path("products-image/original/" . floor($data->GCode) . "/" . floor($data->SCode)))) {
            File::makeDirectory("products-image/original/" . floor($data->GCode) . "/" . floor($data->SCode), 0755, true, true);
        }

        if (!File::isDirectory(public_path("products-image/webp"))) {
            File::makeDirectory("products-image/webp", 0755, true, true);
        }

        if (!File::isDirectory(public_path("products-image/webp/" . floor($data->GCode)))) {
            File::makeDirectory("products-image/webp/" . floor($data->GCode), 0755, true, true);
        }
        if (!File::isDirectory(public_path("products-image/webp/" . floor($data->GCode) . "/" . floor($data->SCode)))) {
            File::makeDirectory("products-image/webp/" . floor($data->GCode) . "/" . floor($data->SCode), 0755, true, true);
        }
    }


    protected function CreateImages($data)
    {

        $imagePath = "products-image/original/" . floor($data->GCode) . "/" . floor($data->SCode) . "/" . floor($data->Code) . ".jpg";
        $webpPath =  "products-image/webp/" . floor($data->GCode) . "/" . floor($data->SCode) . "/" . floor($data->Code) . ".webp";

        File::put(public_path($imagePath), $data->Pic);
        Image::configure(['driver' => 'gd']);
        Image::make($imagePath)->encode('webp', 80)->resize(250, 250)->save($webpPath, 100);
        File::delete(public_path($imagePath));
    }


    public function search($SearchPhrase)
    {
        try {
            $SearchPhrase = str_replace("ی", "ي", $SearchPhrase);
            // $SearchPhrase = str_replace("ک", "ك", $SearchPhrase);
            $searchPhrases = explode(' ', $SearchPhrase);

            $productsImages = ProductModel::query();
            // foreach ($searchPhrases as $phrase1) {
            //     $productsImages->where('Name', 'like', "%$phrase1%");
            // }
            $productsImages->where('UCode', 'like', "%$SearchPhrase%");
            $imageCreation = $productsImages->select('Pic', 'GCode', 'SCode', 'Code', 'CChangePic')->where('CodeCompany', $this->active_company)->where('CShowInDevice', 1)->orderBy('UCode', 'ASC')->paginate(24);
            foreach ($imageCreation as $value) {
                if ($value->Pic and $value->CChangePic) {
                    $this->CreatePath($value);
                    $this->CreateImages($value);
                    Log::info('Image creation completed for product via search:', [
                        'GCode' => $value->GCode,
                        'SCode' => $value->SCode,
                        'Code' => $value->Code,
                    ]);
                    DB::table('Kala')->where('Code', $value->Code)->where('CodeCompany', $this->active_company)->update(['CChangePic' => 0]);
                }
            }

            $products = ProductModel::query();
            // foreach ($searchPhrases as $phrase) {
            //     $products->where('Name', 'like', "%$phrase%");
            // }
            $products->where('UCode', 'like', "%$SearchPhrase%");

            return response()->json([
                "message" => 'موفقیت آمیز بود',
                "result" => $products->select(
                    'CodeCompany',
                    'CanSelect',
                    'GCode',
                    'GName',
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
                    'KVahed'
                )->orderBy('UCode', 'ASC')->where('CodeCompany', $this->active_company)->where('CShowInDevice', 1)->paginate(24)
            ], 200);
        } catch (Exception $exception) {

            return response()->json([
                "message" => $exception->getMessage(),
                "result" => null
            ], 503);
        }
    }
}
