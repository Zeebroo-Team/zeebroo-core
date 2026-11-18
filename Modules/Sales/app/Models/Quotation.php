<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Business\Models\Business;
use Modules\CRM\Models\Lead;
use Modules\DesignStudio\Models\Design;
use Modules\Pos\Models\Customer;

class Quotation extends Model
{
    const STATUS_DRAFT    = 'draft';
    const STATUS_SENT     = 'sent';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';
    const STATUS_EXPIRED  = 'expired';

    // The approval workflow of a quotation built from a CRM lead
    const STATUS_PENDING_APPROVAL = 'pending_approval';
    const STATUS_RETURNED         = 'returned';
    const STATUS_APPROVED         = 'approved';

    // A lead quotation replaced by a newer version — kept read-only as history
    const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'business_id',
        'branch_id',
        'quote_number',
        'version',
        'parent_quotation_id',
        'customer_id',
        'lead_id',
        'customer_address',
        'reference',
        'quote_date',
        'expiry_date',
        'status',
        'notes',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total',
        'prepared_by',
        'prepared_by_name',
        'prepared_by_designation',
        'prepared_by_phone',
        'submitted_by',
        'approved_by',
        'approved_at',
        'approval_comment',
        'pdf_path',
        'sent_at',
        'sent_via',
    ];

    protected $casts = [
        'quote_date'      => 'date',
        'expiry_date'     => 'date',
        'subtotal'        => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount'      => 'decimal:2',
        'total'           => 'decimal:2',
        'version'         => 'integer',
        'approved_at'     => 'datetime',
        'sent_at'         => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }

    /** The CRM lead this quotation was built from, if any. */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    /**
     * Who the quotation is addressed to: the customer, else the CRM lead it was built for
     * (not converted to a customer yet), else a walk-in. The quotation's own address wins.
     *
     * @return array{name: string, contact: string, address: string, phone: string, email: string}
     */
    public function billTo(): array
    {
        if ($c = $this->customer) {
            return [
                'name'    => (string) $c->name,
                'contact' => (string) ($c->contact_name ?? ''),
                'address' => (string) ($this->customer_address ?: ($c->address ?? '')),
                'phone'   => (string) ($c->phone ?? ''),
                'email'   => (string) ($c->email ?? ''),
            ];
        }
        if ($this->lead_id && ($l = $this->lead)) {
            return [
                'name'    => (string) ($l->company ?: $l->name),
                'contact' => $l->company ? (string) $l->name : '',
                'address' => (string) ($this->customer_address ?? ''),
                'phone'   => (string) ($l->phone ?? ''),
                'email'   => (string) ($l->email ?? ''),
            ];
        }

        return ['name' => 'Walk-in Customer', 'contact' => '', 'address' => (string) ($this->customer_address ?? ''), 'phone' => '', 'email' => ''];
    }

    /** The version this one revises. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_quotation_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(QuotationApproval::class)->orderBy('created_at')->orderBy('id');
    }

    /** Requests to change this version, oldest first. */
    public function revisionRequests(): HasMany
    {
        return $this->hasMany(QuotationRevisionRequest::class)->orderBy('created_at')->orderBy('id');
    }

    /** The request waiting for the head's decision, if any. */
    public function pendingRevisionRequest(): HasOne
    {
        return $this->hasOne(QuotationRevisionRequest::class)->where('status', QuotationRevisionRequest::STATUS_PENDING)->latest('id');
    }

    /** The approved request this version was made from (none for a first version). */
    public function revisionSource(): HasOne
    {
        return $this->hasOne(QuotationRevisionRequest::class, 'revision_id');
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(QuotationDispatch::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /** What the customer said about this version, oldest first. */
    public function customerResponses(): HasMany
    {
        return $this->hasMany(QuotationCustomerResponse::class)->orderBy('created_at')->orderBy('id');
    }

    /** The staff member who prepared it (the "Prepared by" details are kept on the quotation itself). */
    public function preparer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'prepared_by');
    }

    /**
     * The "Prepared by" block of the printed quotation, or null when nobody is on file.
     *
     * @return array{name: string, designation: string, phone: string}|null
     */
    public function preparedByDetails(): ?array
    {
        $name = trim((string) $this->prepared_by_name);
        if ($name === '') {
            return null;
        }

        return [
            'name'        => $name,
            'designation' => trim((string) $this->prepared_by_designation),
            'phone'       => trim((string) $this->prepared_by_phone),
        ];
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'submitted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(Design::class, 'quotation_id')->whereNotNull('proposal_group');
    }

    public function isLeadQuotation(): bool
    {
        return $this->lead_id !== null;
    }

    /**
     * Sales / POS quotations stay editable while draft or sent. A lead quotation goes through
     * approval, so only a draft or one the head returned can change; once issued it is locked
     * and a change needs a revision request the head approves (QuotationRevisionService in the
     * CRM), which makes a new version.
     */
    public function isEditable(): bool
    {
        if ($this->isLeadQuotation()) {
            return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_RETURNED], true);
        }

        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SENT]);
    }

    public function isSuperseded(): bool
    {
        return $this->status === self::STATUS_SUPERSEDED;
    }

    /** The whole discount (lines + header) as a % of the subtotal. */
    public function discountPercent(): float
    {
        return (float) $this->subtotal > 0 ? round((float) $this->discount_amount / (float) $this->subtotal * 100, 2) : 0.0;
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast()
            && !in_array($this->status, [self::STATUS_ACCEPTED, self::STATUS_REJECTED, self::STATUS_EXPIRED, self::STATUS_SUPERSEDED]);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT            => 'Draft',
            self::STATUS_SENT             => 'Sent',
            self::STATUS_ACCEPTED         => 'Accepted',
            self::STATUS_REJECTED         => 'Rejected',
            self::STATUS_EXPIRED          => 'Expired',
            self::STATUS_PENDING_APPROVAL => 'Pending Approval',
            self::STATUS_RETURNED         => 'Returned',
            self::STATUS_APPROVED         => 'Approved',
            self::STATUS_SUPERSEDED       => 'Superseded',
            default                      => ucfirst($this->status),
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT            => '#64748b',
            self::STATUS_SENT             => '#3b82f6',
            self::STATUS_ACCEPTED         => '#10b981',
            self::STATUS_REJECTED         => '#ef4444',
            self::STATUS_EXPIRED          => '#f59e0b',
            self::STATUS_PENDING_APPROVAL => '#a855f7',
            self::STATUS_RETURNED         => '#f97316',
            self::STATUS_APPROVED         => '#0d9488',
            self::STATUS_SUPERSEDED       => '#94a3b8',
            default                      => '#64748b',
        };
    }
}
