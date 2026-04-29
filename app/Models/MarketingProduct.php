<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingProduct extends Model
{
    use HasFactory, SoftDeletes, HasUuid;

    protected $table = 'marketing_products';

    protected $fillable = [
        'uuid',
        'product_id',
        'marketer_id',
    ];

    // Relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function marketer()
    {
        return $this->belongsTo(Marketing::class);
    }
}