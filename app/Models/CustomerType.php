<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerType extends Model
{
    use HasFactory, SoftDeletes, HasUuid;

    protected $table = 'customer_types';

    protected $fillable = [
        'uuid',
        'type',
        'discount',
        'company_id',
    ];

    protected $casts = [
        'discount' => 'double',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}