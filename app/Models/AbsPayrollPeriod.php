<?php

namespace App\Models;
use Database\Factories\Abs\AbsPayrollPeriodFactory;

use App\Enums\AbsPayrollStatus;
use App\Models\Scopes\CompanyScope;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AbsPayrollPeriod extends Model
{
    use HasFactory, HasUlid;
    protected static $factory = AbsPayrollPeriodFactory::class;

    protected $table = 'abs_payroll_periods';

    protected $fillable = [
        'ulid',
        'user_id',
        'period_month',
        'period_year',
        'daily_rate',
        'total_days',
        'gross_salary',
        'total_deduction',
        'total_bonus',
        'net_salary',
        'notes',
        'status',
        'generated_at',
        'company_id',
    ];

    protected $casts = [
        'daily_rate' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'total_deduction' => 'decimal:2',
        'total_bonus' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'status' => AbsPayrollStatus::class,
        'generated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function getRouteKeyName()
    {
        return 'ulid';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function deductions()
    {
        return $this->hasMany(AbsDeduction::class, 'abs_payroll_period_id');
    }

    public function bonuses()
    {
        return $this->hasMany(AbsBonus::class, 'abs_payroll_period_id');
    }

    public function isFinal(): bool
    {
        return $this->status === AbsPayrollStatus::FINAL;
    }
}
