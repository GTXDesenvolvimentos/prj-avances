<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class ProductUnitsModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_units';

    protected $fillable = [
        'id',
        'symbol',
        'description',
        'status',
        'company_id',
        'created_by',
        'updated_by'
    ];

    // 👇 Opcional (útil para clareza e compatibilidade com versões antigas do Laravel)
    protected $dates = ['deleted_at'];


    public function company()
    {
        return $this->belongsTo(CompanyModel::class, 'company_id');
    }


}
