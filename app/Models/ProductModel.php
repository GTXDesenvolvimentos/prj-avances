<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'products';
    protected $fillable = [
        'id',
        'category_id',
        'unit_id',
        'company_id',
        'product_code',
        'name',
        'description',
        'availability',
        'average_cost',
        'sale_price',
        'rental_price',
        'is_dynamic_sale_price',
        'is_dynamic_rental_price',
        'status',
        'created_at',
        'updated_at',
        'deleted_at'
    ];
    protected $casts = [
        'is_dynamic_sale_price' => 'boolean',
        'is_dynamic_rental_price' => 'boolean',
    ];
    protected $dates = ['deleted_at'];

    // Add these relationships
    public function category()
    {
        return $this->belongsTo(ProductCategoryModel::class, 'category_id')->withTrashed();
    }

    public function unit()
    {
        return $this->belongsTo(ProductUnitsModel::class, 'unit_id')->withTrashed();
    }



}