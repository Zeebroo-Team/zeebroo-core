<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One sending of a quotation PDF to the customer over one channel (email, WhatsApp, …). */
class QuotationDispatch extends Model
{
    const STATUS_QUEUED = 'queued';
    const STATUS_SENT   = 'sent';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'quotation_id',
        'channel',
        'to',
        'cc',
        'subject',
        'message',
        'file_path',
        'status',
        'error',
        'sent_by',
        'sent_at',
    ];

    protected $casts = [
        'cc'      => 'array',
        'sent_at' => 'datetime',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'sent_by');
    }
}
