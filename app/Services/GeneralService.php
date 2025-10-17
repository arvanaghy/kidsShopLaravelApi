<?php

namespace App\Services;

use App\Models\DeviceHeaderImage;
use App\Repositories\GeneralRepository;
use App\Services\ImageServices\BannerImageService;
use App\Traits\Cacheable;
use Illuminate\Support\Facades\DB;

class GeneralService
{

    protected $active_company;
    protected $bannerImageService;
    private $cacheTime = 60 * 30;
    protected $generalRepository;

    use Cacheable;

    public function __construct(CompanyService $companyService, BannerImageService $bannerImageService, GeneralRepository $generalRepository)
    {
        $this->active_company = $companyService->getActiveCompany();
        $this->bannerImageService = $bannerImageService;
        $this->generalRepository = $generalRepository;
    }

    public function getCompanyInfo()
    {
        return $this->cacheQuery('company_info', $this->cacheTime, function () {
            return DB::table('Company')->where('DeviceSelected', 1)->first();
        });
    }

    public function getCurrencyUnit()
    {
        return $this->generalRepository->getCurrencyUnit();
    }

    public function fetchBanners()
    {
        return $this->cacheQuery('home_page_banners', $this->cacheTime, function () {
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

    public function listTransferServices()
    {
        return $this->cacheQuery('transfer_services', $this->cacheTime, function () {
            return DB::table('AV_KhadamatDevice_View')->where('CodeCompany', $this->active_company)->get();
        });
    }


    public function checkOnlinePaymentAvailable()
    {
        return $this->cacheQuery('online_payment_available', $this->cacheTime, function () {
            return $this->generalRepository->getBankAccount();
        });
    }

    public function aboutUs()
    {
        return $this->cacheQuery('about_us', $this->cacheTime, function () {
            return DB::table('DeviceAbout')->where('Type', 0)->orderBy('Radif', 'asc')->get();
        });
    }

    public function faq()
    {
        return $this->cacheQuery('faq', $this->cacheTime, function () {
            return DB::table('DeviceAbout')->where('Type', 1)->orderBy('Radif', 'asc')->get();
        });
    }
}
