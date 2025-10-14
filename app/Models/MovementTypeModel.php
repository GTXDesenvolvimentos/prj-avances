<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MovementTypeModel extends Model
{
    use HasFactory, SoftDeletes;

    // Nome da tabela
    protected $table = 'movement_type';

    // Campos que podem ser preenchidos em massa
    protected $fillable = [
        'id',
        'name',
        'description',
        'type',
        'company_id',
        'status',
    ];

    // Timestamps automáticos
    public $timestamps = true;

    // Datas que são tratadas como Carbon (necessário para SoftDeletes)
    protected $dates = ['deleted_at'];

    /**
     * Relações
     */

    // Empresa vinculada
    public function company()
    {
        return $this->belongsTo(CompanyModel::class, 'company_id');
    }
}
