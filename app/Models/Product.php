<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes, HasUuid;

    protected $fillable = [
        'uuid',
        'name',
        'code',
        'base_price',
        'sales_price',
        'last_purchase_price',
        'stock',
        'min_stock',
        'description',
        'is_active',
        'category_id',
        'unit_id',
        'supplier_id',
        'user_id',
        'company_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    // Scopes
    public static function active($query)
    {
        return $query->where('is_active', true);
    }

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function marketingProducts()
    {
        return $this->hasMany(MarketingProduct::class);
    }

    public function purchaseDetails()
    {
        return $this->hasMany(PurchaseDetail::class);
    }

    public function saleDetails()
    {
        return $this->hasMany(SalesDetail::class);
    }

    public function stockMutations()
    {
        return $this->hasMany(StockMutation::class);
    }
}