<?php

namespace App\Services;

use App\Models\DeviceHeaderImage;
use App\Services\ImageServices\BannerImageService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GeneralService
{

    protected $active_company;
    protected $bannerImageService;

    public function __construct(CompanyService $companyService, BannerImageService $bannerImageService)
    {
        $this->active_company = $companyService->getActiveCompany();
        $this->bannerImageService = $bannerImageService;
    }

    public function getCompanyInfo()
    {
        return Cache::remember('company_info', 60 * 30, function () {
            return DB::table('Company')->where('DeviceSelected', 1)->first();
        });
    }

    public function getCurrencyUnit()
    {
        return DB::table('UserSetting')->select('MVahed')->first();
    }

    public function fetchBanners()
    {
        return Cache::remember('home_page_banners', 60 * 30, function () {
            $baseQuery = DeviceHeaderImage::where('CodeCompany', $this->active_company)
                ->orderBy('Code', 'DESC')
                ->limit(6);
            $results = $baseQuery->get();

            $updates = [];
            foreach ($results as $image) {
                if ($image->CChangePic == 1) {
                    $updateData = ['CChangePic' => 0];

                    if (!empty($image->PicName)) {
                        $this->bannerImageService->removeBannerImage($image);
                    }

                    if (!empty($image->Pic)) {
                        $picName = uniqid(ceil($image->Code) . '_', true);
                        $this->bannerImageService->processBannerImage($image, $picName);
                        $updateData['PicName'] = $picName;
                    } else {
                        $updateData['PicName'] = null;
                    }

                    $updates[$image->Code] = $updateData;
                }
            }

            if (!empty($updates)) {
                foreach ($updates as $code => $data) {
                    DeviceHeaderImage::where('Code', $code)->update($data);
                }
            }
            $results = $results->map(function ($item) {
                unset($item->Pic);
                return $item;
            });

            return $results;
        });
    }
}
