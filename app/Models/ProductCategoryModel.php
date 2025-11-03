<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // 👈 Importante!

class ProductCategoryModel extends Model
{
    use HasFactory, SoftDeletes; // 👈 Adiciona SoftDeletes

    protected $table = 'product_categories';

    protected $fillable = [
        'id',
        'category',
        'description',
        'status',
        'company_id',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    // 👇 Opcional (útil para clareza e compatibilidade com versões antigas do Laravel)
    protected $dates = ['deleted_at'];
}
