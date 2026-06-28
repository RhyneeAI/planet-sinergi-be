<?php

namespace App\Models;

use App\Enums\AbsLoanStatus;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;

class AbsLoan extends Model
{
    protected $table = 'abs_loans';

    protected $fillable = [
        'user_id',
        'amount',
        'reason',
        'tenor_months',
        'monthly_installment',
        'remaining_balance',
        'status',
        'approved_by',
        'company_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'monthly_installment' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
        'status' => AbsLoanStatus::class,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
