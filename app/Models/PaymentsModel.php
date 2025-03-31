<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentsModel extends Model
{
    use HasFactory;

    protected $table = "DaryaftPardakht";

    protected $fillable = [
        'Code',
        'CodeCompany',
        'CodeDoreMali',
        'Index1',
        'Index2',
        'SIndex1',
        'SIndex2',
        'Code1',
        'Code2',
        'SDaryaft',
        'SPardakht',
        'BCode',
        'SDate',
        'Mablag',
        'Babat',
        'CodeRCheck',
        'IsOwner',
        'CodeHazineDP',
        'MHazineDP'
    ];
}