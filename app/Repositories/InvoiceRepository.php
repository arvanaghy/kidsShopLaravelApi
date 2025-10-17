<?php

namespace App\Repositories;

use App\Models\InvoiceModel;
use App\Services\CompanyService;
use App\Traits\Cacheable;
use Exception;
use Illuminate\Support\Facades\DB;

class InvoiceRepository
{

    use Cacheable;

    protected $active_company;
    protected $cacheTime = 60 * 60 * 24 * 30;

    public function __construct(
        CompanyService $companyService,
    ) {
        $this->active_company = $companyService->getActiveCompany();
    }
    public function getAccountBalance($financialPeriod, $customerCode)
    {
        return DB::table('GHesab')
            ->where('CodeDoreMali', $financialPeriod)
            ->where('CodeCustomer', $customerCode)
            ->sum('MCustomer');
    }


    public function listPastInvoices($financialPeriod, $customerCode)
    {
        return InvoiceModel::where('CodeDoreMali', $financialPeriod)
            ->where('CodeCustomer', $customerCode)
            ->orderBy('Code', 'desc')
            ->paginate(12);
    }
}
