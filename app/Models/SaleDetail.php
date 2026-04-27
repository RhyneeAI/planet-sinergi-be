<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'sell_price',
        'discount',
        'subtotal',
    ];

    // Relationships
    public function saleTransaction()
    {
        return $this->belongsTo(SaleTransaction::class, 'sale_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}