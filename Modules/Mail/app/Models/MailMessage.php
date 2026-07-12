<?php

namespace Modules\Mail\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;
use Modules\Mail\Support\HtmlSanitizer;

class MailMessage extends Model
{
    protected $table = 'mail_messages';

    const DIRECTION_INBOUND  = 'inbound';
    const DIRECTION_OUTBOUND = 'outbound';

    protected $fillable = [
        'business_id',
        'mailbox_id',
        'direction',
        'uid',
        'message_id',
        'from_address',
        'from_name',
        'to_address',
        'subject',
        'body_text',
        'body_html',
        'is_read',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read'     => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * Sanitized unconditionally, regardless of which code path writes this
     * attribute — body_html can originate from an arbitrary external sender and
     * is rendered with {!! !!}, so it must never reach storage un-purified.
     */
    public function setBodyHtmlAttribute(?string $value): void
    {
        $this->attributes['body_html'] = HtmlSanitizer::clean($value);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class, 'mailbox_id');
    }

    public function snippet(int $length = 120): string
    {
        $source = $this->body_text ?: strip_tags((string) $this->body_html);

        return \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', $source)), $length);
    }
}
