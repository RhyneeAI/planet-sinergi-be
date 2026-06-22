<?php

namespace App\Models;
use Database\Factories\Pos\PosSalesDetailFactory;

use App\Models\Scopes\CompanyScope;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PosSalesDetail extends Model
{
    use HasFactory, SoftDeletes, HasUlid;

    protected static $factory = PosSalesDetailFactory::class;
    protected $table = 'pos_sales_details';

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
        return $this->belongsTo(PosSalesTransaction::class, 'sale_id');
    }

    public function product()
    {
        return $this->belongsTo(PosProduct::class, 'product_id');
    }

    
    // Stock Mutation
    public function stockMutations()
    {
        return $this->morphMany(PosStockMutation::class, 'reference');
    }
}
