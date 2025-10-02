<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CompanyService
{
    public function getActiveCompany(): ?string
    {
        return DB::table('Company')
            ->where('DeviceSelected', 1)
            ->value('Code');
    }

    public function getFinancialPeriod(string $companyCode): ?string
    {
        return DB::table('DoreMali')
            ->where('CodeCompany', $companyCode)
            ->where('DeviceSelected', 1)
            ->value('Code');
    }
}
