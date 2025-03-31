<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerGroupModel extends Model
{
    use HasFactory;
    protected $table = "CustomerGroup";
    protected $fillable = [
        'Code',
        'CodeCompany',
        'Name',
        'Kharidar',
        'Forooshande',
        'Personel',
        'Tankhah',
        'Owner',
        'BazarYab',
        'Peymankar'
    ];
}
