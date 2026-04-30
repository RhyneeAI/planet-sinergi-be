<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Scopes\CompanyScope;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesTransaction extends Model
{
    use HasFactory, SoftDeletes, HasUlid;

    protected $fillable = [
        'ulid',
        'transaction_code',
        'transaction_date',
        'discount',
        'total',
        'user_id',
        'customer_id',
        'marketing_id',
        'company_id',
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function marketing()
    {
        return $this->belongsTo(User::class)   
                    ->where('role', Role::MARKETING);
    }

    public function details()
    {
        return $this->hasMany(SalesDetail::class, 'sale_id');
    }
}