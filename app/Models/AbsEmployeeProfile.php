<?php

namespace App\Models;
use Database\Factories\Abs\AbsEmployeeProfileFactory;

use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AbsEmployeeProfile extends Model
{
    use HasFactory;
    protected static $factory = AbsEmployeeProfileFactory::class;

    protected $table = 'abs_employee_profiles';

    protected $fillable = [
        'user_id',
        'abs_jabatan_id',
        'sub_company_id',
        'abs_shift_id',
        'company_id',
    ];

    protected $casts = [];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function jabatan()
    {
        return $this->belongsTo(Position::class, 'abs_jabatan_id');
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
}
