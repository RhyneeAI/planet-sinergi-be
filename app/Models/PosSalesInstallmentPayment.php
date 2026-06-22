<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PosSalesInstallmentPayment extends Model
{
    use HasFactory, SoftDeletes, HasUlid;

    protected $table = 'pos_sales_installment_payments';

    protected $fillable = [
        'ulid',
        'sales_installment_plan_id',
        'installment_number',
        'paid_amount',
        'paid_date',
        'notes',
        'created_by',
        'company_id',
    ];

    protected $casts = [
        'paid_date'   => 'date',
        'paid_amount' => 'float',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function plan()
    {
        return $this->belongsTo(PosSalesInstallmentPlan::class, 'sales_installment_plan_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
