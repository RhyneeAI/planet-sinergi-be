<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PosPurchaseDetail extends Model
{
    use HasFactory, SoftDeletes, HasUlid;

    protected $table = 'pos_purchase_details';

    protected $fillable = [
        'ulid',
        'purchase_id',
        'product_id',
        'quantity',
        'buy_price',
        'subtotal',
        'company_id'
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    // Relationships
    public function purchaseTransaction()
    {
        return $this->belongsTo(PosPurchaseTransaction::class, 'purchase_id');
    }

    public function product()
    {
        return $this->belongsTo(PosProduct::class, 'product_id');
    }
    
    public function stockMutations()
    {
        return $this->morphMany(PosStockMutation::class, 'reference');
    }
}
