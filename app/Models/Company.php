<?php

namespace App\Models;

use App\Enums\Role;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes, HasUuid;

    protected $fillable = [
        'uuid',
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

    public function customerTypes()
    {
        return $this->hasMany(CustomerType::class);
    }

    public function marketings()
    {
        return $this->hasMany(User::class)
                    ->where('role', Role::MARKETING);
    }


    public function purchaseTransactions()
    {
        return $this->hasMany(PurchaseTransaction::class);
    }

    public function salesTransactions()
    {
        return $this->hasMany(SalesTransaction::class);
    }

    public function stockMutations()
    {
        return $this->hasMany(StockMutation::class);
    }

    public function opsIncomes()
    {
        return $this->hasMany(OpsIncome::class);
    }

    public function opsExpenses()
    {
        return $this->hasMany(OpsExpense::class);
    }

    public function opsWallets()
    {
        return $this->hasMany(OpsWallet::class);
    }
}