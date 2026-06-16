<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Scopes\CompanyScope;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasUuid;

    public static bool $skipSubCompanyAutoCreate = false;

    protected $fillable = [
        'uuid',
        'name',
        'email',
        'address',
        'phone',
        'password',
        'role',
        'is_active',
        'created_by',
        'company_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'role' => Role::class,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'created_by');
    }

    public function marketingProducts()
    {
        return $this->hasMany(MarketingProduct::class, 'marketing_id');
    }

    public function salesTransactions()
    {
        return $this->hasMany(SalesTransaction::class, 'created_by', 'id');
    }

    public function createdStockMutations()
    {
        return $this->hasMany(StockMutation::class, 'created_by');
    }

    public function opsWallet()
    {
        return $this->hasOne(OpsWallet::class, 'mandor_id');
    }

    public function opsWallets()
    {
        return $this->hasMany(OpsWallet::class, 'mandor_id');
    }

    public function subCompanies()
    {
        return $this->hasMany(SubCompany::class, 'mandor_id');
    }

    /** Cabang utama mandor (aktif, urut nama) — FK mandor_id ada di ops_sub_companies. */
    public function primarySubCompany()
    {
        return $this->hasOne(SubCompany::class, 'mandor_id')
            ->ofMany(['name' => 'min'], fn ($query) => $query->where('is_active', true));
    }

    public function mandorIncomes()
    {
        return $this->hasMany(OpsIncome::class, 'mandor_id');
    }

    public function mandorExpenses()
    {
        return $this->hasMany(OpsExpense::class, 'mandor_id');
    }

    public function opsNotifications()
    {
        return $this->hasMany(OpsNotification::class);
    }

    // Helper methods
    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SUPERADMIN;
    }

    public function isOwner(): bool
    {
        return $this->role === Role::OWNER;
    }

    public function isMarketing(): bool
    {
        return $this->role === Role::MARKETING;
    }
}
