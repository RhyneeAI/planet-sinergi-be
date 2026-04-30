<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseDetail extends Model
{
    use HasFactory, SoftDeletes, HasUlid;

    protected $fillable = [
        'ulid',
        'purchase_id',
        'product_id',
        'quantity',
        'buy_price',
        'subtotal',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    // Relationships
    public function purchaseTransaction()
    {
        return $this->belongsTo(PurchaseTransaction::class, 'purchase_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    
    public function stockMutations()
    {
        return $this->morphMany(StockMutation::class, 'reference');
    }
}