<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSizeColorModel extends Model
{
    use HasFactory;
    protected $table = "AV_KalaSizeColorMande_View";

    public $timestamps = false;

    protected $fillable = [
        'CSCode',
        'CodeKala',
        'SizeNum',
        'ColorCode',
        'ColorName',
        'Mande',
        'Mablag'
    ];
}
