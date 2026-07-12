<?php

namespace Modules\Mail\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Modules\Business\Models\Business;

class Mailbox extends Model
{
    protected $table = 'mail_mailboxes';

    protected $fillable = [
        'business_id',
        'email_address',
        'imap_host',
        'imap_port',
        'imap_username',
        'imap_password',
        'imap_encryption',
        'is_active',
        'last_uid',
        'last_synced_at',
        'last_sync_error',
    ];

    protected function casts(): array
    {
        return [
            'is_active'       => 'boolean',
            'imap_port'       => 'integer',
            'last_uid'        => 'integer',
            'last_synced_at'  => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MailMessage::class, 'mailbox_id');
    }

    public function setImapPasswordAttribute(string $value): void
    {
        $this->attributes['imap_password'] = Crypt::encryptString($value);
    }

    public function getDecryptedPassword(): ?string
    {
        if (!filled($this->imap_password)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->imap_password);
        } catch (DecryptException) {
            return null;
        }
    }
}
