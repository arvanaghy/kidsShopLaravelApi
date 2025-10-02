<?php

namespace App\Services\ImageServices;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
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

    /**
     * Process and save product image as WebP.
     */
    public function processSingleProductImage($product, $image, string $pic_name): bool
    {
        $imagePath = public_path("products-image/original/{$product->GCode}/{$product->SCode}/{$pic_name}.jpg");
        $webpPath = public_path("products-image/webp/{$product->GCode}/{$product->SCode}/{$pic_name}.webp");

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

    /**
     * Update product images if needed.
     */
    public function updateProductImages($images): void
    {
        foreach ($images as $image) {
            if (data_get($image, 'CChangePic') && !empty($image->Pic)) {
                $picName = ceil($image->ImageCode) . '_' . Carbon::parse($image->created_at)->timestamp;
                // if ($this->processProductImage($image, $picName)) {
                //     DB::transaction(function () use ($image, $picName) {
                //         DB::table('KalaImage')->where('Code', $image->ImageCode)->update(['PicName' => $picName]);
                //         DB::table('Kala')->where('Code', $image->Code)->update(['CChangePic' => 0]);
                //     });
                // }
            }
        }
    }
}
