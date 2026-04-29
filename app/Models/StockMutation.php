<?php

namespace App\Models;

use App\Enums\MutationType;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMutation extends Model
{
    use HasFactory, HasUlid;

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
        'type' => MutationType::class,
    ];

    // Relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Helper methods
    public function isIn(): bool
    {
        return $this->type === MutationType::IN;
    }

    public function isOut(): bool
    {
        return $this->type === MutationType::OUT;
    }

    public function isOpname(): bool
    {
        return $this->type === MutationType::OPNAME;
    }
}