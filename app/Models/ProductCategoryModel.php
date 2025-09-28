<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCategoryModel extends Model
{
    use HasFactory;

    protected $table = 'product_categories';

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'status'
    ];
}
