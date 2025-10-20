<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContactEntitiesModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'contact_entities';

    protected $primaryKey = 'id';

    protected $fillable = [
        'name',
        'partner_id',
        'company_id',
        'type',
        'contact',
        'note',
        'created_at',
        'updated_at',
        'deleted_at',
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
     * 🔗 Relação com o parceiro (Partner)
     */
    public function partner()
    {
        return $this->belongsTo(PartnerModel::class, 'partners_id');
    }

    /**
     * 🔗 Relação com a empresa (Company)
     */
    public function company()
    {
        return $this->belongsTo(CompanyModel::class, 'company_id');
    }

    /**
     * 🔗 Usuário que criou o contato
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 🔗 Usuário que atualizou o contato
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * 🔗 Usuário que excluiu o contato
     */
    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
