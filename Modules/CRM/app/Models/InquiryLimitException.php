<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Extra inquiries an administrator allows one user on one day, on top of the daily limit. */
class InquiryLimitException extends Model
{
    protected $table = 'crm_inquiry_limit_exceptions';

    protected $fillable = [
        'business_id',
        'user_id',
        'exception_date',
        'extra_allowed',
        'reason',
        'granted_by',
    ];

    protected function casts(): array
    {
        return [
            'exception_date' => 'date',
            'extra_allowed'  => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
