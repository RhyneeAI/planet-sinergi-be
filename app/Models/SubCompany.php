<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubCompany extends Model
{
    use HasFactory, SoftDeletes, HasUuid;

    protected $table = 'ops_sub_companies';

    protected $fillable = [
        'uuid',
        'name',
        'code',
        'address',
        'is_active',
        'mandor_id',
        'company_id',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /** Mandor yang mengelola cabang ini (FK mandor_id di tabel cabang). */
    public function mandor()
    {
        return $this->belongsTo(User::class, 'mandor_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function wallet()
    {
        return $this->hasOne(OpsWallet::class, 'sub_company_id');
    }

    public function incomes()
    {
        return $this->hasMany(OpsIncome::class, 'sub_company_id');
    }

    public function expenses()
    {
        return $this->hasMany(OpsExpense::class, 'sub_company_id');
    }
}
