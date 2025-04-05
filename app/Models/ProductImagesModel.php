<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductImagesModel extends Model
{
    use HasFactory;

    protected $table = "KalaImage";

    public $timestamps = false;

    protected $fillable = [
        'CodeKala',
        'PicName',
        'Pic',
        'Def'
    ];
}
