<?php

namespace App\Models;
use Database\Factories\Pos\PosStockMutationFactory;

use App\Enums\PosStockMutationType;
use App\Models\Scopes\CompanyScope;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosStockMutation extends Model
{
    use HasFactory, HasUlid;

    protected static $factory = PosStockMutationFactory::class;
    protected $table = 'pos_stock_mutations';

    protected $fillable = [
        'ulid',
        'type',
        'quantity',
        'stock_before',
        'stock_after',
        'notes',
        'product_id',
        'company_id',
        'reference_type',
        'reference_id',
        'created_by',
    ];

    protected $casts = [
        'type' => PosStockMutationType::class,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    // Relationships
    public function product()
    {
        return $this->belongsTo(PosProduct::class, 'product_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference()
    {
        // Morph to SalesTransactino or PurchaseTransaction
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }

    // Helper methods
    public function isIncoming(): bool
    {
        return $this->type->isIncoming(); 
    }

    public function isOutgoing(): bool
    {
        return $this->type->isOutgoing(); 
    }

    public function isOpname(): bool
    {
        return $this->type->isOpname();
    }
}
