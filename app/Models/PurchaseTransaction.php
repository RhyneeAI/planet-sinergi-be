<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transaction_code',
        'transaction_date',
        'discount',
        'total',
        'supplier_id',
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

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function details()
    {
        return $this->hasMany(PurchaseDetail::class, 'purchase_id');
    }
}