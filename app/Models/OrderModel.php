<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderModel extends Model
{
    use HasFactory;

    protected $table = "SOrder";

    protected $primaryKey = 'Code';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = ['Code', 'CCode', 'CodeDoreMali', 'CodeKhadamat', 'MKhadamat', 'Comment', 'status'];
}
