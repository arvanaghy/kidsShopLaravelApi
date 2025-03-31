<?php

namespace App\Models;

use App\Models\SubCategoryModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryModel extends Model
{
    use HasFactory;
    protected $table = "AV_KalaGroupDevice_View";


    public function subcategories() : HasMany
    {
        return $this->hasMany(SubCategoryModel::class,'CodeGroup', 'Code');
    }

}
