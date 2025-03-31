<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class WebPaymentModel extends Model
{
    use HasFactory;

    protected $table = "WebPayment";

    protected $fillable = [
        'id',
        'SCode',
        'CCode',
        'TrID',
        'UUID',
        'Comment',
        'Mablag',
        'updated_at',
        'created_at',
    ];
}
