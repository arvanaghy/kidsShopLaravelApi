<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class OrderRepository
{
    public function listPastOrders($financialPeriod, $companyCode, $customerCode)
    {
        return DB::table('AV_RFactorForoosh_VIEW')
            ->where('CodeDoreMali', $financialPeriod)
            ->where('CodeCompany', $companyCode)
            ->where('CCode', $customerCode)
            ->orderBy('Code', 'desc')
            ->paginate(12);
    }


    public function listPastOrdersProducts($financialPeriod, $orderCode, $customerCode)
    {
        return DB::table('AV_RFactorForooshKala_VIEW')
            ->where('CodeDoreMali', $financialPeriod)
            ->where('CodeFactorForoosh', $orderCode)
            ->where('CCode', $customerCode)
            ->paginate(12);
    }


    public function listUnverifiedOrders($financialPeriod, $customerCode)
    {
        return DB::table('AV_SOrder_View')
            ->where('CodeDoreMali', $financialPeriod)
            ->where('CCode', $customerCode)
            ->orderBy('Code', 'desc')
            ->paginate(12);
    }

    public function listUnverifiedOrdersProducts($orderCode, $customerCode)
    {
        return DB::table('AV_SOrderKala_View')
            ->where('SCode', $orderCode)
            ->where('CCode', $customerCode)
            ->paginate(12);
    }
}
