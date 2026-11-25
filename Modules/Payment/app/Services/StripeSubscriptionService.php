<?php

namespace Modules\Payment\Services;

use Modules\Business\Models\Business;
use Modules\Package\Models\Package;
use Modules\Payment\Models\Payment;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Event;
use Stripe\Invoice;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeSubscriptionService
{
    private ?StripeClient $client = null;

    /**
     * Built lazily so injecting this service doesn't fail for flows that
     * never touch Stripe (e.g. free-package onboarding) when STRIPE_SECRET
     * hasn't been configured yet.
     */
    private function client(): StripeClient
    {
        if ($this->client === null) {
            $secret = (string) config('services.stripe.secret');
            if ($secret === '') {
                throw new \RuntimeException('Stripe secret key is not configured (STRIPE_SECRET).');
            }
            $this->client = new StripeClient($secret);
        }

        return $this->client;
    }

    /**
     * Create a Stripe Checkout Session for a monthly recurring subscription
     * against the given package price, tied to the given pending Payment row.
     */
    public function createSubscriptionCheckoutSession(
        Payment $payment,
        Business $business,
        Package $package,
        float $amount,
        string $successUrl,
        string $cancelUrl,
    ): CheckoutSession {
        return $this->client()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer_email' => $payment->user?->email,
            'client_reference_id' => (string) $payment->id,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $payment->currency ?? 'usd',
                    'unit_amount' => (int) round($amount * 100),
                    'recurring' => ['interval' => 'month'],
                    'product_data' => [
                        'name' => $package->name.' plan (monthly)',
                        'description' => 'Monthly subscription for '.$business->name,
                        // Required by Stripe's Managed Payments (enabled by
                        // default on this account) for ad-hoc price_data line
                        // items — SaaS/subscription software tax category.
                        'tax_code' => 'txcd_10103000',
                    ],
                ],
            ]],
            'success_url' => $successUrl.'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'payment_id' => (string) $payment->id,
                'business_id' => (string) $business->id,
                'package_id' => (string) $package->id,
            ],
            'subscription_data' => [
                'metadata' => [
                    'payment_id' => (string) $payment->id,
                    'business_id' => (string) $business->id,
                    'package_id' => (string) $package->id,
                ],
            ],
        ]);
    }

    /**
     * One-off Checkout (mode=payment) for the first month of extra user seats.
     * The recurring part is added to the subscription afterwards by
     * upsertExtraUsersItem(), once Stripe confirms this charge.
     */
    public function createExtraUsersCheckoutSession(
        Payment $payment,
        Business $business,
        Package $package,
        int $quantity,
        float $unitPrice,
        ?string $customerId,
        string $successUrl,
        string $cancelUrl,
    ): CheckoutSession {
        $metadata = [
            'payment_id' => (string) $payment->id,
            'business_id' => (string) $business->id,
            'package_id' => (string) $package->id,
            'kind' => Payment::TYPE_EXTRA_USERS,
            'quantity' => (string) $quantity,
        ];

        $params = [
            'mode' => 'payment',
            'client_reference_id' => (string) $payment->id,
            'line_items' => [[
                'quantity' => $quantity,
                'price_data' => [
                    'currency' => $payment->currency ?? 'usd',
                    'unit_amount' => (int) round($unitPrice * 100),
                    'product_data' => [
                        'name' => 'Extra user — '.$package->name.' plan',
                        'description' => 'Additional user seat for '.$business->name.' (first month)',
                        'tax_code' => 'txcd_10103000',
                    ],
                ],
            ]],
            'success_url' => $successUrl.'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'metadata' => $metadata,
            'payment_intent_data' => ['metadata' => $metadata],
        ];

        // Reuse the subscription's customer so the charge shows up on the same Stripe account.
        if ($customerId) {
            $params['customer'] = $customerId;
        } else {
            $params['customer_email'] = $payment->user?->email;
        }

        return $this->client()->checkout->sessions->create($params);
    }

    /**
     * Sets the subscription's "Extra users" item to `$seats` × `$unitPrice` per
     * month, creating it on first use. No proration — the current month was
     * already paid through createExtraUsersCheckoutSession(), so the new
     * amount applies from the next renewal.
     */
    public function upsertExtraUsersItem(string $subscriptionId, int $seats, float $unitPrice, string $currency, string $productName): void
    {
        $subscription = $this->retrieveSubscription($subscriptionId);

        $existing = null;
        foreach ($subscription->items->data ?? [] as $item) {
            if (($item->metadata['kind'] ?? null) === Payment::TYPE_EXTRA_USERS) {
                $existing = $item;
                break;
            }
        }

        if ($seats <= 0) {
            if ($existing) {
                $this->client()->subscriptions->update($subscriptionId, [
                    'items' => [['id' => $existing->id, 'deleted' => true]],
                    'proration_behavior' => 'none',
                ]);
            }

            return;
        }

        $productId = $existing
            ? (is_string($existing->price->product) ? $existing->price->product : $existing->price->product->id)
            : $this->client()->products->create([
                'name' => $productName,
                'tax_code' => 'txcd_10103000',
            ])->id;

        $item = [
            'quantity' => $seats,
            'price_data' => [
                'currency' => strtolower($currency),
                'product' => $productId,
                'unit_amount' => (int) round($unitPrice * 100),
                'recurring' => ['interval' => 'month'],
            ],
            'metadata' => ['kind' => Payment::TYPE_EXTRA_USERS],
        ];
        if ($existing) {
            $item['id'] = $existing->id;
        }

        $this->client()->subscriptions->update($subscriptionId, [
            'items' => [$item],
            'proration_behavior' => 'none',
        ]);
    }

    /**
     * Schedule (or undo) cancellation at the end of the paid period — the
     * customer keeps access until then and is not charged again.
     */
    public function setCancelAtPeriodEnd(string $subscriptionId, bool $cancel): \Stripe\Subscription
    {
        return $this->client()->subscriptions->update($subscriptionId, [
            'cancel_at_period_end' => $cancel,
        ]);
    }

    public function retrieveSubscription(string $subscriptionId): \Stripe\Subscription
    {
        return $this->client()->subscriptions->retrieve($subscriptionId);
    }

    public function retrieveSession(string $sessionId): CheckoutSession
    {
        return $this->client()->checkout->sessions->retrieve($sessionId, [
            'expand' => ['subscription', 'payment_intent'],
        ]);
    }

    public function constructWebhookEvent(string $payload, string $sigHeader, string $secret): Event
    {
        return Webhook::constructEvent($payload, $sigHeader, $secret);
    }

    /**
     * Invoice-related webhook payloads vary in how much they carry inline —
     * an `invoice.paid`/`invoice.payment_failed` event's object already has
     * `subscription`/`customer`/`billing_reason`, but the newer InvoicePayment
     * object (`invoice_payment.paid`) only references its parent invoice by
     * ID. Always retrieving the full Invoice here keeps the webhook handler
     * logic identical regardless of which event triggered it.
     */
    public function retrieveInvoice(string $invoiceId): Invoice
    {
        return $this->client()->invoices->retrieve($invoiceId);
    }

    /**
     * A subscription's invoices created at or after `$since` (newest first) —
     * used by the payment check to find renewals the webhook may have missed.
     *
     * @return list<Invoice>
     */
    public function invoicesForSubscription(string $subscriptionId, \DateTimeInterface $since, int $limit = 10): array
    {
        $page = $this->client()->invoices->all([
            'subscription' => $subscriptionId,
            'created' => ['gte' => $since->getTimestamp()],
            'limit' => $limit,
        ]);

        return $page->data;
    }
}
