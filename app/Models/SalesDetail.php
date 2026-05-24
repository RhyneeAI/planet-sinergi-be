<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesDetail extends Model
{
    use HasFactory, SoftDeletes, HasUlid;

    protected $fillable = [
        'ulid',
        'sale_id',
        'product_id',
        'quantity',
        'marketing_price',
        'sell_price',
        'discount',
        'subtotal',
        'company_id'
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    // Relationships
    public function saleTransaction()
    {
        return $this->belongsTo(SalesTransaction::class, 'sale_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    
    // Stock Mutation
    public function stockMutations()
    {
        return $this->morphMany(StockMutation::class, 'reference');
    }
}