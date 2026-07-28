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
        'is_active', 'trigger_type', 'flow_data',
        'run_count', 'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active'   => 'boolean',
            'flow_data'   => 'array',
            'run_count'   => 'integer',
            'last_run_at' => 'datetime',
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
        return [
            // Sales & Invoices
            'sale.created'          => 'Sale Created',
            'sale.voided'           => 'Sale Voided',
            'sale.refunded'         => 'Sale Refunded',
            'invoice.created'       => 'Invoice Created',
            'invoice.paid'          => 'Invoice Paid',
            // Products & Stock
            'product.created'       => 'Product Created',
            'product.updated'       => 'Product Updated',
            'stock.updated'         => 'Stock Updated',
            'barcode.sheet.created' => 'Barcode Sheet Created',
            'stock.audit.finalized' => 'Stock Audit Finalized',
            // Purchasing
            'grn.created'           => 'GRN Created',
            'order.created'         => 'Purchase Order Created',
            'supplier.created'      => 'Supplier Created',
            'cheque.created'        => 'Cheque Created',
            'cheque.expired'        => 'Cheque Expired / Overdue',
            // Customers
            'customer.created'      => 'Customer Created',
            // Cash & End of Day
            'eod.withdraw'          => 'Cash Withdrawal',
            'eod.settled'           => 'End-of-Day Settled to Bank',
            // Finance — Bills
            'bill.created'          => 'Bill Created',
            'bill.paid'             => 'Bill Payment Settled',
            // Finance — Loans
            'loan.created'          => 'Loan Created',
            'loan.installment.paid' => 'Loan Installment Paid',
            // Finance — Property & Rental
            'property.created'      => 'Property Created',
            'rental.created'        => 'Rental Agreement Created',
            'rental.paid'           => 'Rental Payment Settled',
            // Manual
            'manual'                => 'Manual / Button Trigger',
        ];
    }
}
