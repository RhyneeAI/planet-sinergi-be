<?php

namespace App\Models;
use Database\Factories\Abs\AbsAttendanceFactory;

use App\Enums\AbsAttendanceStatus;
use App\Models\Scopes\CompanyScope;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AbsAttendance extends Model
{
    use HasFactory, HasUlid;
    protected static $factory = AbsAttendanceFactory::class;

    protected $table = 'abs_attendances';

    protected $fillable = [
        'ulid',
        'user_id',
        'sub_company_id',
        'abs_shift_id',
        'date',
        'check_in_time',
        'check_in_photo',
        'check_in_lat',
        'check_in_lng',
        'check_out_time',
        'check_out_photo',
        'check_out_lat',
        'check_out_lng',
        'status',
        'late_reason',
        'early_reason',
        'company_id',
    ];

    protected $casts = [
        'date' => 'date',
        'status' => AbsAttendanceStatus::class,
        'check_in_lat' => 'decimal:8',
        'check_in_lng' => 'decimal:8',
        'check_out_lat' => 'decimal:8',
        'check_out_lng' => 'decimal:8',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subCompany()
    {
        return $this->belongsTo(SubCompany::class, 'sub_company_id');
    }

    public function shift()
    {
        return $this->belongsTo(AbsShift::class, 'abs_shift_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function deductions()
    {
        return $this->hasMany(AbsDeduction::class, 'abs_attendance_id');
    }

    public function hasCheckedIn(): bool
    {
        return !is_null($this->check_in_time);
    }

    public function hasCheckedOut(): bool
    {
        return !is_null($this->check_out_time);
    }
}
