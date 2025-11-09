<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PartnerModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'partners';
    protected $primaryKey = 'id';

    protected $fillable = [
        'name',
        'tax_id',
        'partner_type',
        'person_type',
        'company_id',
        'status',
        'note',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * 🔗 Relação N:N com Contacts
     */
    public function contacts()
    {
        return $this->belongsToMany(ContactsModel::class, 'contacts_partners', 'partners_id', 'contacts_id')
            ->withPivot(['created_by', 'updated_by', 'deleted_by'])
            ->withTimestamps();
    }

    /**
     * 🔗 Relação N:N com Addresses
     */
    public function addresses()
    {
        return $this->belongsToMany(AddressModel::class, 'address_partner', 'partner_id', 'address_id')
            ->withPivot(['number', 'created_at', 'updated_at', 'deleted_at'])
            ->withTimestamps();
    }
}
