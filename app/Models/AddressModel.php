<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AddressModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'addresses';

    protected $primaryKey = 'id';

    protected $fillable = [
        'company_id',
        'partner_id',
        'zip_code',
        'street',
        'number',
        'complement',
        'neighborhood',
        'city',
        'state',
        'status',
        'active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * 🔗 Relação com a empresa
     */
    public function company()
    {
        return $this->belongsTo(CompanyModel::class, 'company_id');
    }

    /**
     * 🔗 Relação com o parceiro
     */
    public function partner()
    {
        return $this->belongsTo(PartnerModel::class, 'partner_id');
    }

    /**
     * 🔗 Usuário que criou o registro
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 🔗 Usuário que atualizou o registro
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * 🔗 Usuário que excluiu o registro
     */
    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
