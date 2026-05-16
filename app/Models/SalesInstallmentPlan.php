<?php

namespace App\Models;

use App\Enums\InstallmentStatus;
use App\Models\Scopes\CompanyScope;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesInstallmentPlan extends Model
{
    use HasFactory, SoftDeletes, HasUlid;

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
        'status'     => InstallmentStatus::class,
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
        return $this->belongsTo(SalesTransaction::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments()
    {
        return $this->hasMany(SalesInstallmentPayment::class);
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
        if ($this->status === InstallmentStatus::COMPLETED) return false;
        $monthsPassed = $this->start_date->diffInMonths(now());
        return $monthsPassed >= $this->tenor && $this->remainingAmount() > 0;
    }
}