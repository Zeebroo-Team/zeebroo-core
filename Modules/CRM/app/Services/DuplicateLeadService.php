<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadForm;
use Modules\CRM\Support\PhoneNormalizer;
use Modules\Pos\Models\Customer;

/**
 * Finds the leads and customers of a business that share a phone number (compared in
 * normalised form) or an email address (compared case-insensitively) with a contact.
 */
class DuplicateLeadService
{
    public function __construct(private readonly CrmSettingsService $settings) {}

    /** @return Collection<int, Lead> newest first, open leads before closed ones */
    public function matchingLeads(int $businessId, ?string $phone, ?string $email, ?int $ignoreLeadId = null): Collection
    {
        $normalized = $this->settings->normalizePhone($businessId, $phone);
        $email      = $this->cleanEmail($email);
        if ($normalized === null && $email === null) {
            return collect();
        }

        return Lead::query()
            ->where('business_id', $businessId)
            ->when($ignoreLeadId !== null, fn ($q) => $q->whereKeyNot($ignoreLeadId))
            // Supplier / employee submissions are not sales leads
            ->whereDoesntHave('form', fn ($q) => $q->whereIn('type', LeadForm::NON_SALES_TYPES))
            ->where(function ($q) use ($normalized, $email) {
                if ($normalized !== null) {
                    $q->orWhere('normalized_phone', $normalized);
                }
                if ($email !== null) {
                    $q->orWhereRaw('LOWER(email) = ?', [$email]);
                }
            })
            ->with(['stage', 'project:id,name,department_id'])
            ->orderByDesc('id')
            ->get()
            ->sortBy(fn (Lead $lead) => $lead->isOpen() ? 0 : 1)
            ->values();
    }

    /** @return Collection<int, Customer> newest first */
    public function matchingCustomers(int $businessId, ?string $phone, ?string $email, ?int $ignoreCustomerId = null): Collection
    {
        $normalized = $this->settings->normalizePhone($businessId, $phone);
        $email      = $this->cleanEmail($email);
        if ($normalized === null && $email === null) {
            return collect();
        }

        $tail = $normalized !== null ? PhoneNormalizer::tail($normalized) : null;

        return Customer::query()
            ->where('business_id', $businessId)
            ->when($ignoreCustomerId !== null, fn ($q) => $q->whereKeyNot($ignoreCustomerId))
            ->where(function ($q) use ($normalized, $tail, $email) {
                if ($normalized !== null) {
                    $q->orWhere('normalized_phone', $normalized)
                        // Rows written before normalized_phone existed (until crm:normalize-customer-phones
                        // runs): narrow down on the last digits with the usual separators stripped
                        ->orWhere(fn ($q) => $q->whereNull('normalized_phone')->whereNotNull('phone')->whereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '') LIKE ?",
                            ['%' . $tail],
                        ));
                }
                if ($email !== null) {
                    // Emails are saved trimmed and lower-cased (CustomerService::normalizeContact)
                    $q->orWhere('email', $email);
                }
            })
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->filter(fn (Customer $c) => ($email !== null && $this->cleanEmail($c->email) === $email)
                || ($normalized !== null && ($c->normalized_phone ?? $this->settings->normalizePhone($businessId, $c->phone)) === $normalized))
            ->values();
    }

    public function cleanEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }
}
