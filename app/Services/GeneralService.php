<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class GeneralService
{


    public function getCompanyInfo()
    {
        return DB::table('Company')->where('DeviceSelected', 1)->first();
    }

    public function getCurrencyUnit()
    {
        return DB::table('UserSetting')->select('MVahed')->first();
    }
}
