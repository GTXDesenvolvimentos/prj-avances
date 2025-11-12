<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryModel extends Model
{
    use HasFactory;

    protected $table = 'inventories';

    protected $fillable = [
        'company_id',
        'product_id',
        'warehouse_id',
        'movement_type_id',
        'quantity_movement',
        'note'
    ];

    // 🔹 Produto
    public function product()
    {
        return $this->belongsTo(ProductModel::class, 'product_id');
    }

    // 🔹 Armazém
    public function warehouse()
    {
        return $this->belongsTo(WarehouseModel::class, 'warehouse_id');
    }

    // 🔹 Tipo de movimento (entrada / saída)
    public function movementType()
    {
        return $this->belongsTo(MovementTypeModel::class, 'type');
    }
}
