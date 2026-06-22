<?php

namespace App\Models;
use Database\Factories\Ops\OpsEditLogFactory;

use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpsEditLog extends Model
{
    use HasFactory;

    protected static $factory = OpsEditLogFactory::class;
    public $timestamps = false;

    protected $fillable = [
        'loggable_type',
        'loggable_id',
        'reason',
        'old_data',
        'new_data',
        'edited_by',
        'company_id',
        'created_at',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::creating(function (self $model) {
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    public function loggable()
    {
        return $this->morphTo();
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function editedBy()
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
