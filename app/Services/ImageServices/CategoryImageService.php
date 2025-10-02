<?php

namespace App\Services\ImageServices;

use App\Services\ImageServices\ImageService;
use Illuminate\Support\Facades\File;

class CategoryImageService
{

    protected $imageService;
    protected function CreateCategoryImageDirectories()
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

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
        $this->CreateCategoryImageDirectories();
    }
    /**
     * Process a category image.
     *
     * @param mixed $data Image data object
     * @param string $picName Image name
     * @return bool
     */
    public function processCategoryImage($data, string $picName): bool
    {
        $imagePath = public_path("category-images/original/" . $picName . ".jpg");
        $webpPath = public_path("category-images/webp/" . $picName . ".webp");
        return $this->imageService->processImage(
            data: $data,
            imagePath: $imagePath,
            outputPath: $webpPath,
            resize: [1600, 1600],
            quality: 100
        );
    }

    /**
     * Remove a category image.
     *
     * @param mixed $data Image data object
     */
    public function removeCategoryImage($data): void
    {
        $path = public_path("category-images/webp/" . $data->PicName . ".webp");
        $this->imageService->removeImage($path);
    }
}
