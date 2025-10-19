<?php

namespace App\Services;

use App\Repositories\CompanyRepository;
use App\Traits\Cacheable;

class CompanyService
{
    use Cacheable;

    private $companyRepository;
    private $ttl = 60 * 30;

    public function __construct(CompanyRepository $companyRepository)
    {
        $this->companyRepository = $companyRepository;
    }

    public function getActiveCompany()
    {
        return $this->cacheQuery('kidsShopRedis_active_company', $this->ttl, function () {
            return $this->companyRepository->getActiveCompanyCode();
        });
    }

    public function getFinancialPeriod($companyCode)
    {
        return $this->cacheQuery('kidsShopRedis_financial_period_' . $companyCode, $this->ttl, function () use ($companyCode) {
            return $this->companyRepository->getFinancialPeriodCode($companyCode);
        });
    }
}
