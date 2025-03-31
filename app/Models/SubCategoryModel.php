<?php

namespace App\Models;

use App\Models\CategoryModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class SubCategoryModel extends Model
{
    use HasFactory;
    protected $table = "AV_KalaSubGroupDevice_View";

    public function category(): BelongsTo
    {
        return $this->belongsTo(CategoryModel::class, 'code', 'CodeGroup');
    }
}
