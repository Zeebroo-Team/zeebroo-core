<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Modules\CRM\Models\Lead;
use Modules\Pos\Models\Customer;

/**
 * The customer master record (pos_customers) as the CRM sees it: one record per person or
 * company, found again by phone (normalised) or email wherever a contact comes in.
 */
class CustomerService
{
    public function __construct(
        private readonly DuplicateLeadService $duplicates,
        private readonly CrmSettingsService $settings,
    ) {}

    /** Runs on every save: email trimmed and lower-cased, phone kept in normalised form for matching. */
    public function normalizeContact(Customer $customer): void
    {
        if ($customer->isDirty('email')) {
            $customer->email = $this->duplicates->cleanEmail($customer->email);
        }
        if ($customer->isDirty('phone') || ! $customer->exists || ($customer->phone !== null && $customer->normalized_phone === null)) {
            $customer->normalized_phone = $customer->business_id
                ? $this->settings->normalizePhone((int) $customer->business_id, $customer->phone)
                : null;
        }
    }

    /** @return Collection<int, Customer> customers of the business sharing the phone or email, newest first */
    public function duplicatesOf(int $businessId, ?string $phone, ?string $email, ?int $ignoreCustomerId = null): Collection
    {
        return $this->duplicates->matchingCustomers($businessId, $phone, $email, $ignoreCustomerId);
    }

    /**
     * Duplicates as the create / edit forms show them, with what matched.
     *
     * @return list<array{id: int, name: string, phone: ?string, email: ?string, matched_on: list<string>}>
     */
    public function presentDuplicates(int $businessId, ?string $phone, ?string $email, ?int $ignoreCustomerId = null): array
    {
        $normalized = $this->settings->normalizePhone($businessId, $phone);
        $email      = $this->duplicates->cleanEmail($email);

        return $this->duplicatesOf($businessId, $phone, $email, $ignoreCustomerId)
            ->take(5)
            ->map(fn (Customer $c) => [
                'id'         => $c->id,
                'name'       => $c->name,
                'phone'      => $c->phone,
                'email'      => $c->email,
                'matched_on' => array_values(array_filter([
                    $normalized !== null && ($c->normalized_phone ?? $this->settings->normalizePhone($businessId, $c->phone)) === $normalized ? 'phone' : null,
                    $email !== null && $this->duplicates->cleanEmail($c->email) === $email ? 'email' : null,
                ])),
            ])
            ->values()
            ->all();
    }

    /** "Already a customer: Kasun Perera (phone)." for a form warning, or null when there is none. */
    public function duplicateWarning(array $duplicates): ?string
    {
        if ($duplicates === []) {
            return null;
        }

        $names = array_map(fn ($d) => $d['name'] . ' (' . implode(' & ', $d['matched_on']) . ')', $duplicates);

        return (count($duplicates) === 1 ? 'Already a customer with this ' : 'Already customers with this ')
            . 'phone / email: ' . implode(', ', $names) . '.';
    }

    /**
     * The customer a converted lead becomes: the one it is already linked to, else an existing
     * customer with the same phone or email (blank contact details filled in from the lead),
     * else a new one. `wasRecentlyCreated` tells which.
     */
    public function forLead(Lead $lead): Customer
    {
        $customer = $lead->customer_id
            ? Customer::query()->where('business_id', $lead->business_id)->find($lead->customer_id)
            : null;

        $customer ??= $this->duplicatesOf((int) $lead->business_id, $lead->phone, $lead->email)->first();

        if ($customer === null) {
            return Customer::create([
                'business_id' => $lead->business_id,
                'name'        => $lead->company ?: $lead->name,
                'phone'       => $lead->phone,
                'email'       => $lead->email,
                'notes'       => $lead->notes,
            ]);
        }

        $customer->fill(array_filter([
            'phone' => $customer->phone ? null : $lead->phone,
            'email' => $customer->email ? null : $lead->email,
        ]));
        if ($customer->isDirty()) {
            $customer->save();
        }

        return $customer;
    }
}
