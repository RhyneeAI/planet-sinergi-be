<?php

namespace App\Models;

use App\Enums\PosInstallmentStatus;
use App\Models\Scopes\CompanyScope;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PosPurchaseInstallmentPlan extends Model
{
    use HasFactory, SoftDeletes, HasUlid;

    protected $table = 'pos_purchase_installment_plans';

    protected $fillable = [
        'ulid',
        'purchase_transaction_id',
        'supplier_id',
        'total_amount',
        'paid_amount',
        'start_date',
        'status',
        'company_id',
    ];

    protected $casts = [
        'status'       => PosInstallmentStatus::class,
        'start_date'   => 'date',
        'total_amount' => 'float',
        'paid_amount'  => 'float',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function purchaseTransaction()
    {
        return $this->belongsTo(PosPurchaseTransaction::class, 'purchase_transaction_id');
    }

    public function supplier()
    {
        return $this->belongsTo(PosSupplier::class, 'supplier_id');
    }

    public function payments()
    {
        return $this->hasMany(PosPurchaseInstallmentPayment::class, 'purchase_installment_plan_id');
    }

    public function remainingAmount(): float
    {
        return max(0, $this->total_amount - $this->paid_amount);
    }

    public function isLastPayment(float $amount): bool
    {
        return ($this->paid_amount + $amount) >= $this->total_amount;
    }
}
