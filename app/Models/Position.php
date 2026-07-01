<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use App\Traits\HasUuid;
use Database\Factories\PositionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Position extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected static $factory = PositionFactory::class;

    protected $table = 'positions';

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
