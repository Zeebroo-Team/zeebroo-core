<?php

namespace Modules\Mail\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An automatic platform email (email verification OTP, welcome, password reset OTP, inactivity reminder,
 * activity report, new release) with its admin-editable template and settings.
 */
class AdminAutomatedEmail extends Model
{
    public const EMAIL_VERIFICATION = 'email_verification';

    public const WELCOME = 'welcome';

    public const PASSWORD_RESET = 'password_reset';

    public const INACTIVITY = 'inactivity_reminder';

    public const REPORT = 'activity_report';

    public const NEW_RELEASE = 'new_release';

    protected $table = 'admin_automated_emails';

    protected $fillable = [
        'key',
        'is_enabled',
        'subject',
        'body',
        'settings',
        'last_run_at',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'settings' => 'array',
            'last_run_at' => 'datetime',
        ];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function setting(string $name, mixed $default = null): mixed
    {
        return $this->settings[$name] ?? $default;
    }
}
