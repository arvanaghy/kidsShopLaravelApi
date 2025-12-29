<?php

namespace App\Services\ImageServices;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManagerStatic as Image;

class ImageService
{
    /**
     * Process and save an image with specified settings.
     *
     * @param mixed $data Image data object with Pic property
     * @param string $picName Image name
     * @param string $basePath Base directory (e.g., 'products-image', 'category-images')
     * @param array $resize Resize dimensions [width, height]
     * @param int $quality Image quality (0-100)
     * @return bool
     */
    public function processImage($data, string $imagePath, string $outputPath, array $resize = [1200, 1600], int $quality = 100): bool
    {
        try {
            if (empty($data->Pic)) {
                return false;
            }
            File::put($imagePath, $data->Pic);

            Image::configure(['driver' => 'gd']);
            Image::make($imagePath)
                ->resize($resize[0], $resize[1], function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })
                ->encode('webp', $quality)
                ->save($outputPath);

            File::delete($imagePath);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to process image: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Remove an image from storage.
     *
     * @param mixed $data Image data object
     * @param string $path Base directory
     */
    public function removeImage(string $path): void
    {
        if (File::exists($path)) {
            File::delete($path);
        }
    }

    public function processImageFromResource($imageResource, string $imagePath, string $outputPath, array $resize = [1200, 1600], int $quality = 100): bool
    {
        try {
            if (empty($imageResource)) {
                return false;
            }

            File::put($imagePath, $imageResource);

            Image::configure(['driver' => 'gd']);
            Image::make($imagePath)
                ->resize($resize[0], $resize[1], function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })
                ->encode('webp', $quality)
                ->save($outputPath);

            File::delete($imagePath);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to process image from resource: {$e->getMessage()}");
            return false;
        }
    }


    public function processWebImage($imageFile, string $imagePath, string $outputPath, array $resize = [1200, 1600], int $quality = 100): bool
    {
        try {
            if (empty($imageFile)) {
                return false;
            }
            File::put($imagePath, $imageFile);

            Image::configure(['driver' => 'gd']);
            Image::make($imagePath)
                ->resize($resize[0], $resize[1], function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })
                ->encode('webp', $quality)
                ->save($outputPath);

            File::delete($imagePath);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to process image: {$e->getMessage()}");
            return false;
        }
    }
}
