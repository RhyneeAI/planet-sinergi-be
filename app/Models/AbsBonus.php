<?php

namespace App\Models;
use Database\Factories\Abs\AbsBonusFactory;

use App\Models\Scopes\CompanyScope;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AbsBonus extends Model
{
    use HasFactory, HasUlid;
    protected static $factory = AbsBonusFactory::class;

    protected $table = 'abs_bonuses';

    protected $fillable = [
        'ulid',
        'abs_payroll_period_id',
        'user_id',
        'reason',
        'amount',
        'created_by',
        'company_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function getRouteKeyName()
    {
        return 'ulid';
    }

    public function payrollPeriod()
    {
        return $this->belongsTo(AbsPayrollPeriod::class, 'abs_payroll_period_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
