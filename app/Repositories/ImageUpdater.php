<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class ImageUpdater
{
    public function productImagesUpdate($updates)
    {
        if (empty($updates)) {
            return;
        }
        DB::transaction(function () use ($updates) {
            foreach ($updates as $update) {
                DB::table('KalaImage')->where('Code', $update['Code'])->update(['PicName' => $update['PicName']]);
            }
        });
    }
}


