<?php

namespace App\Models;
use Database\Factories\Pos\PosProductFactory;

use App\Models\Scopes\CompanyScope;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PosProduct extends Model
{
    use HasFactory, SoftDeletes, HasUuid;

    protected static $factory = PosProductFactory::class;
    protected $table = 'pos_products';

    protected $fillable = [
        'uuid',
        'name',
        'code',
        'base_price',
        'sales_price',
        'marketing_price',
        'last_purchase_price',
        'stock',
        'min_stock',
        'description',
        'is_active',
        'category_id',
        'unit_id',
        'created_by',
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

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function category()
    {
        return $this->belongsTo(PosCategory::class, 'category_id');
    }

    public function unit()
    {
        return $this->belongsTo(PosUnit::class, 'unit_id');
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function marketingProducts()
    {
        return $this->hasMany(PosMarketingProduct::class, 'product_id');
    }

    public function purchaseDetails()
    {
        return $this->hasMany(PosPurchaseDetail::class, 'product_id');
    }

    public function salesDetails()
    {
        return $this->hasMany(PosSalesDetail::class, 'product_id');
    }

    public function stockMutations()
    {
        return $this->hasMany(PosStockMutation::class, 'product_id');
    }
}
