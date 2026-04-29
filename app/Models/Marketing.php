<?php

namespace App\Models;

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

    public function salesTransactions()
    {
        return $this->hasMany(SaleTransaction::class);
    }
}