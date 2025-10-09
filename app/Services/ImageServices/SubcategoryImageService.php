<?php

namespace App\Services\ImageServices;

use Illuminate\Support\Facades\File;

class SubcategoryImageService
{

    protected $imageService;
    protected function CreateSubcategoryImageDirectories()
    {
        $paths = [
            "subcategory-images",
            "subcategory-images/original",
            "subcategory-images/webp"
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
        $this->CreateSubcategoryImageDirectories();
    }
    /**
     * Process a subcategory image.
     *
     * @param mixed $data Image data object
     * @param string $picName Image name
     * @return bool
     */
    public function processSubcategoryImage($data, string $picName): bool
    {
        $imagePath = public_path("subcategory-images/original/" . $picName . ".jpg");
        $webpPath = public_path("subcategory-images/webp/" . $picName . ".webp");
        return $this->imageService->processImage(
            data: $data,
            imagePath: $imagePath,
            outputPath: $webpPath,
            resize: [1600, 1600],
            quality: 100
        );
    }

    /**
     * Remove a subcategory image.
     *
     * @param mixed $data Image data object
     */
    public function removeSubcategoryImage($data): void
    {
        if (empty($data->PicName)) {
            return;
        }
        $path = public_path("subcategory-images/webp/" . $data->PicName . ".webp");
        $this->imageService->removeImage($path);
    }
}
