<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // 👈 Importante!
use App\Models\CompanyModel;

class WarehouseModel extends Model
{
    use HasFactory, SoftDeletes; // 👈 Adiciona SoftDeletes

    protected $table = 'warehouses'; // 👈 Nome da tabela

    protected $fillable = [
        'id',
        'address_id',
        'name',
        'note',
        'company_id',
        'status',
    ];

    // 👇 Opcional (útil para clareza e compatibilidade com versões antigas do Laravel)
    protected $dates = ['deleted_at'];

    // Empresa vinculada
    public function company()
    {
        return $this->belongsTo(CompanyModel::class, 'company_id');
    }
 }


