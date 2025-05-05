<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BestSellModel extends Model
{
    use HasFactory;
    protected $table = "AV_KalaTedadForooshKol_View";

    public $timestamps = false;


    public function productSizeColor(): HasMany
    {
        return $this->hasMany(ProductSizeColorModel::class, 'CodeKala', 'KCode');
    }
    public function productImages(): HasMany
    {
        return $this->hasMany(ProductImagesModel::class, 'CodeKala', 'KCode');
    }
}
