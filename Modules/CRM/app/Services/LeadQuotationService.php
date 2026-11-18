<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadStage;
use Modules\Pos\Models\Customer;
use Modules\Sales\Models\Quotation;
use Modules\Sales\Services\QuotationService;
use Modules\Sales\Services\QuotationStockService;

/**
 * Quotations built from inventory inside a lead. The quotation itself is the Sales module's
 * (QuotationService); this adds the lead link, the stock check (warn / block / off, from the
 * CRM settings) and the lead status moves: creating or revising a quotation puts the lead in
 * "Quotation Preparing". Who may do it is LeadPolicy::canQuote, checked by the caller.
 */
class LeadQuotationService
{
    public function __construct(
        private readonly QuotationService $quotations,
        private readonly QuotationStockService $stock,
        private readonly CrmSettingsService $settings,
        private readonly LeadService $leads,
        private readonly LeadStageService $stages,
        private readonly CustomerService $customers,
    ) {}

    /** @return Collection<int, Quotation> newest first */
    public function forLead(Lead $lead): Collection
    {
        return Quotation::query()
            ->where('business_id', $lead->business_id)
            ->where('lead_id', $lead->id)
            ->with(['submittedBy:id,name', 'approvedBy:id,name'])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Each lead's accepted quotation total (the latest accepted one wins over older revisions),
     * keyed by lead id. Leads with no accepted quotation are left out.
     *
     * @param  list<int>  $leadIds
     * @return array<int, float>
     */
    public function acceptedTotals(array $leadIds): array
    {
        if ($leadIds === []) {
            return [];
        }

        return Quotation::query()
            ->whereIn('lead_id', $leadIds)
            ->where('status', Quotation::STATUS_ACCEPTED)
            ->orderBy('id')
            ->get(['lead_id', 'total'])
            ->mapWithKeys(fn (Quotation $q) => [(int) $q->lead_id => (float) $q->total])
            ->all();
    }

    public function quotationForLead(Lead $lead, int $quotationId): Quotation
    {
        return Quotation::query()
            ->where('business_id', $lead->business_id)
            ->where('lead_id', $lead->id)
            ->findOrFail($quotationId);
    }

    /**
     * Stock of each product line against the business's rule.
     *
     * @return array{mode: string, has_shortage: bool, lines: list<array<string, mixed>>}
     */
    public function stockCheck(Business $business, array $items, ?int $branchId = null): array
    {
        $mode  = $this->settings->quotationStockCheck($business);
        $lines = $mode === CrmSetting::STOCK_CHECK_OFF ? [] : $this->stock->check($business, $items, $branchId);

        return [
            'mode'         => $mode,
            'has_shortage' => collect($lines)->contains('short', true),
            'lines'        => $lines,
        ];
    }

    public function create(Lead $lead, array $data, array $items, ?int $userId = null): Quotation
    {
        $business = $lead->business()->firstOrFail();
        $this->guardStock($business, $items, $data['branch_id'] ?? null);

        return DB::transaction(fn () => $this->createQuotation($business, $lead, $data, $items, $userId));
    }

    private function createQuotation(Business $business, Lead $lead, array $data, array $items, ?int $userId): Quotation
    {
        $customerId = $this->saveCustomer($lead, $data) ?? $data['customer_id'] ?? $lead->customer_id;
        unset($data['customer']);

        $quotation = $this->quotations->create($business, [
            ...$data,
            'lead_id'     => $lead->id,
            'customer_id' => $customerId,
            // Not sent at all → the linked customer's address; sent empty → left blank on purpose
            'customer_address' => array_key_exists('customer_address', $data) ? $data['customer_address'] : $this->customerAddress($lead),
            'reference'   => filled($data['reference'] ?? null) ? $data['reference'] : $this->defaultReference($lead),
            'version'     => 1,
        ], $items);

        $this->moveLeadTo($lead, LeadStage::STATUS_QUOTATION_PREPARING, $userId);
        $lead->recordFirstResponse();

        return $quotation;
    }

    public function update(Lead $lead, Quotation $quotation, array $data, array $items): Quotation
    {
        $this->guardStock($lead->business()->firstOrFail(), $items, $data['branch_id'] ?? $quotation->branch_id);

        return DB::transaction(function () use ($lead, $quotation, $data, $items) {
            $customerId = $this->saveCustomer($lead, $data)
                ?? (array_key_exists('customer_id', $data) ? $data['customer_id'] : $quotation->customer_id);
            unset($data['customer']);

            return $this->quotations->update($quotation, [...$data, 'customer_id' => $customerId], $items);
        });
    }

    /**
     * Saved customers matching what is typed in the quotation's customer field (name, phone,
     * email, industry), for the auto-suggest.
     *
     * @return list<array<string, mixed>>
     */
    public function searchCustomers(int $businessId, string $q, int $limit = 8): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $like = '%' . addcslashes($q, '%_\\') . '%';

        return Customer::query()
            ->where('business_id', $businessId)
            ->where(fn ($w) => $w->where('name', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('industry', 'like', $like))
            // Names starting with the text first, then the rest alphabetically
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [addcslashes($q, '%_\\') . '%'])
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Customer $c) => $this->presentCustomer($c))
            ->all();
    }

    /** @return array{id: int, name: string, phone: ?string, email: ?string, address: ?string, industry: ?string}|null */
    public function presentCustomer(?Customer $c): ?array
    {
        return $c === null ? null : [
            'id'       => (int) $c->id,
            'name'     => $c->name,
            'phone'    => $c->phone,
            'email'    => $c->email,
            'address'  => $c->address,
            'industry' => $c->industry,
        ];
    }

    /**
     * What a new quotation's customer section starts with, the first found of: the lead's linked
     * customer, the customer of the lead's latest quotation, a saved customer with the lead's
     * phone / email — else the lead's own contact details (saved as a customer on save).
     */
    public function customerDefaults(Lead $lead): array
    {
        $businessId = (int) $lead->business_id;
        $customer   = $lead->customer_id
            ? Customer::query()->where('business_id', $businessId)->find($lead->customer_id)
            : null;

        if ($customer === null) {
            $lastCustomerId = Quotation::query()
                ->where('business_id', $businessId)
                ->where('lead_id', $lead->id)
                ->whereNotNull('customer_id')
                ->latest('id')
                ->value('customer_id');
            $customer = $lastCustomerId ? Customer::query()->where('business_id', $businessId)->find($lastCustomerId) : null;
        }

        $customer ??= $this->customers->duplicatesOf($businessId, $lead->phone, $lead->email)->first();

        return $this->presentCustomer($customer) ?? [
            'id'       => null,
            // The person on the lead, as shown in its header (not the company)
            'name'     => $lead->name ?: $lead->company,
            'phone'    => $lead->phone,
            'email'    => $lead->email,
            'address'  => null,
            'industry' => null,
        ];
    }

    /**
     * Saves the quotation form's customer details to the customer master (pos_customers):
     * - customer_id given → that customer, with the details typed in written back to it;
     * - none → an existing customer with the same phone / email (blank fields filled in), else a new one.
     * Returns the customer id, or null when the form sent no customer details at all.
     */
    private function saveCustomer(Lead $lead, array $data): ?int
    {
        $details = $data['customer'] ?? null;
        if (! is_array($details)) {
            return null;
        }
        $fields = array_map(
            fn ($v) => is_string($v) && trim($v) !== '' ? trim($v) : null,
            array_intersect_key($details, array_flip(['name', 'phone', 'email', 'address', 'industry'])),
        );
        $businessId = (int) $lead->business_id;

        if (! empty($data['customer_id'])) {
            $customer = Customer::query()->where('business_id', $businessId)->findOrFail($data['customer_id']);
            // Typed-in values replace what is on file; empty fields leave it as it was
            $customer->fill(array_filter($fields, fn ($v) => $v !== null));
            if ($customer->isDirty()) {
                $customer->save();
            }

            return (int) $customer->id;
        }

        if ($fields['name'] === null) {
            return null;
        }

        $customer = $this->customers->duplicatesOf($businessId, $fields['phone'] ?? null, $fields['email'] ?? null)->first();
        if ($customer === null) {
            return (int) Customer::create(['business_id' => $businessId, ...$fields])->id;
        }

        // Already on file (same phone / email): only fill in what it is missing
        $customer->fill(array_filter($fields, fn ($v, $k) => $v !== null && blank($customer->{$k}), ARRAY_FILTER_USE_BOTH));
        if ($customer->isDirty()) {
            $customer->save();
        }

        return (int) $customer->id;
    }

    /**
     * A new draft version of an approved / sent / rejected quotation (the old one becomes
     * superseded); the lead goes back to preparing.
     */
    public function revise(Lead $lead, Quotation $quotation, ?int $userId = null): Quotation
    {
        $revision = $this->quotations->revise($quotation);
        $this->moveLeadTo($lead, LeadStage::STATUS_QUOTATION_PREPARING, $userId);

        return $revision;
    }

    /**
     * Move an open lead to the pipeline's stage for a status, as the quotation workflow does.
     * A pipeline without that stage, or a lead already there or closed, is left as it is.
     */
    public function moveLeadTo(Lead $lead, string $statusKey, ?int $userId = null, ?string $reason = null, array $context = []): Lead
    {
        $lead->loadMissing(['stage', 'project']);
        if (! $lead->isOpen() || $lead->statusKey() === $statusKey || $lead->project === null
            || $this->stages->stageForStatus($lead->project, $statusKey) === null) {
            return $lead;
        }

        return $this->leads->moveToStatus($lead, $statusKey, $userId, array_filter(['reason' => $reason]) + $context, system: true);
    }

    /** The address on file for the lead's customer — what a new quotation is billed to by default. */
    public function customerAddress(Lead $lead): ?string
    {
        return $lead->customer_id ? ($lead->customer()->value('address') ?: null) : null;
    }

    /** "Kasun Perera (Lead #12)" — the quotation's reference when none is given. */
    private function defaultReference(Lead $lead): string
    {
        return mb_substr(trim(($lead->company ?: $lead->name) . ' (Lead #' . $lead->id . ')'), 0, 120);
    }

    private function guardStock(Business $business, array $items, mixed $branchId): void
    {
        $check = $this->stockCheck($business, $items, $branchId !== null && $branchId !== '' ? (int) $branchId : null);
        if ($check['mode'] !== CrmSetting::STOCK_CHECK_BLOCK || ! $check['has_shortage']) {
            return;
        }

        $errors = [];
        foreach ($check['lines'] as $line) {
            if ($line['short']) {
                $errors['items.' . $line['index'] . '.quantity'] = 'Only ' . rtrim(rtrim(number_format($line['available'], 3, '.', ''), '0'), '.')
                    . ' in stock (' . rtrim(rtrim(number_format($line['requested'], 3, '.', ''), '0'), '.') . ' quoted).';
            }
        }

        throw ValidationException::withMessages($errors);
    }
}
