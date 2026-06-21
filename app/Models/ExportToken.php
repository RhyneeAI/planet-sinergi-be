<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExportToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'report_type',
        'filters',
        'format',
        'status',
        'disk_path',
        'filename',
        'error_message',
        'requested_by',
        'company_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'completed_at' => 'datetime',
        ];
    }
}
