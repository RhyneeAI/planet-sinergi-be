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

    protected $fillable = [
        'uuid',
        'name',
        'username',
        'email',
        'address',
        'phone',
        'password',
        'role',
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

    public function salesTransactions()
    {
        return $this->hasMany(SalesTransaction::class, 'user_id');
    }

    public function createdStockMutations()
    {
        return $this->hasMany(StockMutation::class, 'created_by');
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
