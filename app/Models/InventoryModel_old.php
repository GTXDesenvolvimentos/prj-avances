<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_movements';

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'movement_type_id', // 🔹 Corrigido o nome do campo de relação
        'rental_rental_id',
        'sale_sale_id',
        'quantity_movement',
        'quantity_total',
        'notes',
        'company_id',
        'status',
    ];

    public $timestamps = true;

    protected $dates = ['deleted_at'];

    /**
     * ======================
     * 🔹 RELACIONAMENTOS
     * ======================
     */

    // Produto vinculado
    public function product()
    {
        return $this->belongsTo(ProductModel::class, 'product_id')->withTrashed();
    }

    // Tipo de movimento (entrada, saída, etc.)
    public function movementType()
    {
        return $this->belongsTo(MovementTypeModel::class, 'movement_type_id')->withTrashed();
    }

    // Armazém vinculado
    public function warehouse()
    {
        return $this->belongsTo(WarehouseModel::class, 'warehouse_id')->withTrashed();
    }

    // Empresa vinculada
    public function company()
    {
        return $this->belongsTo(CompanyModel::class, 'company_id')->withTrashed();
    }

    
    public function category()
    {
        return $this->belongsTo(ProductCategoryModel::class, 'category_id')->withTrashed();
    }

    public function unit()
    {
        return $this->belongsTo(ProductUnitsModel::class, 'unit_id')->withTrashed();
    }

    public function movement_type()
    {
        return $this->belongsTo(MovementTypeModel::class, 'movement_type_id')->withTrashed();
    }

}
