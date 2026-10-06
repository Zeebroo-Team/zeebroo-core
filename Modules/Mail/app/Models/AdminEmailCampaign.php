<?php

namespace Modules\Mail\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One bulk send: a snapshot of the subject/body at send time plus its recipients.
 */
class AdminEmailCampaign extends Model
{
    public const STATUS_SENDING = 'sending';

    public const STATUS_COMPLETED = 'completed';

    protected $table = 'admin_email_campaigns';

    protected $fillable = [
        'template_id',
        'subject',
        'body',
        'status',
        'recipients_count',
        'sent_count',
        'failed_count',
        'created_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'recipients_count' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AdminEmailTemplate::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(AdminEmailCampaignRecipient::class, 'campaign_id');
    }

    public function pendingCount(): int
    {
        return max(0, $this->recipients_count - $this->sent_count - $this->failed_count);
    }
}
