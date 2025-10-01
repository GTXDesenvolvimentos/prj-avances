<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCategoryModel extends Model
{
    use HasFactory;

    protected $table = 'product_categories';

    protected $fillable = [
        'id',
        'name',
        'description',
        'status',
        'company_id'
    ];
}
