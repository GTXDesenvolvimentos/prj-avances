<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AddressPartnerModel extends Model
{
    protected $table = 'address_partner';

    protected $fillable = [
        'partner_id',
        'address_id',
        'number',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public $timestamps = false; // Já estamos preenchendo created_at e updated_at manualmente
}
