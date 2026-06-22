<?php

namespace App\Models;
use Database\Factories\Ops\OpsIncomeFactory;

use App\Enums\OpsPaymentMethod;
use App\Enums\OpsSourceType;
use App\Models\Scopes\CompanyScope;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OpsIncome extends Model
{
    use HasFactory, SoftDeletes, HasUuid;

    protected static $factory = OpsIncomeFactory::class;
    protected $fillable = [
        'uuid',
        'name',
        'amount',
        'date',
        'payment_method',
        'proof_files',
        'note',
        'source_type',
        'mandor_id',
        'sub_company_id',
        'created_by',
        'company_id',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'payment_method' => OpsPaymentMethod::class,
        'proof_files' => 'array',
        'source_type' => OpsSourceType::class,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function mandor()
    {
        return $this->belongsTo(User::class, 'mandor_id');
    }

    public function subCompany()
    {
        return $this->belongsTo(SubCompany::class, 'sub_company_id');
    }

    public function transferConfirmation()
    {
        return $this->morphOne(OpsTransferConfirmation::class, 'confirmable');
    }

    public function editLogs()
    {
        return $this->hasMany(OpsEditLog::class, 'loggable_id', 'id')->where('loggable_type', 'ops_incomes');
    }
}
