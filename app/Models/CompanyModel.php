<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyModel extends Model
{
    use HasFactory, SoftDeletes;

    // Nome da tabela
    protected $table = 'companies';

    // Campos que podem ser preenchidos em massa
    protected $fillable = [
        'id',
        'address_id',
        'name',
        'tax_id',
        'phone',
        'status',
    ];

    // Datas que são tratadas como Carbon (necessário para SoftDeletes)
    protected $dates = ['deleted_at'];

    /**
     * Relações (exemplos, podem ser ajustadas conforme necessidade)
     */

    // Endereço da empresa
    // public function address()
    // {
    //     return $this->belongsTo(Address::class, 'address_id');
    // }

    // // Produtos da empresa (exemplo de relação, se existir)
    // public function products()
    // {
    //     return $this->hasMany(ProductModel::class, 'company_id');
    // }
}
