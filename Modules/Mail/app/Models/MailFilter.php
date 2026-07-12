<?php

namespace Modules\Mail\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

class MailFilter extends Model
{
    protected $table = 'mail_filters';

    const FIELD_FROM    = 'from';
    const FIELD_SUBJECT = 'subject';

    const ACTION_MARK_READ = 'mark_read';
    const ACTION_DELETE    = 'delete';

    protected $fillable = [
        'business_id',
        'field',
        'value',
        'action',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public static function fields(): array
    {
        return [
            self::FIELD_FROM    => 'From',
            self::FIELD_SUBJECT => 'Subject',
        ];
    }

    public static function actions(): array
    {
        return [
            self::ACTION_MARK_READ => 'Mark as read',
            self::ACTION_DELETE    => 'Delete',
        ];
    }

    public function fieldLabel(): string
    {
        return self::fields()[$this->field] ?? $this->field;
    }

    public function actionLabel(): string
    {
        return self::actions()[$this->action] ?? $this->action;
    }

    /**
     * Whether an incoming message matches this filter's condition —
     * case-insensitive substring match against the given field's value.
     */
    public function matches(?string $fromAddress, ?string $fromName, ?string $subject): bool
    {
        $haystack = match ($this->field) {
            self::FIELD_SUBJECT => (string) $subject,
            default              => trim(($fromName ?? '') . ' ' . ($fromAddress ?? '')),
        };

        return $this->value !== '' && str_contains(strtolower($haystack), strtolower($this->value));
    }
}
