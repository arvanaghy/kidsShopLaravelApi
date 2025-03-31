<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerModel extends Model
{
    use HasFactory;
    protected $table = "Customer";
    protected $fillable = [
        'CodeCompany',
        'CodeGroup',
        'CodeCustomer',
        'PayerType',
        'Name',
        'Mobile',
        'Etebar',
        'EtebarCheck',
        'CityCode',
        'Kharidar',
        'Forooshande',
        'Personel',
        'Tankhah',
        'Owner',
        'BazarYab',
        'Peymankar',
        'ForooshType',
        'DForoosh',
        'PSahm',
        'BPercent',
        'BKalaPercent',
        'DENUSLat',
        'DENUSLong',
        'CLocationOn',
        'Act',
        'CShowInDevice',
        'VCar',
        'SMSCode',
        'SMSTime',
        'UToken',
        'VerifiedAT',
        'Address',
    ];


    protected $casts = [
        'SMSTime' => 'datetime',
    ];
}
