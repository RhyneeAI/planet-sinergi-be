<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;

class CustomConfiguration extends Model
{
    protected $table = 'custom_configurations';

    protected $fillable = [
        'key',
        'value',
        'description',
        'company_id',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public static function getValue(string $key, $default = null): ?string
    {
        $record = static::where('key', $key)->first();
        return $record ? $record->value : $default;
    }

    public static function setValue(string $key, string $value, ?string $description = null): self
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'description' => $description, 'company_id' => auth()->user()->company_id]
        );
    }
}
