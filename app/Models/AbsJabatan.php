<?php

namespace App\Models;
use Database\Factories\Abs\AbsJabatanFactory;

use App\Models\Scopes\CompanyScope;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AbsJabatan extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected static $factory = AbsJabatanFactory::class;

    protected $table = 'abs_jabatans';

    protected $fillable = [
        'uuid',
        'name',
        'daily_rate',
        'company_id',
    ];

    protected $casts = [
        'daily_rate' => 'decimal:2',
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
        return $this->hasMany(AbsEmployeeProfile::class, 'abs_jabatan_id');
    }
}
