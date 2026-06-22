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
        return $this->hasMany(PosCategory::class);
    }

    public function units()
    {
        return $this->hasMany(PosUnit::class);
    }

    public function suppliers()
    {
        return $this->hasMany(PosSupplier::class);
    }

    public function products()
    {
        return $this->hasMany(PosProduct::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function customers()
    {
        return $this->hasMany(PosCustomer::class);
    }

    public function customerTypes()
    {
        return $this->hasMany(PosCustomerType::class);
    }

    public function marketings()
    {
        return $this->hasMany(User::class)
                    ->where('role', Role::MARKETING);
    }


    public function purchaseTransactions()
    {
        return $this->hasMany(PosPurchaseTransaction::class);
    }

    public function salesTransactions()
    {
        return $this->hasMany(PosSalesTransaction::class);
    }

    public function stockMutations()
    {
        return $this->hasMany(PosStockMutation::class);
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

    public function opsSubCompanies()
    {
        return $this->hasMany(SubCompany::class);
    }

    public function absShifts()
    {
        return $this->hasMany(AbsShift::class);
    }

    public function absAttendances()
    {
        return $this->hasMany(AbsAttendance::class);
    }

    public function absPayrollPeriods()
    {
        return $this->hasMany(AbsPayrollPeriod::class);
    }
}