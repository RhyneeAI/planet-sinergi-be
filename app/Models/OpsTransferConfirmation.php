<?php

namespace App\Models;

use App\Enums\OpsTransferConfirmationStatus;
use App\Models\Scopes\CompanyScope;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpsTransferConfirmation extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'confirmable_type',
        'confirmable_id',
        'status',
        'mandor_proof_file',
        'confirmed_at',
        'note',
        'confirmed_by',
        'company_id',
    ];

    protected $casts = [
        'status' => OpsTransferConfirmationStatus::class,
        'confirmed_at' => 'datetime',
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
