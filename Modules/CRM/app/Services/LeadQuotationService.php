<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadStage;
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

        $quotation = $this->quotations->create($business, [
            ...$data,
            'lead_id'     => $lead->id,
            'customer_id' => $data['customer_id'] ?? $lead->customer_id,
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

        return $this->quotations->update($quotation, [
            ...$data,
            'customer_id' => array_key_exists('customer_id', $data) ? $data['customer_id'] : $quotation->customer_id,
        ], $items);
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
