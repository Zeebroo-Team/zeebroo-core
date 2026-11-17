<?php

namespace Modules\Ecommerce\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Ecommerce\Models\Order;
use Modules\Ecommerce\Models\PaymentGateway;
use Modules\Ecommerce\Models\Store;
use Stripe\StripeClient;

/**
 * Online payment gateways for the storefront. Each business connects its OWN
 * merchant account (Stripe / PayHere) — money goes straight to the merchant.
 */
class PaymentGatewayService
{
    private const PAYHERE_LIVE    = 'https://www.payhere.lk/pay/checkout';
    private const PAYHERE_SANDBOX = 'https://sandbox.payhere.lk/pay/checkout';

    /** Every catalog gateway for the business (unsaved defaults for ones never configured). */
    public function all(Business $business): Collection
    {
        $rows = PaymentGateway::where('business_id', $business->id)->get()->keyBy('gateway');

        return collect(array_keys(PaymentGateway::CATALOG))->map(fn ($key) => $rows[$key]
            ?? new PaymentGateway(['business_id' => $business->id, 'gateway' => $key, 'is_enabled' => false, 'is_sandbox' => true]));
    }

    public function find(Business $business, string $gateway): ?PaymentGateway
    {
        return PaymentGateway::where('business_id', $business->id)->where('gateway', $gateway)->first();
    }

    /** @return string[] online gateway keys the storefront may offer right now */
    public function usableKeys(Business|int $business): array
    {
        $id = $business instanceof Business ? $business->id : $business;

        return PaymentGateway::where('business_id', $id)->where('is_enabled', true)->get()
            ->filter->isUsable()->pluck('gateway')->values()->all();
    }

    /**
     * Save settings. Blank secret fields keep the stored value, so the desktop
     * client never needs to read secrets back.
     */
    public function update(Business $business, string $gateway, array $data): PaymentGateway
    {
        $meta = PaymentGateway::CATALOG[$gateway];
        $row  = PaymentGateway::firstOrNew(['business_id' => $business->id, 'gateway' => $gateway]);

        $creds = $row->credentials ?? [];
        foreach ($meta['public'] as $f) {
            if (array_key_exists($f, $data)) {
                $creds[$f] = trim((string) $data[$f]);
            }
        }
        foreach ($meta['secret'] as $f) {
            if (! empty($data[$f])) {
                $creds[$f] = trim((string) $data[$f]);
            }
        }
        $row->credentials = $creds;

        if (array_key_exists('is_sandbox', $data)) {
            $row->is_sandbox = (bool) $data['is_sandbox'];
        }
        if (array_key_exists('is_enabled', $data)) {
            $row->is_enabled = (bool) $data['is_enabled'];
        }

        if ($row->is_enabled && ! $row->isConfigured()) {
            throw ValidationException::withMessages(['is_enabled' => "Enter all {$meta['name']} credentials before enabling it."]);
        }

        $this->assertKeyMode($row);
        $row->save();

        return $row;
    }

    /** Stripe test keys must be used in sandbox and live keys in live mode — catches the most common setup mistake. */
    private function assertKeyMode(PaymentGateway $row): void
    {
        if ($row->gateway !== 'stripe' || ! $row->isConfigured()) {
            return;
        }
        $pk = (string) $row->credential('publishable_key');
        $sk = (string) $row->credential('secret_key');
        if (! str_starts_with($pk, 'pk_') || ! (str_starts_with($sk, 'sk_') || str_starts_with($sk, 'rk_'))) {
            throw ValidationException::withMessages(['secret_key' => 'Stripe keys should start with pk_ (publishable) and sk_ (secret).']);
        }
        $testKeys = str_contains($sk, '_test_');
        if ($testKeys !== (bool) $row->is_sandbox) {
            throw ValidationException::withMessages(['is_sandbox' => $testKeys
                ? 'These are Stripe TEST keys — turn on sandbox mode, or paste your live keys.'
                : 'These are Stripe LIVE keys — turn off sandbox mode, or paste your test keys.']);
        }
    }

    /**
     * Begin an online payment for a freshly placed order. Returns either
     * `redirect_url` (Stripe Checkout) or a `form` the browser auto-submits (PayHere).
     */
    public function start(Store $store, Order $order): array
    {
        $gateway = $this->find($store->business, $order->payment_method);
        if (! $gateway || ! $gateway->isUsable()) {
            throw ValidationException::withMessages(['payment_method' => 'That payment method is not available.']);
        }

        $currency = strtoupper((string) $store->currency);
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw ValidationException::withMessages(['payment_method' => 'Online payment is not set up correctly for this store (currency).']);
        }

        $returnUrl = route('ecommerce.storefront.order', ['slug' => $store->slug, 'token' => $order->access_token]);

        return match ($order->payment_method) {
            'stripe'  => $this->startStripe($gateway, $store, $order, $currency, $returnUrl),
            'payhere' => $this->startPayhere($gateway, $store, $order, $currency, $returnUrl),
        };
    }

    private function startStripe(PaymentGateway $g, Store $store, Order $order, string $currency, string $returnUrl): array
    {
        $session = (new StripeClient($g->credential('secret_key')))->checkout->sessions->create([
            'mode'                => 'payment',
            'customer_email'      => $order->customer_email ?: null,
            'client_reference_id' => (string) $order->id,
            'line_items'          => [[
                'quantity'   => 1,
                'price_data' => [
                    'currency'     => strtolower($currency),
                    'unit_amount'  => (int) round((float) $order->total * 100),
                    'product_data' => ['name' => "{$store->name} — order {$order->order_no}"],
                ],
            ]],
            'success_url' => $returnUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => $returnUrl . '?cancelled=1',
            'metadata'    => ['ecom_order_id' => (string) $order->id, 'order_no' => $order->order_no],
        ]);

        $order->update(['payment_reference' => $session->id]);

        return ['redirect_url' => $session->url];
    }

    private function startPayhere(PaymentGateway $g, Store $store, Order $order, string $currency, string $returnUrl): array
    {
        $merchantId = $g->credential('merchant_id');
        $amount     = number_format((float) $order->total, 2, '.', '');
        $names      = preg_split('/\s+/', trim($order->customer_name), 2);

        return ['form' => [
            'action' => $g->is_sandbox ? self::PAYHERE_SANDBOX : self::PAYHERE_LIVE,
            'fields' => [
                'merchant_id' => $merchantId,
                'return_url'  => $returnUrl,
                'cancel_url'  => $returnUrl . '?cancelled=1',
                'notify_url'  => route('api.ecommerce.payhere.notify', ['slug' => $store->slug]),
                'order_id'    => $order->order_no,
                'items'       => "{$store->name} order {$order->order_no}",
                'currency'    => $currency,
                'amount'      => $amount,
                'first_name'  => $names[0] ?? $order->customer_name,
                'last_name'   => $names[1] ?? '',
                'email'       => $order->customer_email ?: ($store->contact_email ?: 'customer@example.com'),
                'phone'       => $order->customer_phone,
                'address'     => $order->shipping_address,
                'city'        => $order->shipping_city ?: '-',
                'country'     => 'Sri Lanka',
                'hash'        => strtoupper(md5($merchantId . $order->order_no . $amount . $currency . strtoupper(md5($g->credential('merchant_secret'))))),
            ],
        ]];
    }

    /** Confirm a Stripe Checkout return by asking Stripe directly (never trust the query string). */
    public function confirmStripe(Store $store, Order $order, string $sessionId): bool
    {
        if ($order->payment_status === 'paid') {
            return true;
        }
        if ($order->payment_method !== 'stripe' || $sessionId !== $order->payment_reference) {
            return false;
        }
        $g = $this->find($store->business, 'stripe');
        if (! $g || ! $g->isConfigured()) {
            return false;
        }

        try {
            $session = (new StripeClient($g->credential('secret_key')))->checkout->sessions->retrieve($sessionId);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }

        if ($session->payment_status === 'paid' && (string) ($session->metadata['ecom_order_id'] ?? '') === (string) $order->id) {
            $this->markPaid($order, (string) ($session->payment_intent ?? $sessionId));

            return true;
        }

        return false;
    }

    /** PayHere server-to-server notification; verified with the md5sig. */
    public function handlePayhereNotify(Store $store, array $data): bool
    {
        $g = $this->find($store->business, 'payhere');
        if (! $g || ! $g->isConfigured()) {
            return false;
        }

        $merchantId = (string) ($data['merchant_id'] ?? '');
        $orderNo    = (string) ($data['order_id'] ?? '');
        $amount     = (string) ($data['payhere_amount'] ?? '');
        $currency   = (string) ($data['payhere_currency'] ?? '');
        $statusCode = (string) ($data['status_code'] ?? '');
        $expected   = strtoupper(md5($merchantId . $orderNo . $amount . $currency . $statusCode . strtoupper(md5($g->credential('merchant_secret')))));

        if ($merchantId !== $g->credential('merchant_id') || ! hash_equals($expected, strtoupper((string) ($data['md5sig'] ?? '')))) {
            return false;
        }

        $order = Order::where('business_id', $store->business_id)->where('order_no', $orderNo)->first();
        if (! $order || $order->payment_method !== 'payhere') {
            return false;
        }

        // Amount/currency must match what we asked for.
        if (abs((float) $amount - (float) $order->total) > 0.009 || strtoupper($currency) !== strtoupper((string) $store->currency)) {
            return false;
        }

        if ($statusCode === '2') {
            $this->markPaid($order, (string) ($data['payment_id'] ?? $orderNo));
        } elseif (in_array($statusCode, ['-1', '-2'], true) && $order->payment_status !== 'paid') {
            $order->update(['internal_note' => trim(($order->internal_note ? $order->internal_note . "\n" : '') . 'PayHere payment ' . ($statusCode === '-1' ? 'cancelled' : 'failed') . '.')]);
        }

        return true;
    }

    private function markPaid(Order $order, string $reference): void
    {
        $order->update([
            'payment_status'    => 'paid',
            'payment_reference' => $reference,
            'paid_at'           => now(),
            'status'            => $order->status === 'pending' ? 'confirmed' : $order->status,
        ]);
    }

    public function format(PaymentGateway $g): array
    {
        $meta = PaymentGateway::CATALOG[$g->gateway];
        $out  = [
            'key'         => $g->gateway,
            'name'        => $meta['name'],
            'description' => $meta['description'],
            'icon'        => $meta['icon'],
            'is_enabled'  => (bool) $g->is_enabled,
            'is_sandbox'  => (bool) $g->is_sandbox,
            'configured'  => $g->isConfigured(),
            'fields'      => [],
        ];
        foreach ($meta['public'] as $f) {
            $out['fields'][] = ['key' => $f, 'label' => $meta['labels'][$f], 'secret' => false, 'value' => $g->credential($f)];
        }
        foreach ($meta['secret'] as $f) {
            $v = $g->credential($f);
            $out['fields'][] = ['key' => $f, 'label' => $meta['labels'][$f], 'secret' => true, 'is_set' => $v !== null,
                'hint' => $v !== null ? '••••' . substr($v, -4) : null];
        }

        return $out;
    }
}
