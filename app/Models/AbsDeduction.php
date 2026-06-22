<?php

namespace App\Models;
use Database\Factories\Abs\AbsDeductionFactory;

use App\Models\Scopes\CompanyScope;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AbsDeduction extends Model
{
    use HasFactory, HasUlid;
    protected static $factory = AbsDeductionFactory::class;

    protected $table = 'abs_deductions';

    protected $fillable = [
        'ulid',
        'abs_payroll_period_id',
        'user_id',
        'abs_attendance_id',
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

    public function attendance()
    {
        return $this->belongsTo(AbsAttendance::class, 'abs_attendance_id');
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
