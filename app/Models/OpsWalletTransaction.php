<?php

namespace App\Models;
use Database\Factories\Ops\OpsWalletTransactionFactory;

use App\Enums\OpsWalletTransactionType;
use App\Models\Scopes\CompanyScope;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpsWalletTransaction extends Model
{
    use HasFactory, HasUuid;

    protected static $factory = OpsWalletTransactionFactory::class;
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'wallet_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'reference_type',
        'reference_id',
        'note',
        'created_by',
        'company_id',
        'created_at',
    ];

    protected $casts = [
        'type' => OpsWalletTransactionType::class,
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::creating(function (self $model) {
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    public function wallet()
    {
        return $this->belongsTo(OpsWallet::class, 'wallet_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference()
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }
}
