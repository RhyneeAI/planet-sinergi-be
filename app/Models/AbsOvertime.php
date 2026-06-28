<?php

namespace App\Models;

use App\Enums\AbsOvertimeStatus;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;

class AbsOvertime extends Model
{
    protected $table = 'abs_overtimes';

    protected $fillable = [
        'user_id',
        'date',
        'start_time',
        'end_time',
        'reason',
        'status',
        'approved_by',
        'company_id',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'string',
        'end_time' => 'string',
        'status' => AbsOvertimeStatus::class,
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
