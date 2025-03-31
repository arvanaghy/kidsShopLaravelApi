<?php

namespace App\Http\Controllers\V1;

use App\Models\ProductModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use App\Models\CustomerModel;
use Exception;
use Illuminate\Support\Facades\Response;
use Intervention\Image\ImageManagerStatic as Image;


class ProductController extends Controller
{

    protected $active_company = null;
    protected $financial_period = null;

    public function __construct(Request $request)
    {
        try {
            $active_company = DB::table('Company')->where('DeviceSelected', 1)->first();
            $this->active_company = optional($active_company)->Code ?? null;
    
            if ($this->active_company) {
                $active_financial_period = DB::table('DoreMali')
                    ->where('CodeCompany', $this->active_company)
                    ->where('DeviceSelected', 1)
                    ->first();
    
                $this->financial_period = optional($active_financial_period)->Code ?? null;
            }
        } catch (\Exception $e) {
            Log::error("Error initializing constructor: " . $e->getMessage());
        }
    }
    

    protected function CreatePath($data)
    {
        $basePath = public_path("products-image");
    
        // Define subdirectories
        $directories = [
            "$basePath/original/" . floor($data->GCode) . "/" . floor($data->SCode),
            "$basePath/webp/" . floor($data->GCode) . "/" . floor($data->SCode),
        ];
    
        foreach ($directories as $dir) {
            File::makeDirectory($dir, 0755, true, true);
        }
    }
    

    protected function CreateImages($data, $picName)
    {

        $imagePath = "products-image/original/" . floor($data->GCode) . "/" . floor($data->SCode) . "/" . $picName . ".jpg";
        $webpPath =  "products-image/webp/" . floor($data->GCode) . "/" . floor($data->SCode) . "/" . $picName . ".webp";

        File::put(public_path($imagePath), $data->Pic);
        Image::configure(['driver' => 'gd']);
        Image::make($imagePath)->encode('webp', 100)->resize(250, 250)->save($webpPath, 100);
        File::delete(public_path($imagePath));
    }

    protected function removeImages($data)
    {
        // $path = "products-image/webp/" . floor($data->GCode) . "/" . floor($data->SCode);
        // File::deleteDirectory(public_path($path));
    }

    public function resetImages()
    {
        try {
            // update all kala table cchangepic to 1
            DB::table('Kala')->where('CodeCompany', $this->active_company)->update(['CChangePic' => 1]);
            return array(
                'message' => 'success',
                'status' => 200,
            );
        } catch (\Exception $e) {
            return array(
                'message' => $e->getMessage(),
                'status' => 503,
            );
        }
    }

    public function list_products()
    {
        try {
            $imageCreations = ProductModel::select('Pic', 'ImageCode', 'created_at', 'GCode', 'SCode', 'Code', 'CChangePic', 'PicName')->where('CodeCompany', $this->active_company)->where('CShowInDevice', 1)->orderBy('UCode', 'ASC')->paginate(12);

            foreach ($imageCreations as $imageCreation) {
                if ($imageCreation->CChangePic == 1) {
                    $this->removeImages($imageCreation);
                    $this->CreatePath($imageCreation);

                    DB::table('Kala')->Where('Code', $imageCreation->Code)->update(['CChangePic' => 0]);
                    if ($imageCreation->Pic != null) {
                        $picName = ceil($imageCreation->ImageCode) . "_" .  $imageCreation->created_at->getTimestamp();
                        $this->CreateImages($imageCreation,  $picName);

                        DB::table('KalaImage')->Where('Code', $imageCreation->ImageCode)->update(['PicName' => $picName]);
                    }
                }
            }

            $result = ProductModel::select(
                'CodeCompany',
                'CanSelect',
                'GCode',
                'GName',
                'SCode',
                'SName',
                'Comment',
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
                'ImageCode',
                'PicName',
                'created_at',

            )->where('CodeCompany', $this->active_company)->where('CShowInDevice', 1)->orderBy('UCode', 'ASC')->first();


            return response()->json([
                'message' => 'با موفقیت انجام پذیرفت',
                "result" => $result,
                'created_at_in_epoch' => $result->created_at->getTimestamp()
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    public function offerd_products()
    {

        try {
            $imageCreation = ProductModel::select('Pic', 'ImageCode', 'created_at', 'GCode', 'SCode', 'Code', 'PicName', 'CChangePic')->where('CFestival', 1)->where('CodeCompany', $this->active_company)->where('CShowInDevice', 1)->orderBy('UCode', 'DESC')->paginate(12);

            foreach ($imageCreation as $image) {
                if ($image->CChangePic == 1) {
                    $this->removeImages($image);
                    $this->CreatePath($image);

                    DB::table('Kala')->Where('Code', $image->Code)->update(['CChangePic' => 0]);
                    if ($image->Pic != null) {
                        $picName = ceil($image->ImageCode) . "_" .  $image->created_at->getTimestamp();
                        $this->CreateImages($image,  $picName);

                        DB::table('KalaImage')->Where('Code', $image->ImageCode)->update(['PicName' => $picName]);
                    }
                }
            }

            $result = ProductModel::select(
                'CodeCompany',
                'CanSelect',
                'GCode',
                'GName',
                'SCode',
                'Comment',
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
            )->where('CodeCompany', $this->active_company)->where('CFestival', 1)->where('CShowInDevice', 1)->orderBy('UCode', 'DESC')->paginate(12);

            return response()->json([
                'message' => 'با موفقیت انجام پذیرفت',
                "result" => $result
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    public function show_product($Code)
    {

        try {
            $imageCreation = ProductModel::select('GCode', 'SCode', 'Code', 'CChangePic')->where('CodeCompany', $this->active_company)->where('CShowInDevice', 1)->orderBy('CodeKala', 'ASC')->where('Code', $Code)->first();

            if ($imageCreation and $imageCreation->CChangePic == 1) {
                $this->removeImages($imageCreation);
                $this->CreatePath($imageCreation);

                $imagesResults = DB::table('KalaImage')->where('CodeKala', $imageCreation->Code)->get();


                foreach ($imagesResults as $image) {
                    $picName = ceil($image->Code) . "_" .  $image->created_at->getTimestamp();
                    $this->CreateImages($image,  $picName);

                    DB::table('KalaImage')->Where('Code', $image->ImageCode)->update(['PicName' => $picName]);
                }
                DB::table('Kala')->Where('Code', $imageCreation->Code)->update(['CChangePic' => 0]);
            }

            $productImages = array();
            if ($imageCreation) {
                $productImagesResults = DB::table('KalaImage')->select('PicName')->where('CodeKala', $imageCreation->Code)->get();
                foreach ($productImagesResults as $image) {
                    array_push($productImages, $image->PicName);
                }
            } else {
                $productImages = [];
            }


            $result =  ProductModel::select(
                'CodeCompany',
                'CanSelect',
                'GCode',
                'GName',
                'SCode',
                'Comment',
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
            )->where('CodeCompany', $this->active_company)->where('Code', $Code)->where('CShowInDevice', 1)->orderBy('CodeKala', 'ASC')->first();

            if ($result) {
                $result->productImages = $productImages;
            }

            return response()->json([
                'message' => 'با موفقیت انجام پذیرفت',
                "result" => $result
            ], 200);
        } catch (Exception $e) {

            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    public function search_products($SearchPhrase)
    {
        try {
            $SearchPhrase = str_replace("ی", "ي", $SearchPhrase);
            // $SearchPhrase = str_replace("ک", "ك", $SearchPhrase);
            $searchPhrases = explode(' ', $SearchPhrase);
            $products1 = ProductModel::query();
            $products1->where(function ($query) use ($searchPhrases) {
                foreach ($searchPhrases as $phrase) {
                    $query->where('Name', 'like', "%$phrase%");
                }
            });
            $imageCreation = $products1->select(
                'GCode',
                'ImageCode',
                'created_at',
                'Pic',
                'SCode',
                'Code',
                'CChangePic',
                'PicName'
            )->orderBy('CodeKala', 'ASC')->where('CodeCompany', $this->active_company)->where('CShowInDevice', 1)->paginate(12);

            foreach ($imageCreation as $image) {
                if ($image->CChangePic == 1) {
                    $this->removeImages($image);
                    $this->CreatePath($image);

                    DB::table('Kala')->Where('Code', $image->Code)->update(['CChangePic' => 0]);
                    if ($image->Pic != null) {
                        $picName = ceil($image->ImageCode) . "_" .  $image->created_at->getTimestamp();
                        $this->CreateImages($image,  $picName);

                        DB::table('KalaImage')->Where('Code', $image->ImageCode)->update(['PicName' => $picName]);
                    }
                }
            }

            $products = ProductModel::query();
            $products->where(function ($query) use ($searchPhrases) {
                foreach ($searchPhrases as $phrase) {
                    $query->where('Name', 'like', "%$phrase%");
                }
            });

            $result = $products->select(
                'CodeCompany',
                'CanSelect',
                'GCode',
                'GName',
                'SCode',
                'Comment',
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
                ->where('CShowInDevice', 1)
                ->where('CodeCompany', $this->active_company)
                ->orderBy('CodeKala', 'ASC')
                ->paginate(12);

            return response()->json([
                'message' => 'با موفقیت انجام پذیرفت',
                "result" => $result
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    public function list_category_products($code)
    {
        try {
            $result =  ProductModel::select(
                'CodeCompany',
                'CanSelect',
                'GCode',
                'GName',
                'SCode',
                'Comment',
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
            )->where('CodeCompany', $this->active_company)->where('GCode', $code)->where('CShowInDevice', 1)->orderBy('CodeKala', 'ASC')->paginate(12);

            return response()->json([
                "message" => 'با موفقیت انجام پذیرفت',
                "result" => $result
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    public function list_subcategory_products($code)
    {
        try {
            $imageCreation = ProductModel::select('Pic', 'ImageCode', 'created_at', 'GCode', 'SCode', 'Code', 'CChangePic', 'PicName')->where('CodeCompany', $this->active_company)->where('CShowInDevice', 1)->where('SCode', $code)->orderBy('CodeKala', 'ASC')->paginate(32);

            foreach ($imageCreation as $image) {
                if ($image->CChangePic == 1) {
                    $this->CreatePath($image);
                    $this->removeImages($image);

                    DB::table('Kala')->Where('Code', $image->Code)->update(['CChangePic' => 0]);
                    if ($image->Pic != null) {
                        $picName = ceil($image->ImageCode) . "_" .  $image->created_at->getTimestamp();
                        $this->CreateImages($image,  $picName);

                        DB::table('KalaImage')->Where('Code', $image->ImageCode)->update(['PicName' => $picName]);
                    }
                }
            }

            $result =  ProductModel::select(
                'CodeCompany',
                'CanSelect',
                'GCode',
                'GName',
                'SCode',
                'Comment',
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
            )->where('CodeCompany', $this->active_company)->where('CShowInDevice', 1)->where('SCode', $code)->orderBy('CodeKala', 'ASC')->paginate(32);

            return response()->json([
                'message' => 'با موفقیت اطلاع رسانی شد',
                "result" => $result
            ], 200);
        } catch (\Exception $exception) {

            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    public function list_products_for_website(Request $request, $sortType)
    {
        try {
            $userResult = null;
            $token = $request->bearerToken();
            if ($token) {
                $userResult = CustomerModel::where('UToken', $token)->first();
            }

            $sortField = 'KhordePrice';
            $sortOrder = 'ASC';

            $priceFields = [
                '0' => 'SPrice',
                '1' => 'KhordePrice',
                '2' => 'OmdePrice',
                '3' => 'HamkarPrice',
                '4' => 'AgsatPrice',
                '5' => 'CheckPrice',
            ];

            switch ($sortType) {
                case 'UCode':
                    $sortField = 'UCode';
                    break;

                case 'price_asc':
                    $sortField = $userResult ? ($priceFields[$userResult->ForooshType] ?? 'KhordePrice') : 'KhordePrice';
                    $sortOrder = 'ASC';
                    break;

                case 'price_des':
                    $sortField = $userResult ? ($priceFields[$userResult->ForooshType] ?? 'KhordePrice') : 'SPrice';
                    $sortOrder = 'DESC';
                    break;

                case 'best_sell':
                    return DB::table('AV_KalaTedadForooshKol_View')
                        ->select(
                            'GCode',
                            'GName',
                            'SGCode as SCode',
                            'SGName as SName',
                            'KCode as Code',
                            'KName as Name',
                            'Vahed',
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
                        )
                        ->where('CodeCompany', $this->active_company)
                        ->where('CShowInDevice', 1)
                        ->orderBy('KMegdar', 'DESC')
                        ->paginate(12);
            }

            $imageResults = ProductModel::where('CodeCompany', $this->active_company)
                ->where('CShowInDevice', 1)
                ->select('Pic', 'ImageCode', 'created_at', 'GCode', 'SCode', 'Code', 'CChangePic', 'PicName')
                ->orderBy($sortField, $sortOrder)
                ->paginate(12);

            foreach ($imageResults as $image) {
                if ($image->CChangePic == 1) {
                    $this->removeImages($image);
                    $this->CreatePath($image);
                    DB::table('Kala')->where('Code', $image->Code)->update(['CChangePic' => 0]);

                    if (!empty($image->Pic)) {
                        $picName = ceil($image->ImageCode) . "_" . $image->created_at->getTimestamp();
                        $this->CreateImages($image, $picName);
                        DB::table('KalaImage')->where('Code', $image->ImageCode)->update(['PicName' => $picName]);
                    }
                }
            }

            $products = ProductModel::where('CodeCompany', $this->active_company)
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
                ->orderBy($sortField, $sortOrder)
                ->paginate(12);

            return response()->json([
                'message' => 'Successfully retrieved product list.',
                'result'  => $products,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error: ' . $e->getMessage(),
                'result'  => null,
            ], 503);
        }
    }

    public function list__subcategory_products_for_website(Request $request, $subcategoryCode, $sortType)
    {
        try {
            $userResult = null;
            if (isset($request) and $request->bearerToken() != null) {
                $token = $request->bearerToken();
                $userResult = CustomerModel::where('UToken', $token)->first();
            }
            $SType = 'ASC';
            $SField = 'KhordePrice';
            switch ($sortType) {
                case 'UCode':
                    $SField = 'UCode';
                    $SType = 'ASC';
                    break;
                case 'price_asc':
                    $SField = 'KhordePrice';
                    $SType = 'DESC';
                    if ($userResult) {
                        switch ($userResult->ForooshType) {
                            case '0':
                                $SField = 'SPrice';
                                break;
                            case '1':
                                $SField = 'KhordePrice';
                                break;
                            case '2':
                                $SField = 'OmdePrice';
                                break;
                            case '3':
                                $SField = 'HamkarPrice';
                                break;
                            case '4':
                                $SField = 'AgsatPrice';
                                break;
                            case '5':
                                $SField = 'CheckPrice';
                                break;
                            default:
                                $SField = 'KhordePrice';
                                break;
                        }
                    } else {
                        $SField = 'KhordePrice';
                    }
                    break;
                case 'price_des':
                    $SField = 'SPrice';
                    $SType = 'ASC';
                    if ($userResult) {
                        switch ($userResult->ForooshType) {
                            case '0':
                                $SField = 'SPrice';
                                break;
                            case '1':
                                $SField = 'KhordePrice';
                                break;
                            case '2':
                                $SField = 'OmdePrice';
                                break;
                            case '3':
                                $SField = 'HamkarPrice';
                                break;
                            case '4':
                                $SField = 'AgsatPrice';
                                break;
                            case '5':
                                $SField = 'CheckPrice';
                                break;
                            default:
                                $SField = 'KhordePrice';
                                break;
                        }
                    } else {
                        $SField = 'KhordePrice';
                    }
                    break;
                default:
                    $SField = 'UCode';
                    $SType = 'ASC';
                    break;
            }
            $imageCreation = ProductModel::select('Pic', 'ImageCode', 'created_at', 'GCode', 'SCode', 'Code', 'CChangePic', 'PicName')->where('CodeCompany', $this->active_company)->where('SCode', $subcategoryCode)->where('CShowInDevice', 1)->orderBy($SField, $SType)->paginate(12);
            foreach ($imageCreation as $image) {
                if ($image->CChangePic == 1) {
                    $this->removeImages($image);
                    $this->CreatePath($image);

                    DB::table('Kala')->Where('Code', $image->Code)->update(['CChangePic' => 0]);
                    if ($image->Pic != null) {
                        $picName = ceil($image->ImageCode) . "_" .  $image->created_at->getTimestamp();
                        $this->CreateImages($image,  $picName);

                        DB::table('KalaImage')->Where('Code', $image->ImageCode)->update(['PicName' => $picName]);
                    }
                }
            }
            $result =  ProductModel::select(
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
            )->where('CodeCompany', $this->active_company)->where('SCode', $subcategoryCode)->where('CShowInDevice', 1)->orderBy($SField, $SType)->paginate(12);

            return response()->json([
                'message' => 'عملیات با موفقیت انجام شد',
                "result" => $result
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    public function list__subcategory_products_for_website_with_PCode(Request $request, $ProducteCode, $sortType)
    {
        try {
            $userResult = null;
            if (isset($request) and $request->bearerToken() != null) {
                $token = $request->bearerToken();
                $userResult = CustomerModel::where('UToken', $token)->first();
            }
            $SType = 'ASC';
            $SField = 'KhordePrice';
            switch ($sortType) {
                case 'UCode':
                    $SField = 'UCode';
                    $SType = 'ASC';
                    break;
                case 'price_asc':
                    $SField = 'KhordePrice';
                    $SType = 'DESC';
                    if ($userResult) {
                        switch ($userResult->ForooshType) {
                            case '0':
                                $SField = 'SPrice';
                                break;
                            case '1':
                                $SField = 'KhordePrice';
                                break;
                            case '2':
                                $SField = 'OmdePrice';
                                break;
                            case '3':
                                $SField = 'HamkarPrice';
                                break;
                            case '4':
                                $SField = 'AgsatPrice';
                                break;
                            case '5':
                                $SField = 'CheckPrice';
                                break;
                            default:
                                $SField = 'KhordePrice';
                                break;
                        }
                    } else {
                        $SField = 'KhordePrice';
                    }
                    break;
                case 'price_des':
                    $SField = 'SPrice';
                    $SType = 'ASC';
                    if ($userResult) {
                        switch ($userResult->ForooshType) {
                            case '0':
                                $SField = 'SPrice';
                                break;
                            case '1':
                                $SField = 'KhordePrice';
                                break;
                            case '2':
                                $SField = 'OmdePrice';
                                break;
                            case '3':
                                $SField = 'HamkarPrice';
                                break;
                            case '4':
                                $SField = 'AgsatPrice';
                                break;
                            case '5':
                                $SField = 'CheckPrice';
                                break;
                            default:
                                $SField = 'KhordePrice';
                                break;
                        }
                    } else {
                        $SField = 'KhordePrice';
                    }
                    break;
                default:
                    $SField = 'UCode';
                    $SType = 'ASC';
                    break;
            }

            $product = ProductModel::select('SCode')->where('CodeCompany', $this->active_company)->where('Code', $ProducteCode)->firstOrFail();
            $imageCreation = ProductModel::select('Pic', 'ImageCode', 'created_at', 'GCode', 'SCode', 'Code', 'CChangePic', 'PicName')->where('CodeCompany', $this->active_company)->where('SCode', $product->SCode)->where('CShowInDevice', 1)->orderBy($SField, $SType)->paginate(12);
            foreach ($imageCreation as $image) {
                if ($image->CChangePic == 1) {
                    $this->removeImages($image);
                    $this->CreatePath($image);

                    DB::table('Kala')->Where('Code', $image->ImageCode)->update(['CChangePic' => 0]);
                    if ($image->Pic != null) {
                        $picName = ceil($image->ImageCode) . "_" .  $image->created_at->getTimestamp();
                        $this->CreateImages($image,  $picName);

                        DB::table('KalaImage')->Where('Code', $image->ImageCode)->update(['PicName' => $picName]);
                    }
                }
            }

            $result = ProductModel::select(
                'CodeCompany',
                'CanSelect',
                'GCode',
                'GName',
                'SCode',
                'Comment',
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
            )->where('CodeCompany', $this->active_company)->where('SCode', $product->SCode)->where('CShowInDevice', 1)->orderBy($SField, $SType)->paginate(12);
            return response()->json([
                'message' => 'عملیات با موفقیت انجام شد',
                "result" => $result
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    public function list__category_products_for_website(Request $request, $categoryCode, $sortType)
    {
        try {
            $userResult = null;
            if (isset($request) and $request->bearerToken() != null) {
                $token = $request->bearerToken();
                $userResult = CustomerModel::where('UToken', $token)->first();
            }
            $SType = 'ASC';
            $SField = 'KhordePrice';
            switch ($sortType) {
                case 'UCode':
                    $SField = 'UCode';
                    $SType = 'ASC';
                    break;
                case 'price_asc':
                    $SField = 'KhordePrice';
                    $SType = 'DESC';
                    if ($userResult) {
                        switch ($userResult->ForooshType) {
                            case '0':
                                $SField = 'SPrice';
                                break;
                            case '1':
                                $SField = 'KhordePrice';
                                break;
                            case '2':
                                $SField = 'OmdePrice';
                                break;
                            case '3':
                                $SField = 'HamkarPrice';
                                break;
                            case '4':
                                $SField = 'AgsatPrice';
                                break;
                            case '5':
                                $SField = 'CheckPrice';
                                break;
                            default:
                                $SField = 'KhordePrice';
                                break;
                        }
                    } else {
                        $SField = 'KhordePrice';
                    }
                    break;
                case 'price_des':
                    $SField = 'SPrice';
                    $SType = 'ASC';
                    if ($userResult) {
                        switch ($userResult->ForooshType) {
                            case '0':
                                $SField = 'SPrice';
                                break;
                            case '1':
                                $SField = 'KhordePrice';
                                break;
                            case '2':
                                $SField = 'OmdePrice';
                                break;
                            case '3':
                                $SField = 'HamkarPrice';
                                break;
                            case '4':
                                $SField = 'AgsatPrice';
                                break;
                            case '5':
                                $SField = 'CheckPrice';
                                break;
                            default:
                                $SField = 'KhordePrice';
                                break;
                        }
                    } else {
                        $SField = 'KhordePrice';
                    }
                    break;
                default:
                    $SField = 'UCode';
                    $SType = 'ASC';
                    break;
            }
            $imageCreation = ProductModel::select('Pic', 'ImageCode', 'created_at', 'GCode', 'SCode', 'Code', 'CChangePic', 'PicName')->where('CodeCompany', $this->active_company)->where('GCode', $categoryCode)->where('CShowInDevice', 1)->orderBy($SField, $SType)->paginate(12);
            foreach ($imageCreation as $image) {
                if ($image->CChangePic == 1) {
                    $this->removeImages($image);
                    $this->CreatePath($image);

                    DB::table('Kala')->Where('Code', $image->Code)->update(['CChangePic' => 0]);
                    if ($image->Pic != null) {
                        $picName = ceil($image->ImageCode) . "_" .  $image->created_at->getTimestamp();
                        $this->CreateImages($image,  $picName);

                        DB::table('KalaImage')->Where('Code', $image->ImageCode)->update(['PicName' => $picName]);
                    }
                }
            }

            $result = ProductModel::select(
                'CodeCompany',
                'CanSelect',
                'GCode',
                'GName',
                'SCode',
                'SName',
                'Comment',
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
            )->where('CodeCompany', $this->active_company)->where('GCode', $categoryCode)->where('CShowInDevice', 1)->orderBy($SField, $SType)->paginate(12);

            return response()->json([
                'message' => 'عملیات با موفقیت انجام شد',
                "result" => $result
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }


    public function bestSeller()
    {
        try {
            $imageResult = DB::table('AV_KalaTedadForooshKol_View')->select('Pic', 'KCode as Code', 'ImageCode', 'created_at', 'CChangePic', 'GCode', 'SGCode as SCode', 'PicName')->where('CShowInDevice', 1)->where('CodeCompany', $this->active_company)->paginate(12);

            foreach ($imageResult as $image) {
                if ($image->CChangePic == 1) {
                    $this->removeImages($image);
                    $this->CreatePath($image);;
                    DB::table('Kala')->Where('Code', $image->Code)->update(['CChangePic' => 0]);
                    if ($image->Pic != null) {
                        $picName = ceil($image->ImageCode) . "_" .  $image->created_at->getTimestamp();
                        $this->CreateImages($image,  $picName);

                        DB::table('KalaImage')->Where('Code', $image->ImageCode)->update(['PicName' => $picName]);
                    }
                }
            }

            $result = DB::table('AV_KalaTedadForooshKol_View')->select(
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
            )->where('CodeCompany', $this->active_company)->where('CShowInDevice', 1)->orderBy('KMegdar', 'DESC')->paginate(12);

            return response()->json([
                'message' => 'با موفقیت انجام شد',
                "result" => $result
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    public function samePrice($code)
    {

        try {
            $product = ProductModel::where('Code', $code)->where('CodeCompany', $this->active_company)->first();

            $imageCreation = ProductModel::select('Pic', 'ImageCode', 'created_at', 'GCode', 'SCode', 'Code', 'CChangePic', 'PicName')->where('CodeCompany', $this->active_company)->where('GCode', $product->GCode)->where('KhordePrice', '<=', $product->KhordePrice + 100000)->where('KhordePrice', '>=', $product->KhordePrice - 100000)->where('CShowInDevice', 1)->where('Code', '!=', $product->Code)->orderBy('Code', 'Desc')->paginate(12);

            foreach ($imageCreation as $image) {
                if ($image->CChangePic == 1) {
                    $this->removeImages($image);
                    $this->CreatePath($image);

                    DB::table('Kala')->Where('Code', $image->Code)->update(['CChangePic' => 0]);
                    if ($image->Pic != null) {
                        $picName = ceil($image->ImageCode) . "_" .  $image->created_at->getTimestamp();
                        $this->CreateImages($image,  $picName);

                        DB::table('KalaImage')->Where('Code', $image->ImageCode)->update(['PicName' => $picName]);
                    }
                }
            }

            $result = ProductModel::select(
                'CodeCompany',
                'CanSelect',
                'GCode',
                'Comment',
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
                'KVahed',
                'PicName'
            )->where('CodeCompany', $this->active_company)->where('GCode', $product->GCode)->where('KhordePrice', '<=', $product->KhordePrice + 100000)->where('KhordePrice', '>=', $product->KhordePrice - 100000)->where('CShowInDevice', 1)->where('Code', '!=', $product->Code)->orderBy('Code', 'Desc')->paginate(12);

            return response()->json([
                'message' => 'با موفقیت اطلاع رسانی شد',
                "result" => $result
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    public function list_latest_products()
    {
        try {
            $imageCreation = ProductModel::select('Pic', 'ImageCode', 'created_at', 'GCode', 'SCode', 'Code', 'PicName', 'CChangePic')->where('CodeCompany', $this->active_company)->where('CShowInDevice', 1)->orderBy('Code', 'DESC')->paginate(12);

            foreach ($imageCreation as $image) {
                if ($image->CChangePic == 1) {
                    $this->removeImages($image);
                    $this->CreatePath($image);

                    DB::table('Kala')->Where('Code', $image->Code)->update(['CChangePic' => 0]);
                    if ($image->Pic != null) {
                        $picName = ceil($image->ImageCode) . "_" .  $image->created_at->getTimestamp();
                        $this->CreateImages($image,  $picName);

                        DB::table('KalaImage')->Where('Code', $image->ImageCode)->update(['PicName' => $picName]);
                    }
                }
            }

            $result =  ProductModel::select(
                'CodeCompany',
                'CanSelect',
                'GCode',
                'GName',
                'SCode',
                'Comment',
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
            )->where('CodeCompany', $this->active_company)->where('CShowInDevice', 1)->orderBy('Code', 'DESC')->paginate(12);

            return response()->json([
                'message' => 'با موفقیت اطلاع رسانی شد',
                "result" => $result
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }
}
