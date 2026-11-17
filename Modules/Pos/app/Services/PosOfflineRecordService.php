<?php

namespace Modules\Pos\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Account\Models\Bill;
use Modules\Account\Services\BillService;
use Modules\Business\Models\Business;
use Modules\Pos\Models\Customer;
use Modules\Pos\Models\CustomerCategory;

/**
 * Saves customers and expenses the mobile app recorded while offline.
 *
 * Every record carries the phone's client_uuid, so uploading the same record
 * twice (the connection dropped before the phone got the answer) returns the
 * one already saved instead of creating a duplicate.
 */
class PosOfflineRecordService
{
    public function __construct(private readonly BillService $bills)
    {
    }

    /**
     * @param  array{client_uuid: string, name: string, phone?: ?string, email?: ?string, address?: ?string,
     *               notes?: ?string, customer_type?: ?string, customer_category_id?: ?int}  $data
     * @return array{customer: Customer, duplicate: bool, matched: bool}
     */
    public function saveCustomer(Business $business, array $data): array
    {
        if ($existing = $this->customerByUuid($business, $data['client_uuid'])) {
            return ['customer' => $existing, 'duplicate' => true, 'matched' => false];
        }

        // The same person may already have been added on another device or on
        // the web while this phone was offline — reuse them instead of
        // creating a second record with the same phone number.
        $phone = trim((string) ($data['phone'] ?? ''));
        if ($phone !== '') {
            $samePhone = Customer::query()->where('business_id', $business->id)->where('phone', $phone)->limit(2)->get();
            if ($samePhone->count() === 1) {
                return ['customer' => $samePhone->first(), 'duplicate' => false, 'matched' => true];
            }
        }

        // A category deleted while the phone was offline must not block the upload.
        $categoryId = $data['customer_category_id'] ?? null;
        if ($categoryId !== null && ! CustomerCategory::query()->where('business_id', $business->id)->whereKey($categoryId)->exists()) {
            $categoryId = null;
        }

        try {
            $customer = Customer::create([
                'business_id'          => $business->id,
                'client_uuid'          => $data['client_uuid'],
                'name'                 => $data['name'],
                'phone'                => $phone !== '' ? $phone : null,
                'email'                => $data['email'] ?? null,
                'address'              => $data['address'] ?? null,
                'notes'                => $data['notes'] ?? null,
                'customer_type'        => $data['customer_type'] ?? 'retail',
                'customer_category_id' => $categoryId,
            ]);
        } catch (QueryException $e) {
            // Two uploads raced; the unique index stopped the second one.
            if ($existing = $this->customerByUuid($business, $data['client_uuid'])) {
                return ['customer' => $existing, 'duplicate' => true, 'matched' => false];
            }
            throw $e;
        }

        try {
            app(\Modules\AutomationEditor\Services\AutomationRunnerService::class)->dispatch('customer.created', $business, [
                'event'    => 'customer.created',
                'customer' => ['id' => $customer->id, 'name' => $customer->name, 'email' => $customer->email, 'phone' => $customer->phone, 'created_at' => $customer->created_at?->toIso8601String()],
            ]);
        } catch (\Throwable) {}

        return ['customer' => $customer, 'duplicate' => false, 'matched' => false];
    }

    /**
     * Records an offline expense as a one-time bill due on the day it was
     * spent. Paying it (which picks an account and checks budgets) is left to
     * the normal Bills screen once the phone is online.
     *
     * @param  array{client_uuid: string, name: string, amount: float|int|string, bill_category: string,
     *               bill_category_other?: ?string, notes?: ?string}  $data
     * @return array{bill: Bill, duplicate: bool}
     */
    public function saveExpense(User $user, Business $business, array $data, CarbonImmutable $spentOn): array
    {
        if ($existing = $this->billByUuid($business, $data['client_uuid'])) {
            return ['bill' => $existing, 'duplicate' => true];
        }

        try {
            $bill = DB::transaction(function () use ($user, $business, $data, $spentOn) {
                $bill = $this->bills->create($user, $business, [
                    'name'                       => $data['name'],
                    'payment_mode'               => Bill::PAYMENT_MODE_ONE_TIME,
                    'bill_category'              => $data['bill_category'],
                    'bill_category_other'        => $data['bill_category'] === Bill::CATEGORY_OTHER ? ($data['bill_category_other'] ?? null) : null,
                    'notes'                      => $data['notes'] ?? null,
                    'recurring_cost'             => round((float) $data['amount'], 2),
                    'recurring_type'             => Bill::RECURRING_PER_MONTH,
                    'agreement_valid_until_year' => (int) $spentOn->format('Y'),
                    'due_date'                   => $spentOn->toDateString(),
                    'amount_varies_by_usage'     => false,
                    'allow_split_payment'        => true,
                    'rental_property_related'    => false,
                ]);
                // Not in Bill's fillable (the Account module stays unchanged).
                $bill->forceFill(['client_uuid' => $data['client_uuid']])->save();

                return $bill;
            });
        } catch (QueryException $e) {
            if ($existing = $this->billByUuid($business, $data['client_uuid'])) {
                return ['bill' => $existing, 'duplicate' => true];
            }
            throw $e;
        }

        return ['bill' => $bill, 'duplicate' => false];
    }

    private function customerByUuid(Business $business, string $uuid): ?Customer
    {
        return Customer::query()->where('business_id', $business->id)->where('client_uuid', $uuid)->first();
    }

    private function billByUuid(Business $business, string $uuid): ?Bill
    {
        return Bill::query()->where('business_id', $business->id)->where('client_uuid', $uuid)->first();
    }
}
