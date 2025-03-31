<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderProductsModel extends Model
{
    use HasFactory;
    protected $table = "SOrderKala";
    protected $fillable = [
        'KCode',
        'SCode',
        'Tedad',
        'Fee',
        'KTedad',
        'KMegdar',
        'KFee',
        'DTakhfif',
        'MTakhfif'
    ];
}