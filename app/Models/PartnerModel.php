<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PartnerModel extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'partners';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'tax_id',
        'partner_type',
        'company_id',
        'status',
        'note'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        // 'password', // se tiver campo de senha
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'status' => 'boolean',
        'company_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * The attributes that should be treated as dates.
     *
     * @var array
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    /**
     * Scope a query to only include active partners.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope a query to only include inactive partners.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInactive($query)
    {
        return $query->where('status', false);
    }

    /**
     * Scope a query to only include partners by type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByType($query, $type)
    {
        return $query->where('partner_type', $type);
    }

    /**
     * Scope a query to only include partners by company.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $companyId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Get the company that owns the partner.
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Check if partner is active.
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->status === true;
    }

    /**
     * Check if partner is inactive.
     *
     * @return bool
     */
    public function isInactive()
    {
        return $this->status === false;
    }

    /**
     * Get partner type label.
     *
     * @return string
     */
    public function getPartnerTypeLabel()
    {
        $types = [
            'customer' => 'Customer',
            'supplier' => 'Supplier',
            'distributor' => 'Distributor',
            'reseller' => 'Reseller',
            'partner' => 'Partner'
        ];

        return $types[$this->partner_type] ?? ucfirst($this->partner_type);
    }
}