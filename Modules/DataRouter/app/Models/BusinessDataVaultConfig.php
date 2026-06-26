<?php

declare(strict_types=1);

namespace Modules\DataRouter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

class BusinessDataVaultConfig extends Model
{
    protected $table = 'business_data_vault_configs';

    protected $fillable = [
        'business_id',
        'vault_url',
        'shared_secret',
        'is_enabled',
        'enabled_modules',
        'label',
    ];

    protected function casts(): array
    {
        return [
            'shared_secret'   => 'encrypted',
            'is_enabled'      => 'boolean',
            'enabled_modules' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function isModuleEnabled(string $module): bool
    {
        return $this->is_enabled
            && in_array($module, (array) ($this->enabled_modules ?? []), true);
    }
}
