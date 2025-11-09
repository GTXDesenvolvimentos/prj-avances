<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactsPartnersModel extends Model
{
    protected $table = 'contacts_partners';

    // se você tem created_at/updated_at na tabela pivô
    public $timestamps = true;

    protected $fillable = [
        'partners_id',
        'contacts_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];
}
