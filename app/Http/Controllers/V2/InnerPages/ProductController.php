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
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManagerStatic as Image;

class ProductController extends Controller
{
    protected $active_company;
    protected $financial_period;

    /**
     * Initialize directories for product images.
     */
    protected function ensureProductPaths(): void
    {
        $paths = [
            public_path('products-image'),
            public_path('products-image/original'),
            public_path('products-image/webp'),
        ];

        foreach ($paths as $path) {
            if (!File::isDirectory($path)) {
                File::makeDirectory($path, 0755, true);
            }
        }
    }

    /**
     * Constructor to set up active company and financial period.
     */
    public function __construct()
    {
        try {
            $this->ensureProductPaths();

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
            abort(500, $e->getMessage());
        }
    }

    /**
     * Process and save product image as WebP.
     */
    protected function processProductImage($data, string $picName): bool
    {
        try {
            $imagePath = public_path("products-image/original/{$picName}.jpg");
            $webpPath = public_path("products-image/webp/{$picName}.webp");

            File::put($imagePath, $data->Pic);

            Image::configure(['driver' => 'gd']);
            Image::make($imagePath)
                ->resize(250, 250, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })
                ->encode('webp', 90)
                ->save($webpPath);

            File::delete($imagePath);

            return true;
        } catch (Exception $e) {
            Log::error("Failed to process product image: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Remove unused images from storage.
     */
    protected function cleanupUnusedImages($product, $productImages): void
    {
        try {
            $webpDir = public_path("products-image/webp/{$product->GCode}/{$product->SCode}");
            $validPicNames = $productImages->pluck('PicName')
                ->filter()
                ->map(fn($name) => "{$name}.webp")
                ->toArray();

            if (File::isDirectory($webpDir)) {
                collect(File::files($webpDir))
                    ->filter(fn($file) => !in_array($file->getFilename(), $validPicNames))
                    ->each(fn($file) => File::delete($file->getPathname()));
            }
        } catch (Exception $e) {
            Log::warning("Failed to cleanup images: {$e->getMessage()}");
        }
    }

    /**
     * Update product images if needed.
     */
    protected function updateProductImages($images): void
    {
        foreach ($images as $image) {
            if (data_get($image, 'CChangePic') && !empty($image->Pic)) {
                $picName = ceil($image->ImageCode) . '_' . Carbon::parse($image->created_at)->timestamp;
                if ($this->processProductImage($image, $picName)) {
                    DB::transaction(function () use ($image, $picName) {
                        DB::table('KalaImage')->where('Code', $image->ImageCode)->update(['PicName' => $picName]);
                        DB::table('Kala')->where('Code', $image->Code)->update(['CChangePic' => 0]);
                    });
                }
            }
        }
    }

    /**
     * Base query for products.
     */
    protected function baseProductQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return ProductModel::where('CodeCompany', $this->active_company)
            ->where('CShowInDevice', 1)
            ->select([
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
            ]);
    }

    /**
     * Get related products, excluding the specified product code.
     */
    public function relatedProducts($GCode, $SCode, $excludeCode = null)
    {
        try {
            $imageQuery = ProductModel::where('CodeCompany', $this->active_company)
                ->where('GCode', $GCode)
                ->where('SCode', $SCode)
                ->where('CShowInDevice', 1)
                ->when($excludeCode, fn($query) => $query->where('Code', '!=', $excludeCode))
                ->select(['Pic', 'ImageCode', 'created_at', 'GCode', 'SCode', 'Code', 'CChangePic', 'PicName'])
                ->orderBy('UCode', 'ASC')
                ->limit(16);

            $this->updateProductImages($imageQuery->get());

            return $this->baseProductQuery()
                ->with(['productSizeColor'])
                ->where('GCode', $GCode)
                ->where('SCode', $SCode)
                ->when($excludeCode, fn($query) => $query->where('Code', '!=', $excludeCode))
                ->orderBy('UCode', 'ASC')
                ->limit(16)
                ->get();
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage(), 'result' => null], 503);
        }
    }

    /**
     * Get offered (festival) products.
     */
    protected function offeredProducts()
    {
        try {
            $imageQuery = ProductModel::where('CodeCompany', $this->active_company)
                ->where('CShowInDevice', 1)
                ->where('CFestival', 1)
                ->select(['Pic', 'ImageCode', 'created_at', 'GCode', 'SCode', 'Code', 'CChangePic', 'PicName'])
                ->orderBy('UCode', 'ASC')
                ->limit(16);

            $this->updateProductImages($imageQuery->get());

            return $this->baseProductQuery()
                ->with(['productSizeColor'])
                ->where('CFestival', 1)
                ->orderBy('UCode', 'ASC')
                ->limit(16)
                ->get();
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage(), 'result' => null], 503);
        }
    }

    /**
     * Show a single product with related and offered products.
     */
    public function showProduct(Request $request, $code)
    {
        try {
            $product = ProductModel::where('Code', $code)->firstOrFail();
            $productImages = ProductImagesModel::where('CodeKala', $product->Code)->get();

            foreach ($productImages as $image) {
                if (!empty($image->Pic) && empty($image->PicName)) {
                    $picName = ceil($image->Code) . '_' . Carbon::parse($image->created_at)->timestamp;
                    if ($this->processProductImage($image, $picName)) {
                        DB::table('KalaImage')->where('Code', $image->Code)->update(['PicName' => $picName]);
                    }
                }
            }

            DB::table('Kala')->where('Code', $product->Code)->update(['CChangePic' => 0]);

            $result = ProductModel::with([
                'productSizeColor',
                'productImages' => fn($query) => $query->select('Code', 'PicName', 'Def', 'CodeKala')
            ])
                ->where('Code', $code)
                ->select([
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
                    'KVahed'
                ])
                ->first();

            return response()->json([
                'product' => $result,
                'relatedProducts' => $this->relatedProducts($result->GCode, $result->SCode, $result->Code),
                'offeredProducts' => $this->offeredProducts(),
                'message' => trans('messages.product_displayed_successfully')
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * List all products with pagination
     */
    public function listAllProducts(Request $request)
    {
        try {
            $productsQuery = $this->baseProductQuery();

            if ($sortPrice = $request->query('sortPrice')) {
                $productsQuery->orderBy('SPrice', $sortPrice);
            }

            if ($search = $request->query('search')) {
                $productsQuery->where('Name', 'LIKE', "%{$search}%");
            }

            $imageProducts = $productsQuery->clone()
                ->select(['Pic', 'ImageCode', 'created_at', 'CodeK', 'GCode', 'SCode', 'PicName'])
                ->paginate(8);

            $this->updateProductImages($imageProducts);

            $products = $productsQuery->with(['productSizeColor'])
                ->paginate(8, ['*'], 'page');

            return response()->json([
                'products' => $products,
                'message' => trans('messages.products_listed_successfully')
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
