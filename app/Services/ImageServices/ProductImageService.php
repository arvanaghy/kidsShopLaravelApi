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
}
