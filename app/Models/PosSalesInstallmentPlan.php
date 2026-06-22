<?php

namespace App\Models;

use App\Enums\PosInstallmentStatus;
use App\Models\Scopes\CompanyScope;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PosSalesInstallmentPlan extends Model
{
    use HasFactory, SoftDeletes, HasUlid;

    protected $table = 'pos_sales_installment_plans';

    protected $fillable = [
        'ulid',
        'sales_transaction_id',
        'customer_id',
        'total_amount',
        'paid_amount',
        'tenor',
        'start_date',
        'status',
        'company_id',
    ];

    protected $casts = [
        'status'     => PosInstallmentStatus::class,
        'start_date' => 'date',
        'total_amount' => 'float',
        'paid_amount'  => 'float',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function salesTransaction()
    {
        return $this->belongsTo(PosSalesTransaction::class, 'sales_transaction_id');
    }

    public function customer()
    {
        return $this->belongsTo(PosCustomer::class, 'customer_id');
    }

    public function payments()
    {
        return $this->hasMany(PosSalesInstallmentPayment::class, 'sales_installment_plan_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // Helpers
    public function remainingAmount(): float
    {
        return max(0, $this->total_amount - $this->paid_amount);
    }

    public function isLastPayment(float $amount): bool
    {
        return ($this->paid_amount + $amount) >= $this->total_amount;
    }

    public function isOverdue(): bool
    {
        if ($this->status === PosInstallmentStatus::COMPLETED) return false;
        $monthsPassed = $this->start_date->diffInMonths(now());
        return $monthsPassed >= $this->tenor && $this->remainingAmount() > 0;
    }
}
