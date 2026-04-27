<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'address',
        'code',
    ];

    // Relationships
    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    public function suppliers()
    {
        return $this->hasMany(Supplier::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function marketers()
    {
        return $this->hasMany(Marketer::class);
    }

    public function purchaseTransactions()
    {
        return $this->hasMany(PurchaseTransaction::class);
    }

    public function salesTransactions()
    {
        return $this->hasMany(SaleTransaction::class);
    }

    public function stockMutations()
    {
        return $this->hasMany(StockMutation::class);
    }
}