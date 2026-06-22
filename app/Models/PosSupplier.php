<?php

namespace App\Models;
use Database\Factories\Pos\PosSupplierFactory;

use App\Models\Scopes\CompanyScope;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PosSupplier extends Model
{
    use HasFactory, SoftDeletes, HasUuid;

    protected static $factory = PosSupplierFactory::class;
    protected $table = 'pos_suppliers';

    protected $fillable = [
        'uuid',
        'name',
        'address',
        'phone',
        'created_by',
        'company_id',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function products()
    {
        return $this->hasMany(PosProduct::class, 'supplier_id');
    }

    public function purchaseTransactions()
    {
        return $this->hasMany(PosPurchaseTransaction::class, 'supplier_id');
    }
}
