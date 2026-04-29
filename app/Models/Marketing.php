<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Marketing extends Model
{
    use HasFactory, SoftDeletes, HasUuid;

    protected $fillable = [
        'uuid',
        'name',
        'address',
        'phone',
        'company_id',
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

    public function marketingProducts()
    {
        return $this->hasMany(MarketingProduct::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'marketing_products');
    }

    public function saleTransactions()
    {
        return $this->hasMany(SaleTransaction::class);
    }
}