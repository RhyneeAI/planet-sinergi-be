<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'username',
        'email',
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

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function salesTransactions()
    {
        return $this->hasMany(SaleTransaction::class, 'user_id');
    }

    public function createdStockMutations()
    {
        return $this->hasMany(StockMutation::class, 'created_by');
    }

    // Helper methods
    public function isAdmin(): bool
    {
        return $this->role === Role::ADMIN;
    }

    public function isOwner(): bool
    {
        return $this->role === Role::OWNER;
    }

    public function isMarketer(): bool
    {
        return $this->role === Role::MARKETER;
    }
}
