<?php

namespace App\Models;
use Database\Factories\Pos\PosPurchaseTransactionFactory;

use App\Enums\PosPaymentType;
use App\Enums\PosTransactionStatus;
use App\Models\Scopes\CompanyScope;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PosPurchaseTransaction extends Model
{
    use HasFactory, SoftDeletes, HasUlid;

    protected static $factory = PosPurchaseTransactionFactory::class;
    protected $table = 'pos_purchase_transactions';

    protected $fillable = [
        'ulid',
        'transaction_code',
        'transaction_date',
        'discount',
        'total',
        'paid',
        'payment_type',
        'transaction_status',
        'supplier_id',
        'created_by',
        'company_id',
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
        'payment_type' => PosPaymentType::class,
        'transaction_status' => PosTransactionStatus::class
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

    public function supplier()
    {
        return $this->belongsTo(PosSupplier::class, 'supplier_id');
    }

    public function details()
    {
        return $this->hasMany(PosPurchaseDetail::class, 'purchase_id');
    }

    public function installmentPlan()
    {
        return $this->hasOne(PosPurchaseInstallmentPlan::class, 'purchase_transaction_id');
    }
}
