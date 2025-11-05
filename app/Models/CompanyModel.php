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
        'company_name',
        'tax_id',
        'phone',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    // Datas que são tratadas como Carbon (necessário para SoftDeletes)
    protected $dates = ['deleted_at'];

    /**
     * Eventos automáticos no Model
     */
    protected static function booted()
    {
        static::saving(function ($model) {
            // Converte tax_id vazio para NULL
            if ($model->tax_id === '') {
                $model->tax_id = null;
            }
        });
    }
  
}
