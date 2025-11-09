<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContactsModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'contacts';
    protected $primaryKey = 'id';

    protected $fillable = [
        'name',
        'company_id',
        'type',
        'contact',
        'note',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * 🔗 Relação N:N com Partner
     */
    public function partners()
    {
        return $this->belongsToMany(PartnerModel::class, 'contacts_partners', 'contacts_id', 'partners_id')
            ->withPivot(['created_by', 'updated_by', 'deleted_by'])
            ->withTimestamps();
    }
}
