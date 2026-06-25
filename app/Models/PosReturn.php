<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosReturn extends Model
{
    use HasFactory, HasUlid;

    protected $table = 'pos_returns';

    protected $fillable = [
        'ulid',
        'sales_transaction_id',
        'sales_detail_id',
        'product_id',
        'qty',
        'reason',
        'refund_amount',
        'status',
        'created_by',
        'company_id',
    ];

    protected $casts = [
        'refund_amount' => 'float',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function salesTransaction()
    {
        return $this->belongsTo(PosSalesTransaction::class, 'sales_transaction_id');
    }

    public function salesDetail()
    {
        return $this->belongsTo(PosSalesDetail::class, 'sales_detail_id');
    }

    public function product()
    {
        return $this->belongsTo(PosProduct::class, 'product_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
