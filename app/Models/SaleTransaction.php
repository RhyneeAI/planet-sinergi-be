<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleTransaction extends Model
{
    use HasFactory, SoftDeletes, HasUlid;

    protected $fillable = [
        'ulid',
        'transaction_code',
        'transaction_date',
        'discount',
        'total',
        'user_id',
        'customer_id',
        'marketer_id',
        'company_id',
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function marketer()
    {
        return $this->belongsTo(Marketer::class);
    }

    public function details()
    {
        return $this->hasMany(SaleDetail::class, 'sale_id');
    }
}