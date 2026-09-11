<?php

namespace Modules\AutomationEditor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;

class AutomationFlow extends Model
{
    protected $table = 'automation_flows';

    protected $fillable = [
        'business_id', 'name', 'description',
        'is_active', 'trigger_type', 'trigger_config', 'flow_data',
        'run_count', 'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active'      => 'boolean',
            'trigger_config' => 'array',
            'flow_data'      => 'array',
            'run_count'      => 'integer',
            'last_run_at'    => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class, 'flow_id');
    }

    public static function availableTriggers(): array
    {
        return array_merge(...array_values(self::availableTriggerGroups()));
    }

    public static function availableTriggerGroups(): array
    {
        return [
            'Sales & Invoices' => [
                'sale.created'    => 'Sale Created',
                'sale.voided'     => 'Sale Voided',
                'sale.refunded'   => 'Sale Refunded',
                'invoice.created' => 'Invoice Created',
                'invoice.paid'    => 'Invoice Paid',
            ],
            'Products & Stock' => [
                'product.created'       => 'Product Created',
                'product.updated'       => 'Product Updated',
                'stock.updated'         => 'Stock Updated',
                'barcode.sheet.created' => 'Barcode Sheet Created',
                'stock.audit.finalized' => 'Stock Audit Finalized',
            ],
            'Purchasing' => [
                'grn.created'      => 'GRN Created',
                'order.created'    => 'Purchase Order Created',
                'supplier.created' => 'Supplier Created',
                'cheque.created'   => 'Cheque Created',
                'cheque.expired'   => 'Cheque Expired / Overdue',
            ],
            'Customers / CRM' => [
                'customer.created'        => 'Customer Created',
                'crm.lead.created'        => 'Lead Created (in Relation)',
                'crm.lead.stage_changed'  => 'Lead Stage Changed (in Relation)',
            ],
            'Cash & End of Day' => [
                'eod.withdraw' => 'Cash Withdrawal',
                'eod.settled'  => 'End-of-Day Settled to Bank',
            ],
            'Finance — Bills' => [
                'bill.created' => 'Bill Created',
                'bill.paid'    => 'Bill Payment Settled',
            ],
            'Finance — Loans' => [
                'loan.created'          => 'Loan Created',
                'loan.installment.paid' => 'Loan Installment Paid',
            ],
            'Finance — Property & Rental' => [
                'property.created' => 'Property Created',
                'rental.created'   => 'Rental Agreement Created',
                'rental.paid'      => 'Rental Payment Settled',
            ],
            'Notifications' => [
                'notification.created' => 'Notification Created',
            ],
            'Manual' => [
                'manual' => 'Manual / Button Trigger',
            ],
        ];
    }

    /**
     * Trigger keys that fire per CRM Relation (Project) and require the
     * flow to be scoped to one specific relation via trigger_config.relation_id.
     */
    public static function relationScopedTriggers(): array
    {
        return ['crm.lead.created', 'crm.lead.stage_changed'];
    }
}
