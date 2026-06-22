<?php

namespace App\Models;
use Database\Factories\Pos\PosCustomerFactory;

use App\Models\Scopes\CompanyScope;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PosCustomer extends Model
{
    use HasFactory, SoftDeletes, HasUuid;

    protected static $factory = PosCustomerFactory::class;
    protected $table = 'pos_customers';

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    protected $fillable = [
        'uuid',
        'name',
        'address',
        'phone',
        'customer_type_id',
        'created_by',
        'company_id',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customerType()
    {
        return $this->belongsTo(PosCustomerType::class, 'customer_type_id');
    }

    public function salesTransactions()
    {
        return $this->hasMany(PosSalesTransaction::class, 'customer_id');
    }
}
