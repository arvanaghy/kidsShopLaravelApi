<?php

namespace App\Services\ImageServices;

use Illuminate\Support\Facades\File;

class BannerImageService
{

    protected function ensureBannerPaths(): void
    {
        $paths = [
            "banner-images",
            "banner-images/original",
            "banner-images/webp"
        ];

        foreach ($paths as $path) {
            $fullPath = public_path($path);
            if (!File::isDirectory($fullPath)) {
                File::makeDirectory($fullPath, 0755, true, true);
            }
        }
    }

    protected $imageService;
    public function __construct(ImageService $imageService)
    {
        $this->ensureBannerPaths();
        $this->imageService = $imageService;
    }

    public function processBannerImage($data, string $picName): void
    {
        $sizes = [
            'desktop' => [1360, 786],
            'mobile' => [390, 844]
        ];

        foreach ($sizes as $type => $size) {
            $imagePath = public_path("banner-images/original/{$picName}_{$type}.jpg");
            $webpPath = public_path("banner-images/webp/{$picName}_{$type}.webp");
            $this->imageService->processImage(
                data: $data,
                imagePath: $imagePath,
                outputPath: $webpPath,
                resize: [$size[0], $size[1]],
                quality: 100
            );
        }
    }

    public function removeBannerImage($data)
    {
        if (empty($data->PicName)) {
            return;
        }
        $webpPathDesktop = public_path("banner-images/webp/" . $data->PicName . "_desktop.webp");
        $webpPathMobile = public_path("banner-images/webp/" . $data->PicName . "_mobile.webp");

        $this->imageService->removeImage($webpPathDesktop);
        $this->imageService->removeImage($webpPathMobile);
    }
}
