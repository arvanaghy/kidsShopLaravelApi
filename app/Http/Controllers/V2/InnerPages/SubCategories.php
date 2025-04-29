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
        Image::make($imagePath)->encode('webp', 100)->resize(250, 250)->save($webpPath);

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
                ->where('CShowInDevice', 1);

            $sortPrice = strtolower($request->query('sort_price'));
            if ($request->has('sort_price') && in_array($sortPrice, ['asc', 'desc'])) {
                $query->orderBy('SPrice', $sortPrice);
            } else {
                $query->orderBy('Code', 'DESC');
            }

            if ($search = $request->query('search') && $request->query('search') != '') {
                $query->where('Name', 'LIKE', "%{$search}%");
            }
            if ($size = $request->query('size') && $request->query('size') != '') {
                $sizes = explode(',', $size);
                $query->whereHas('productSizeColor', function ($query) use ($sizes) {
                    $query->whereIn('SizeNum', $sizes);
                });
            }
            if ($color = $request->query('color') && $request->query('color') != '') {
                $colors = explode(',', $color);
                $query->whereHas('productSizeColor', function ($query) use ($colors) {
                    $query->whereIn('ColorCode', $colors);
                });
            }
            $imageCreation = $query->select([
                'Pic',
                'ImageCode',
                'created_at',
                'GCode',
                'SCode',
                'Code',
                'CChangePic',
                'PicName'
            ])
                ->paginate(24, ['*'], 'product_page');

            foreach ($imageCreation as $image) {
                if ($image->CChangePic == 1 && !empty($image->Pic)) {
                    $createdAt = Carbon::parse($image->created_at);
                    $picName = ceil($image->ImageCode) . "_" . $createdAt->getTimestamp();
                    $this->CreateProductImages($image, $picName);
                    DB::table('KalaImage')->where('Code', $image->ImageCode)->update(['PicName' => $picName]);
                    DB::table('Kala')->where('Code', $image->Code)->update(['CChangePic' => 0]);
                }
            }

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
                ->orderBy('Code', 'DESC');

            if ($sortPrice = $request->query('sort_price')) {
                $query->orderBy('SPrice', $sortPrice);
            }

            if ($search = $request->query('search')) {
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
                'GCode',
                'SCode',
                'Code',
                'CChangePic',
                'PicName'
            ])
                ->paginate(24, ['*'], 'product_page');

            foreach ($imageCreation as $image) {
                if ($image->CChangePic == 1 && !empty($image->Pic)) {
                    $createdAt = Carbon::parse($image->created_at);
                    $picName = ceil($image->ImageCode) . "_" . $createdAt->getTimestamp();
                    $this->CreateProductImages($image, $picName);
                    DB::table('KalaImage')->where('Code', $image->ImageCode)->update(['PicName' => $picName]);
                    DB::table('Kala')->where('Code', $image->Code)->update(['CChangePic' => 0]);
                }
            }

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

    protected function list_colors($categoryCode)
    {
        try {
            $products = ProductModel::with(['productSizeColor'])
                ->where('CodeCompany', $this->active_company)
                ->where('GCode', $categoryCode)
                ->where('CShowInDevice', 1)
                ->orderBy('Code', 'DESC')
                ->get();

            if (empty($products)) {
                return [];
            }

            $colors = [];
            foreach ($products as $product) {
                if ($product->productSizeColor && $product->productSizeColor->isNotEmpty()) {
                    foreach ($product->productSizeColor as $sizeColor) {
                        if (!is_null($sizeColor->ColorCode) && !is_null($sizeColor->ColorName)) {
                            $colors[] = [
                                'ColorCode' => $sizeColor->ColorCode,
                                'ColorName' => $sizeColor->ColorName
                            ];
                        }
                    }
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

    protected function list_sizes($categoryCode)
    {
        try {
            $products = ProductModel::with(['productSizeColor'])
                ->where('CodeCompany', $this->active_company)
                ->where('GCode', $categoryCode)
                ->where('CShowInDevice', 1)
                ->orderBy('Code', 'DESC')
                ->get();

            if (empty($products)) {
                return [];
            }

            $sizes = [];
            foreach ($products as $product) {
                if ($product->productSizeColor && $product->productSizeColor->isNotEmpty()) {
                    foreach ($product->productSizeColor as $sizeColor) {
                        if (!is_null($sizeColor->SizeNum)) {
                            $sizes[] = $sizeColor->SizeNum;
                        }
                    }
                }
            }

            if (empty($sizes)) {
                return [];
            }

            $uniqueSizes = array_values(array_unique($sizes));

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

    protected function list_offers($categoryCode)
    {
        try {
            $query = ProductModel::with(['productSizeColor'])->where('CodeCompany', $this->active_company)
                ->where('GCode', $categoryCode)
                ->where('CShowInDevice', 1)
                ->where('CFestival', 1)
                ->orderBy('Code', 'DESC');
            $imageCreation = $query->select([
                'Pic',
                'ImageCode',
                'created_at',
                'GCode',
                'SCode',
                'Code',
                'CChangePic',
                'PicName'
            ])->Limit(16)->get();
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

            $offersResult = $query->select([
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
            ])->limit(16)->get();
            return  $offersResult;
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'result' => null
            ]);
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
                    'colors' => $this->list_colors($Code),
                    'sizes' => $this->list_sizes($Code),
                    'prices' => $this->list_prices($categoryProducts),
                    'offers' => $this->list_offers($Code),
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
                    'colors' => $this->list_colors($subcategoryProducts),
                    'sizes' => $this->list_sizes($subcategoryProducts),
                    'prices' => $this->list_prices($subcategoryProducts),
                    'offers' => $this->list_offers($Code),
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
