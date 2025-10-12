<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class GeneralRepository
{
    public function getCurrencyUnit()
    {
        return DB::table('UserSetting')->select('MVahed')->first()->MVahed ?? 'ریال';
    }
}
