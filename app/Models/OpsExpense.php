<?php

namespace App\Models;

use App\Enums\OpsExpenseType;
use App\Models\Scopes\CompanyScope;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OpsExpense extends Model
{
    use HasFactory, SoftDeletes, HasUuid;

    protected $fillable = [
        'uuid',
        'name',
        'amount',
        'date',
        'proof_files',
        'note',
        'expense_type',
        'mandor_id',
        'sub_company_id',
        'transfer_income_id',
        'created_by',
        'company_id',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'proof_files' => 'array',
        'expense_type' => OpsExpenseType::class,
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

    public function transferIncome()
    {
        return $this->belongsTo(OpsIncome::class, 'transfer_income_id');
    }

    public function transferConfirmation()
    {
        return $this->morphOne(OpsTransferConfirmation::class, 'confirmable');
    }

    public function editLogs()
    {
        return $this->hasMany(OpsEditLog::class, 'loggable_id', 'id')->where('loggable_type', 'ops_expenses');
    }
}
