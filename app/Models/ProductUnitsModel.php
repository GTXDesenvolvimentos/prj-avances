<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class ProductUnitsModel extends Model
{
    use HasFactory;

    protected $table = 'units';

    protected $fillable = [
        'id',
        'symbol',
        'description',
        'status',
        'company_id'
    ];

   
}
