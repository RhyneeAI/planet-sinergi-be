<?php

namespace App\Models;
use Database\Factories\Ops\OpsTransferConfirmationFactory;

use App\Enums\OpsTransferConfirmationStatus;
use App\Models\Scopes\CompanyScope;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpsTransferConfirmation extends Model
{
    use HasFactory, HasUuid;

    protected static $factory = OpsTransferConfirmationFactory::class;
    protected $fillable = [
        'uuid',
        'confirmable_type',
        'confirmable_id',
        'status',
        'confirmed_amount',
        'mandor_proof_files',
        'confirmed_at',
        'note',
        'confirmed_by',
        'company_id',
    ];

    protected $casts = [
        'status' => OpsTransferConfirmationStatus::class,
        'confirmed_amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'mandor_proof_files' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function confirmable()
    {
        return $this->morphTo();
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
