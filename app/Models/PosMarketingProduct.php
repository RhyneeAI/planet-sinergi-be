<?php

namespace App\Models;
use Database\Factories\Pos\PosMarketingProductFactory;

use App\Enums\Role;
use App\Models\Scopes\CompanyScope;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PosMarketingProduct extends Model
{
    use HasFactory, SoftDeletes, HasUuid;

    protected static $factory = PosMarketingProductFactory::class;
    protected $table = 'pos_marketing_products';

    protected $fillable = [
        'uuid',
        'marketing_price',
        'product_id',
        'marketing_id',
        'created_by',
        'company_id'
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

    public function marketing()
    {
        return $this->belongsTo(User::class)
                    ->where('role', Role::MARKETING);
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
