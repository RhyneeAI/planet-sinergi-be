<?php

namespace App\Models;
use Database\Factories\Ops\OpsWalletFactory;

use App\Models\Scopes\CompanyScope;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpsWallet extends Model
{
    use HasFactory, HasUuid;

    protected static $factory = OpsWalletFactory::class;
    protected $fillable = [
        'uuid',
        'mandor_id',
        'sub_company_id',
        'balance',
        'company_id',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function mandor()
    {
        return $this->belongsTo(User::class, 'mandor_id');
    }

    public function subCompany()
    {
        return $this->belongsTo(SubCompany::class, 'sub_company_id');
    }

    public function transactions()
    {
        return $this->hasMany(OpsWalletTransaction::class, 'wallet_id');
    }
}
