<?php

namespace Modules\Mail\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

class MailTemplate extends Model
{
    protected $table = 'mail_templates';

    protected $fillable = [
        'business_id',
        'name',
        'subject',
        'body',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
