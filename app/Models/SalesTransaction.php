<?php

namespace App\Models;

use App\Enums\PaymentType;
use App\Enums\TransactionStatus;
use App\Models\Scopes\CompanyScope;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesTransaction extends Model
{
    use HasFactory, SoftDeletes, HasUlid;

    protected $fillable = [
        'ulid',
        'transaction_code',
        'transaction_date',
        'discount',
        'additional_cost',      
        'additional_cost_note', 
        'total',
        'paid',
        'payment_type',
        'transaction_status',
        'customer_id',
        'created_by',
        'company_id',
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
        'payment_type' => PaymentType::class,
        'transaction_status' => TransactionStatus::class,
        'additional_cost'   => 'float', 
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

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function details()
    {
        return $this->hasMany(SalesDetail::class, 'sale_id');
    }

    // CICILAN
    public function installmentPlan()
    {
        return $this->hasOne(SalesInstallmentPlan::class);
    }
}