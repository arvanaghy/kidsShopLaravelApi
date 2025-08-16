<?php

namespace App\Http\Controllers\V2\InnerPages;

use App\Http\Controllers\Controller;
use App\Models\BestSellModel;
use App\Models\CategoryModel;
use Illuminate\Http\Request;
use App\Models\ProductModel;
use App\Models\SubCategoryModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Intervention\Image\ImageManagerStatic as Image;
use Exception;
use Illuminate\Support\Facades\Log;

class SubCategories extends Controller
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
        Image::make($imagePath)->encode('webp', 100)->resize(250, 250, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        })->save($webpPath);

        File::delete($imagePath);
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

        Image::make($imagePath)->encode('webp', 100)->resize(250, 250, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        })->save($webpPath);

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

    protected function createProductImages($data, $picName): bool
    {
        try {
            $imagePath = public_path("products-image/original/{$picName}.jpg");
            $webpPath = public_path("products-image/webp/{$picName}.webp");

            File::put($imagePath, $data->Pic);

            Image::configure(['driver' => 'gd']);
            Image::make($imagePath)
                ->encode('webp', 100)
                ->resize(250, 250, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })
                ->save($webpPath);

            File::delete($imagePath);

            return true;
        } catch (Exception $e) {
            Log::error("Failed to create product image: {$e->getMessage()}");
            return false;
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

    protected function fetchCategory($Code)
    {
        try {
            $query = CategoryModel::where('CodeCompany', $this->active_company)->where('Code', $Code);

            $imageQuery = $query->select('*')->first();

            if ($imageQuery->CChangePic == 1) {
                if (!empty($imageQuery->PicName)) {
                    $this->removeCategoryImage($imageQuery);
                }

                if (!empty($imageQuery->Pic)) {
                    $picName = ceil($imageQuery->Code) . "_" . rand(10000, 99999);
                    $this->CreateCategoryImages($imageQuery, $picName);
                    $updateData = ['CChangePic' => 0, 'PicName' => $picName];
                } else {
                    $updateData = ['CChangePic' => 0, 'PicName' => null];
                }

                DB::table('KalaGroup')->where('Code', $imageQuery->Code)->update($updateData);
            }

            $result = $query->select('Code', 'Name', 'Comment', 'PicName')->first();
            return $result;
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'result' => null
            ], 503);
        }
    }

    protected function fetchSubCategory($Code)
    {
        try {
            $query = SubCategoryModel::where('CodeCompany', $this->active_company)->where('Code', $Code);

            $imageQuery = $query->select('*')->first();

            if ($imageQuery->CChangePic == 1) {
                if (!empty($imageQuery->PicName)) {
                    $this->removeCategoryImage($imageQuery);
                }

                if (!empty($imageQuery->Pic)) {
                    $picName = ceil($imageQuery->Code) . "_" . rand(10000, 99999);
                    $this->CreateSubCategoryImages($imageQuery, $picName);
                    $updateData = ['CChangePic' => 0, 'PicName' => $picName];
                } else {
                    $updateData = ['CChangePic' => 0, 'PicName' => null];
                }

                DB::table('KalaSubGroup')->where('Code', $imageQuery->Code)->update($updateData);
            }

            $result = $query->select('Code', 'Name', 'PicName')->first();
            return $result;
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'result' => null
            ], 503);
        }
    }

    protected function list_category_products($categoryCode)
    {
        try {
            $request = request();

            $query = ProductModel::with(['productSizeColor'])
                ->where('CodeCompany', $this->active_company)
                ->where('GCode', $categoryCode)
                ->where('CShowInDevice', 1)
                ->whereHas('productSizeColor', function ($query) {
                    $query->havingRaw('SUM(Mande) > 0');
                });

            $sortPrice = strtolower($request->query('sort_price'));
            if ($request->has('sort_price') && in_array($sortPrice, ['asc', 'desc'])) {
                $sortPrice = in_array($sortPrice, ['asc', 'desc']) ? $sortPrice : 'asc';
                if ($sortPrice === 'asc') {
                    $query->orderByRaw('CASE WHEN SPrice > 0 THEN SPrice ELSE COALESCE((SELECT MIN(Mablag) FROM AV_KalaSizeColorMande_View WHERE AV_KalaSizeColorMande_View.CodeKala = AV_KalaList_View.Code AND AV_KalaSizeColorMande_View.Mande > 0), 999999999) END ASC');
                } else {
                    $query->orderByRaw('CASE WHEN SPrice > 0 THEN SPrice ELSE COALESCE((SELECT MIN(Mablag) FROM AV_KalaSizeColorMande_View WHERE AV_KalaSizeColorMande_View.CodeKala = AV_KalaList_View.Code AND AV_KalaSizeColorMande_View.Mande > 0), -999999999) END DESC');
                }
            } else {
                $query->orderBy('Code', 'DESC');
            }

            $search = $request->query('search');
            if ($request->has('search') && $request->query('search') != '') {
                // replace 'ی' with 'ي'
                $search = str_replace('ی', 'ي', $search);
                $query->where('Name', 'LIKE', "%{$search}%");
            }

            $size = $request->query('size');
            if ($request->has('size') && $request->query('size') != '') {
                $sizes = explode(',', $size);
                $query->whereHas('productSizeColor', function ($query) use ($sizes) {
                    $query->whereIn('SizeNum', $sizes);
                });
            }

            $color = $request->query('color');
            if ($request->has('color') && $request->query('color') != '') {
                $colors = explode(',', $color);
                $query->whereHas('productSizeColor', function ($query) use ($colors) {
                    $query->whereIn('ColorCode', $colors);
                });
            }

            $min_price  = $request->query('min_price');
            if ($request->has('min_price') && $request->query('min_price') != 0) {
                $query->where('SPrice', '>=', (int)$min_price);
            }
            $max_price  = $request->query('max_price');
            if ($request->has('max_price') && $request->query('max_price') != 100000000) {
                $query->where('SPrice', '<=', (int)$max_price);
            }


            $imageCreation = $query->select([
                'Pic',
                'ImageCode',
                'created_at',
                'Code',
                'CChangePic',
            ])
                ->paginate(24, ['*'], 'product_page');

            DB::transaction(function () use ($imageCreation) {
                foreach ($imageCreation as $product) {
                    if ($product->CChangePic == 1 && !empty($product->Pic)) {
                        $createdAt = Carbon::parse($product->created_at);
                        $picName = "{$product->ImageCode}_{$createdAt->getTimestamp()}";

                        if ($this->createProductImages($product, $picName)) {
                            DB::table('KalaImage')
                                ->where('Code', $product->ImageCode)
                                ->update(['PicName' => $picName]);

                            DB::table('Kala')
                                ->where('Code', $product->Code)
                                ->update(['CChangePic' => 0]);
                        }
                    }
                }
            });


            $productResult = $query->select([
                'CodeCompany',
                'CanSelect',
                'GCode',
                'GName',
                'Comment',
                'SCode',
                'SName',
                'Code',
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
            ])
                ->paginate(24, ['*'], 'product_page');

            $productResult->appends($request->query());

            return $productResult;
        } catch (Exception $e) {
            return response()->json([
                'message' => 'خطا: ' . $e->getMessage(),
                'result' => null
            ], 503);
        }
    }

    protected function list_subcategory_products($subcategoryCode)
    {
        try {
            $request = request();

            $query = ProductModel::with(['productSizeColor'])
                ->where('CodeCompany', $this->active_company)
                ->where('SCode', $subcategoryCode)
                ->where('CShowInDevice', 1)
                ->whereHas('productSizeColor', function ($query) {
                    $query->havingRaw('SUM(Mande) > 0');
                });

            if ($sortPrice = $request->query('sort_price')) {
                $sortPrice = in_array($sortPrice, ['asc', 'desc']) ? $sortPrice : 'asc';
                if ($sortPrice === 'asc') {
                    $query->orderByRaw('CASE WHEN SPrice > 0 THEN SPrice ELSE COALESCE((SELECT MIN(Mablag) FROM AV_KalaSizeColorMande_View WHERE AV_KalaSizeColorMande_View.CodeKala = AV_KalaList_View.Code AND AV_KalaSizeColorMande_View.Mande > 0), 999999999) END ASC');
                } else {
                    $query->orderByRaw('CASE WHEN SPrice > 0 THEN SPrice ELSE COALESCE((SELECT MIN(Mablag) FROM AV_KalaSizeColorMande_View WHERE AV_KalaSizeColorMande_View.CodeKala = AV_KalaList_View.Code AND AV_KalaSizeColorMande_View.Mande > 0), -999999999) END DESC');
                }
            } else {
                $query->orderBy('Code', 'DESC');
            }

            if ($search = $request->query('search')) {
                $search = str_replace('ی', 'ي', $search);
                $query->where('Name', 'LIKE', "%{$search}%");
            }
            if ($size = $request->query('size')) {
                $sizes = explode(',', $size);
                $query->whereHas('productSizeColor', function ($query) use ($sizes) {
                    $query->whereIn('SizeNum', $sizes);
                });
            }
            if ($color = $request->query('color')) {
                $colors = explode(',', $color);
                $query->whereHas('productSizeColor', function ($query) use ($colors) {
                    $query->whereIn('ColorCode', $colors);
                });
            }

            $imageCreation = $query->select([
                'Pic',
                'ImageCode',
                'created_at',
                'Code',
                'CChangePic',
            ])
                ->paginate(24, ['*'], 'product_page');

            DB::transaction(function () use ($imageCreation) {
                foreach ($imageCreation as $product) {
                    if ($product->CChangePic == 1 && !empty($product->Pic)) {
                        $createdAt = Carbon::parse($product->created_at);
                        $picName = "{$product->ImageCode}_{$createdAt->getTimestamp()}";

                        if ($this->createProductImages($product, $picName)) {
                            DB::table('KalaImage')
                                ->where('Code', $product->ImageCode)
                                ->update(['PicName' => $picName]);

                            DB::table('Kala')
                                ->where('Code', $product->Code)
                                ->update(['CChangePic' => 0]);
                        }
                    }
                }
            });


            $productResult = $query->select([
                'CodeCompany',
                'CanSelect',
                'GCode',
                'GName',
                'Comment',
                'SCode',
                'SName',
                'Code',
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
            ])
                ->paginate(24, ['*'], 'product_page');

            $productResult->appends($request->query());

            return $productResult;
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'result' => null
            ], 500);
        }
    }

    protected function list_products()
    {
        try {
            $request = request();

            $query = ProductModel::with(['productSizeColor'])
                ->where('CodeCompany', $this->active_company)
                ->where('CShowInDevice', 1)
                ->whereHas('productSizeColor', function ($query) {
                    $query->havingRaw('SUM(Mande) > 0');
                });

            if ($sortPrice = $request->query('sort_price')) {
                $sortPrice = in_array($sortPrice, ['asc', 'desc']) ? $sortPrice : 'asc';
                if ($sortPrice === 'asc') {
                    $query->orderByRaw('CASE WHEN SPrice > 0 THEN SPrice ELSE COALESCE((SELECT MIN(Mablag) FROM AV_KalaSizeColorMande_View WHERE AV_KalaSizeColorMande_View.CodeKala = AV_KalaList_View.Code AND AV_KalaSizeColorMande_View.Mande > 0), 999999999) END ASC');
                } else {
                    $query->orderByRaw('CASE WHEN SPrice > 0 THEN SPrice ELSE COALESCE((SELECT MIN(Mablag) FROM AV_KalaSizeColorMande_View WHERE AV_KalaSizeColorMande_View.CodeKala = AV_KalaList_View.Code AND AV_KalaSizeColorMande_View.Mande > 0), -999999999) END DESC');
                }
            } else {
                $query->orderBy('Code', 'DESC');
            }

            if ($search = $request->query('search')) {
                $search = str_replace('ی', 'ي', $search);
                $query->where('Name', 'LIKE', "%{$search}%");
            }
            if ($size = $request->query('size')) {
                $sizes = explode(',', $size);
                $query->whereHas('productSizeColor', function ($query) use ($sizes) {
                    $query->whereIn('SizeNum', $sizes);
                });
            }
            if ($color = $request->query('color')) {
                $colors = explode(',', $color);
                $query->whereHas('productSizeColor', function ($query) use ($colors) {
                    $query->whereIn('ColorCode', $colors);
                });
            }

            $imageCreation = $query->select([
                'Pic',
                'ImageCode',
                'created_at',
                'Code',
                'CChangePic',
            ])
                ->paginate(24, ['*'], 'product_page');

            DB::transaction(function () use ($imageCreation) {
                foreach ($imageCreation as $product) {
                    if ($product->CChangePic == 1 && !empty($product->Pic)) {
                        $createdAt = Carbon::parse($product->created_at);
                        $picName = "{$product->ImageCode}_{$createdAt->getTimestamp()}";

                        if ($this->createProductImages($product, $picName)) {
                            DB::table('KalaImage')
                                ->where('Code', $product->ImageCode)
                                ->update(['PicName' => $picName]);

                            DB::table('Kala')
                                ->where('Code', $product->Code)
                                ->update(['CChangePic' => 0]);
                        }
                    }
                }
            });


            $productResult = $query->select([
                'CodeCompany',
                'CanSelect',
                'GCode',
                'GName',
                'Comment',
                'SCode',
                'SName',
                'Code',
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
            ])
                ->paginate(24, ['*'], 'product_page');

            $productResult->appends($request->query());

            return $productResult;
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'result' => null
            ], 500);
        }
    }
    protected function list_offered_products()
    {
        try {
            $request = request();

            $query = ProductModel::with(['productSizeColor'])
                ->where('CodeCompany', $this->active_company)
                ->where('CShowInDevice', 1)
                ->where('CFestival', 1)
                ->whereHas('productSizeColor', function ($query) {
                    $query->havingRaw('SUM(Mande) > 0');
                });


            if ($sortPrice = $request->query('sort_price')) {
                $sortPrice = in_array($sortPrice, ['asc', 'desc']) ? $sortPrice : 'asc';
                if ($sortPrice === 'asc') {
                    $query->orderByRaw('CASE WHEN SPrice > 0 THEN SPrice ELSE COALESCE((SELECT MIN(Mablag) FROM AV_KalaSizeColorMande_View WHERE AV_KalaSizeColorMande_View.CodeKala = AV_KalaList_View.Code AND AV_KalaSizeColorMande_View.Mande > 0), 999999999) END ASC');
                } else {
                    $query->orderByRaw('CASE WHEN SPrice > 0 THEN SPrice ELSE COALESCE((SELECT MIN(Mablag) FROM AV_KalaSizeColorMande_View WHERE AV_KalaSizeColorMande_View.CodeKala = AV_KalaList_View.Code AND AV_KalaSizeColorMande_View.Mande > 0), -999999999) END DESC');
                }
            } else {
                $query->orderBy('Code', 'DESC');
            }

            if ($search = $request->query('search')) {
                $search = str_replace('ی', 'ي', $search);
                $query->where('Name', 'LIKE', "%{$search}%");
            }
            if ($size = $request->query('size')) {
                $sizes = explode(',', $size);
                $query->whereHas('productSizeColor', function ($query) use ($sizes) {
                    $query->whereIn('SizeNum', $sizes);
                });
            }
            if ($color = $request->query('color')) {
                $colors = explode(',', $color);
                $query->whereHas('productSizeColor', function ($query) use ($colors) {
                    $query->whereIn('ColorCode', $colors);
                });
            }

            $imageCreation = $query->select([
                'Pic',
                'ImageCode',
                'created_at',
                'Code',
                'CChangePic',
                'PicName'
            ])
                ->paginate(24, ['*'], 'product_page');

            DB::transaction(function () use ($imageCreation) {
                foreach ($imageCreation as $product) {
                    if ($product->CChangePic == 1 && !empty($product->Pic)) {
                        $createdAt = Carbon::parse($product->created_at);
                        $picName = "{$product->ImageCode}_{$createdAt->getTimestamp()}";

                        if ($this->createProductImages($product, $picName)) {
                            DB::table('KalaImage')
                                ->where('Code', $product->ImageCode)
                                ->update(['PicName' => $picName]);

                            DB::table('Kala')
                                ->where('Code', $product->Code)
                                ->update(['CChangePic' => 0]);
                        }
                    }
                }
            });


            $productResult = $query->select([
                'CodeCompany',
                'CanSelect',
                'GCode',
                'GName',
                'Comment',
                'SCode',
                'SName',
                'Code',
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
            ])
                ->paginate(24, ['*'], 'product_page');

            $productResult->appends($request->query());

            return $productResult;
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'result' => null
            ], 500);
        }
    }
    protected function list_best_seller()
    {
        try {
            $request = request();

            $query = ProductModel::with(['productSizeColor'])
                ->where('CodeCompany', $this->active_company)
                ->where('CShowInDevice', 1)
                ->whereHas('productSizeColor', function ($query) {
                    $query->havingRaw('SUM(Mande) > 0');
                });


            $query->orderByRaw('(SELECT SUM(Mande) FROM AV_KalaSizeColorMande_View WHERE AV_KalaSizeColorMande_View.CodeKala = AV_KalaList_View.Code) DESC');


            if ($search = $request->query('search')) {
                $search = str_replace('ی', 'ي', $search);
                $query->where('Name', 'LIKE', "%{$search}%");
            }
            if ($size = $request->query('size')) {
                $sizes = explode(',', $size);
                $query->whereHas('productSizeColor', function ($query) use ($sizes) {
                    $query->whereIn('SizeNum', $sizes);
                });
            }
            if ($color = $request->query('color')) {
                $colors = explode(',', $color);
                $query->whereHas('productSizeColor', function ($query) use ($colors) {
                    $query->whereIn('ColorCode', $colors);
                });
            }

            $imageCreation = $query->select([
                'Pic',
                'ImageCode',
                'created_at',
                'Code',
                'CChangePic',
            ])
                ->paginate(24, ['*'], 'product_page');

            DB::transaction(function () use ($imageCreation) {
                foreach ($imageCreation as $product) {
                    if ($product->CChangePic == 1 && !empty($product->Pic)) {
                        $createdAt = Carbon::parse($product->created_at);
                        $picName = "{$product->ImageCode}_{$createdAt->getTimestamp()}";

                        if ($this->createProductImages($product, $picName)) {
                            DB::table('KalaImage')
                                ->where('Code', $product->ImageCode)
                                ->update(['PicName' => $picName]);

                            DB::table('Kala')
                                ->where('Code', $product->Code)
                                ->update(['CChangePic' => 0]);
                        }
                    }
                }
            });


            $productResult = $query->select([
                'CodeCompany',
                'CanSelect',
                'GCode',
                'GName',
                'Comment',
                'SCode',
                'SName',
                'Code',
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
            ])
                ->paginate(24, ['*'], 'product_page');

            $productResult->appends($request->query());

            return $productResult;
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'result' => null
            ], 500);
        }
    }

    protected function list_colors($categoryCode, $mode)
    {
        try {

            $query = ProductModel::where('CodeCompany', $this->active_company)
                ->where('CShowInDevice', 1)
                ->join('AV_KalaSizeColorMande_View', 'AV_KalaSizeColorMande_View.CodeKala', '=', 'AV_KalaList_View.Code')
                ->where('AV_KalaSizeColorMande_View.ColorCode', '!=', null)
                ->where('AV_KalaSizeColorMande_View.ColorName', '!=', null)
                ->select('AV_KalaSizeColorMande_View.ColorCode', 'AV_KalaSizeColorMande_View.ColorName', 'AV_KalaSizeColorMande_View.RGB')
                ->orderBy('Code', 'DESC');

            if ($mode == 'category') {
                $query->where('GCode', $categoryCode);
            } elseif ($mode == 'subcategory') {
                $query->where('SCode', $categoryCode);
            } elseif ($mode == 'offers') {
                $query->where('CFestival', 1);
            }

            $products = $query->get();

            if ($products->isEmpty()) {
                return [];
            }

            $colors = [];
            foreach ($products as $product) {
                if (!is_null($product->ColorCode) && !is_null($product->ColorName)) {
                    $colors[] = [
                        'ColorCode' => $product->ColorCode,
                        'ColorName' => $product->ColorName,
                        'RGB' => $product->RGB
                    ];
                }
            }

            if (empty($colors)) {
                return [];
            }

            $uniqueColors = array_values(array_unique($colors, SORT_REGULAR));

            return $uniqueColors;
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'result' => null
            ], 500);
        }
    }

    protected function list_sizes($categoryCode, $mode)
    {
        try {
            $query = ProductModel::where('CodeCompany', $this->active_company)
                ->where('CShowInDevice', 1)
                ->join('AV_KalaSizeColorMande_View', 'AV_KalaSizeColorMande_View.CodeKala', '=', 'AV_KalaList_View.Code')
                ->where('AV_KalaSizeColorMande_View.SizeNum', '!=', null)
                ->select('AV_KalaSizeColorMande_View.SizeNum')
                ->orderBy('Code', 'DESC');

            if ($mode == 'category') {
                $query->where('GCode', $categoryCode);
            } elseif ($mode == 'subcategory') {
                $query->where('SCode', $categoryCode);
            } elseif ($mode == 'offers') {
                $query->where('CFestival', 1);
            }

            $products = $query->get();

            if ($products->isEmpty()) {
                return [];
            }

            $sizes = [];
            foreach ($products as $product) {
                if (!is_null($product->SizeNum)) {
                    $sizes[] = $product->SizeNum;
                }
            }

            if (empty($sizes)) {
                return [];
            }

            $uniqueSizes = array_values(array_unique($sizes, SORT_REGULAR));
            sort($uniqueSizes);
            return $uniqueSizes;
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'result' => null
            ], 500);
        }
    }

    protected function list_colors_best_seller($categoryCode, $mode)
    {
        try {

            $query = BestSellModel::where('CodeCompany', $this->active_company)
                ->where('CShowInDevice', 1)
                ->join('AV_KalaSizeColorMande_View', 'AV_KalaSizeColorMande_View.CodeKala', '=', 'AV_KalaTedadForooshKol_View.KCode')
                ->where('AV_KalaSizeColorMande_View.ColorCode', '!=', null)
                ->where('AV_KalaSizeColorMande_View.ColorName', '!=', null)
                ->select('AV_KalaSizeColorMande_View.ColorCode', 'AV_KalaSizeColorMande_View.ColorName', 'AV_KalaSizeColorMande_View.RGB')
                ->orderBy('KMegdar', 'DESC');



            $products = $query->get();

            if ($products->isEmpty()) {
                return [];
            }

            $colors = [];
            foreach ($products as $product) {
                if (!is_null($product->ColorCode) && !is_null($product->ColorName)) {
                    $colors[] = [
                        'ColorCode' => $product->ColorCode,
                        'ColorName' => $product->ColorName
                    ];
                }
            }

            if (empty($colors)) {
                return [];
            }

            $uniqueColors = array_values(array_unique($colors, SORT_REGULAR));

            return $uniqueColors;
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'result' => null
            ], 500);
        }
    }

    protected function list_sizes_best_seller($categoryCode, $mode)
    {
        try {
            $query = ProductModel::where('CodeCompany', $this->active_company)
                ->where('CShowInDevice', 1)
                ->join('AV_KalaSizeColorMande_View', 'AV_KalaSizeColorMande_View.CodeKala', '=', 'AV_KalaTedadForooshKol_View.KCode')
                ->where('AV_KalaSizeColorMande_View.SizeNum', '!=', null)
                ->select('AV_KalaSizeColorMande_View.SizeNum')
                ->orderBy('KMegdar', 'DESC');

            $products = $query->get();

            if ($products->isEmpty()) {
                return [];
            }

            $sizes = [];
            foreach ($products as $product) {
                if (!is_null($product->SizeNum)) {
                    $sizes[] = $product->SizeNum;
                }
            }

            if (empty($sizes)) {
                return [];
            }

            $uniqueSizes = array_values(array_unique($sizes, SORT_REGULAR));
            sort($uniqueSizes);
            return $uniqueSizes;
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'result' => null
            ], 500);
        }
    }

    protected function list_prices($resultProducts)
    {
        try {

            $products = $resultProducts->items();

            if (empty($products)) {
                return [
                    'min_price' => null,
                    'max_price' => null
                ];
            }

            $prices = array_filter(array_map(function ($product) {
                return $product->SPrice;
            }, $products), function ($price) {
                return !is_null($price);
            });

            if (empty($prices)) {
                return [
                    'min_price' => null,
                    'max_price' => null
                ];
            }

            return [
                'min_price' => min($prices),
                'max_price' => max($prices)
            ];
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'result' => null
            ], 500);
        }
    }

    public function index(Request $request, $Code)
    {
        try {
            $subcategoryPage = $request->query('subcategory_page', 1);
            $productPage = $request->query('product_page', 1);

            $subcategories = $this->list_subcategories($Code);
            if ($subcategories instanceof \Illuminate\Http\JsonResponse) {
                return $subcategories;
            }
            $subcategories->appends(['subcategory_page' => $subcategoryPage, 'product_page' => $productPage]);

            $categoryProducts = $this->list_category_products($Code);
            if ($categoryProducts instanceof \Illuminate\Http\JsonResponse) {
                return $categoryProducts;
            }
            $categoryProducts->appends(['subcategory_page' => $subcategoryPage, 'product_page' => $productPage]);

            $category = $this->fetchCategory($Code);
            if ($category instanceof \Illuminate\Http\JsonResponse) {
                return $category;
            }

            return response()->json([
                'result' => [
                    'subcategories' => $subcategories,
                    'category' => $category,
                    'products' => $categoryProducts,
                    'colors' => $this->list_colors($Code, 'category'),
                    'sizes' => $this->list_sizes($Code, 'category'),
                    'prices' => $this->list_prices($categoryProducts),
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

    public function listSubcategoryProducts(Request $request, $Code)
    {
        try {
            $productPage = $request->query('product_page', 1);

            $subcategoryProducts = $this->list_subcategory_products($Code);
            if ($subcategoryProducts instanceof \Illuminate\Http\JsonResponse) {
                return $subcategoryProducts;
            }
            $subcategoryProducts->appends(['product_page' => $productPage]);

            $subcategory = $this->fetchSubCategory($Code);
            if ($subcategory instanceof \Illuminate\Http\JsonResponse) {
                return $subcategory;
            }

            return response()->json([
                'result' => [
                    'subcategory' => $subcategory,
                    'products' => $subcategoryProducts,
                    'colors' => $this->list_colors($Code, 'subcategory'),
                    'sizes' => $this->list_sizes($Code, 'subcategory'),
                    'prices' => $this->list_prices($subcategoryProducts),
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

    public function listAllProducts(Request $request)
    {
        try {
            $productPage = $request->query('product_page', 1);

            $allProducts = $this->list_products();
            if ($allProducts instanceof \Illuminate\Http\JsonResponse) {
                return $allProducts;
            }
            $allProducts->appends(['product_page' => $productPage]);

            return response()->json([
                'result' => [
                    'products' => $allProducts,
                    'colors' => $this->list_colors('0', 'all'),
                    'sizes' => $this->list_sizes('0', 'all'),
                    'prices' => $this->list_prices($allProducts),
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
    public function listAllOffers(Request $request)
    {
        try {
            $productPage = $request->query('product_page', 1);

            $products = $this->list_offered_products();
            if ($products instanceof \Illuminate\Http\JsonResponse) {
                return $products;
            }
            $products->appends(['product_page' => $productPage]);

            return response()->json([
                'result' => [
                    'products' => $products,
                    'colors' => $this->list_colors('all', 'offers'),
                    'sizes' => $this->list_sizes('all', 'offers'),
                    'prices' => $this->list_prices($products),
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

    public function listBestSeller(Request $request)
    {
        try {
            $productPage = $request->query('product_page', 1);

            $products = $this->list_best_seller();
            if ($products instanceof \Illuminate\Http\JsonResponse) {
                return $products;
            }
            $products->appends(['product_page' => $productPage]);

            return response()->json([
                'result' => [
                    'products' => $products,
                    'colors' => $this->list_colors('0', 'all'),
                    'sizes' => $this->list_sizes('0', 'all'),
                    'prices' => $this->list_prices($products),
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
