<?php

declare(strict_types=1);

namespace Modules\HRManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

class PayrollCustomTemplate extends Model
{
    public const KEY_PREFIX = 'custom:';

    protected $table = 'hr_payroll_custom_templates';

    protected $fillable = [
        'business_id',
        'title',
        'description',
        'highlights',
        'rule_set_name',
        'currency',
        'rules',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'highlights' => 'array',
            'rules' => 'array',
            'settings' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function templateKey(): string
    {
        return self::KEY_PREFIX.(string) $this->getKey();
    }

    public static function matchesKey(?string $candidate): bool
    {
        if ($candidate === null || $candidate === '') {
            return false;
        }

        return str_starts_with($candidate, self::KEY_PREFIX)
            && ctype_digit(substr($candidate, strlen(self::KEY_PREFIX)));
    }

    public static function idFromKey(string $key): ?int
    {
        if (! self::matchesKey($key)) {
            return null;
        }

        return (int) substr($key, strlen(self::KEY_PREFIX));
    }
}
