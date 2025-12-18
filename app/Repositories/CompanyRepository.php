<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class CompanyRepository
{
    public function getActiveCompanyCode(): ?string
    {
        return DB::table('Company')
            ->where('DeviceSelected', 1)
            ->value('Code');
    }

    public function getFinancialPeriodCode(string $companyCode): ?string
    {
        return DB::table('DoreMali')
            ->where('CodeCompany', $companyCode)
            ->where('DeviceSelected', 1)
            ->value('Code');
    }
}
