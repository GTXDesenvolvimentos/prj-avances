<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryMovementsModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_movements';

    /**
     * Campos que podem ser preenchidos em massa
     */
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'movement_type',
        'rental_rental_id',
        'sale_sale_id',
        'quantity_movement',
        'quantity_total',
        'notes',
        'company_id',
        'status',
    ];

    /**
     * Ativa timestamps automáticos (created_at e updated_at)
     */
    public $timestamps = true;

    /**
     * Datas que são tratadas como Carbon (para SoftDeletes)
     */
    protected $dates = ['deleted_at'];

    /**
     * Relações
     */

    // Produto vinculado
    public function product()
    {
        return $this->belongsTo(ProductModel::class, 'product_id');
    }
    // Re/torno  de tipos de movimentos;
    public function movement_type()
    {
        return $this->belongsTo(MovementTypeModel::class, 'movement_type');
    }

    // Armazém vinculado
    public function warehouse()
    {
        return $this->belongsTo(WarehouseModel::class, 'warehouse_id');
    }

    // Empresa vinculada
    public function company()
    {
        return $this->belongsTo(CompanyModel::class, 'company_id');
    }

    // Aluguel (rental) vinculado — opcional
    public function rental()
    {
        return $this->belongsTo(Rental::class, 'rental_rental_id');
    }

    //Venda (sale) vinculada — opcional
    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_sale_id');
    }
 }
