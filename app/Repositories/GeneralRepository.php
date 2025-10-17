<?php

namespace App\Repositories;

use Exception;
use Illuminate\Support\Facades\DB;

class GeneralRepository
{
    public function getCurrencyUnit()
    {
        return DB::table('UserSetting')->select('MVahed')->first()->MVahed ?? 'ریال';
    }

    public function getBankAccount()
    {
        $bank_account = DB::table('AV_ShomareHesab_VIEW')->where('Def', 1)->first();

        if (!$bank_account) {
            throw new Exception('حساب بانکی پیشفرض تنظیم نشده است');
        }
        return $bank_account;
    }
}
