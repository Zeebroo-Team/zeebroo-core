<?php

namespace Modules\Payment\Services;

use Modules\Business\Models\Business;
use Modules\Package\Models\Package;
use Modules\Payment\Models\Payment;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Event;
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
}
