<?php

namespace App\Models;
use Database\Factories\Abs\AbsShiftFactory;

use App\Models\Scopes\CompanyScope;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AbsShift extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected static $factory = AbsShiftFactory::class;

    protected $table = 'abs_shifts';

    protected $fillable = [
        'uuid',
        'name',
        'start_time',
        'end_time',
        'company_id',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function employeeProfiles()
    {
        return $this->hasMany(AbsEmployeeProfile::class, 'abs_shift_id');
    }

    public function attendances()
    {
        return $this->hasMany(AbsAttendance::class, 'abs_shift_id');
    }
}
