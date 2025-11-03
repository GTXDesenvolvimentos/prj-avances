<?php
/**
 * @OA\Schema(
 *     schema="ProductUnit",
 *     type="object",
 *     title="Product Unit",
 *     description="Representação de uma unidade de medida",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="symbol", type="string", example="kg"),
 *     @OA\Property(property="description", type="string", example="Kilograma"),
 *     @OA\Property(property="company_id", type="integer", example=10),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */

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
        'product_name',
        'description',
        'availability',
        'average_cost',
        'sale_price',
        'rental_price',
        'is_dynamic_sale_price',
        'is_dynamic_rental_price',
        'status',
        'created_by',
        'updated_by',
        'deleted_by'
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