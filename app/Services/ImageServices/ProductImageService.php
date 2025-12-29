<?php

namespace App\Services\ImageServices;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ProductImageService
{
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

    protected $imageService;
    public function __construct(ImageService $imageService)
    {
        $this->ensureProductPaths();
        $this->imageService = $imageService;
    }


    protected function createProductImagePath($GCode, $SCode): void
    {
        $paths = [
            public_path('products-image/original/' . $GCode),
            public_path('products-image/original/' . $GCode . '/' . $SCode),
            public_path('products-image/webp/' . $GCode),
            public_path('products-image/webp/' . $GCode . '/' . $SCode),
        ];

        foreach ($paths as $path) {
            if (!File::isDirectory($path)) {
                File::makeDirectory($path, 0755, true);
            }
        }
    }
    protected function createProductImagePathMaker($product_code): void
    {
        $paths = [
            public_path('web-products-image'),
            public_path('web-products-image/original'),
            public_path('web-products-image/webp'),
            public_path('web-products-image/original/' . $product_code),
            public_path('web-products-image/webp/' . $product_code),
        ];

        foreach ($paths as $path) {
            if (!File::isDirectory($path)) {
                File::makeDirectory($path, 0755, true);
            }
        }
    }

    /**
     * Process and save product image as WebP.
     */
    public function processProductImage($product, $image, string $pic_name): bool
    {
        $gCode = is_array($product) ? $product['GCode'] : $product->GCode;
        $sCode = is_array($product) ? $product['SCode'] : $product->SCode;
        $imagePath = public_path("products-image/original/{$gCode}/{$sCode}/{$pic_name}.jpg");
        $webpPath = public_path("products-image/webp/{$gCode}/{$sCode}/{$pic_name}.webp");

        $this->createProductImagePath($gCode, $sCode);

        return $this->imageService->processImage(
            data: $image,
            imagePath: $imagePath,
            outputPath: $webpPath,
            resize: [1200, 1600],
            quality: 100,
        );
    }
    public function processProductImageMaker($product_code, $image, string $pic_name): bool
    {
        $imagePath = public_path("web-products-image/original/{$product_code}/{$pic_name}.jpg");
        $webpPath = public_path("web-products-image/webp/{$product_code}/{$pic_name}.webp");

        $this->createProductImagePathMaker($product_code);

        return $this->imageService->processImage(
            data: $image,
            imagePath: $imagePath,
            outputPath: $webpPath,
            resize: [1200, 1200],
            quality: 100,
        );
    }

    public function processWebProductImageMaker($product_code, $imageData, string $pic_name): bool
    {
        $imagePath = public_path("web-products-image/original/{$product_code}/{$pic_name}.jpg");
        $webpPath = public_path("web-products-image/webp/{$product_code}/{$pic_name}.webp");

        $this->createProductImagePathMaker($product_code);

        if (is_string($imageData) && base64_decode($imageData, true) !== false) {
            return $this->imageService->processWebImage(
                imageFile: $imageData,
                imagePath: $imagePath,
                outputPath: $webpPath,
                resize: [1200, 1200],
                quality: 100,
            );
        } else {
            return $this->imageService->processImageFromResource(
                imageResource: $imageData,
                imagePath: $imagePath,
                outputPath: $webpPath,
                resize: [1200, 1200],
                quality: 100,
            );
        }
    }


    /**
     * Remove unused images from storage.
     */
    public function cleanupUnusedImages($product, $productImages): void
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



    public function deleteProductImage($productCode, $pic_name)
    {
        $path = env('APP_URL', 'https://api.kidsshop110.ir') . '/web-products-image/webp/' . $productCode . '/';
        $purePicName = str_replace($path, '', $pic_name);

        $imagePath = public_path('web-products-image/webp/' . $productCode . '/' . $purePicName);
        if (File::exists($imagePath)) {
            return File::delete($imagePath);
        }
    }
}
