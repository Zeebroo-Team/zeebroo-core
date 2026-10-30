<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One step of a lead quotation's approval (see QuotationApprovalService in the CRM). */
class QuotationApproval extends Model
{
    const ACTION_SUBMITTED = 'submitted';
    const ACTION_APPROVED  = 'approved';
    const ACTION_RETURNED  = 'returned';
    const ACTION_REJECTED  = 'rejected';

    /** action => [label, Font Awesome icon, colour] */
    const ACTIONS = [
        self::ACTION_SUBMITTED => ['Submitted for approval', 'fa-paper-plane',     '#a855f7'],
        self::ACTION_APPROVED  => ['Approved',               'fa-circle-check',    '#0d9488'],
        self::ACTION_RETURNED  => ['Returned for changes',   'fa-rotate-left',     '#f97316'],
        self::ACTION_REJECTED  => ['Rejected',               'fa-circle-xmark',    '#ef4444'],
    ];

    protected $fillable = [
        'quotation_id',
        'action',
        'version',
        'by_user_id',
        'comment',
    ];

    protected $casts = [
        'version' => 'integer',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function byUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'by_user_id');
    }

    public function label(): string
    {
        return self::ACTIONS[$this->action][0] ?? ucfirst($this->action);
    }
}
