<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductModel extends Model
{
    use HasFactory;
    protected $table = "AV_KalaList_View";

    public $timestamps = false;


    public function productSizeColor(): HasMany
    {
        return $this->hasMany(ProductSizeColorModel::class, 'CodeKala', 'Code');
    }
    public function productImages(): HasMany
    {
        return $this->hasMany(ProductSizeColorModel::class, 'CodeKala', 'Code');
    }

}
