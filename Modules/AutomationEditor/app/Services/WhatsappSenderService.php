<?php

namespace Modules\AutomationEditor\Services;

class WhatsappSenderService
{
    private const API_VERSION = 'v19.0';

    /**
     * Send a plain-text WhatsApp message via Meta Cloud API.
     *
     * @param  string $to            Recipient phone in E.164 format (with or without +)
     * @param  string $body          Message text (max 4096 chars)
     * @param  string $phoneNumberId Meta phone-number-id from the App Dashboard
     * @param  string $accessToken   Permanent or temporary access token
     */
    public function send(string $to, string $body, string $phoneNumberId, string $accessToken): array
    {
        $phone = preg_replace('/\D/', '', $to);

        if (strlen($phone) < 7) {
            return ['success' => false, 'error' => "Invalid phone number: {$to}"];
        }

        $url     = sprintf('https://graph.facebook.com/%s/%s/messages', self::API_VERSION, $phoneNumberId);
        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to'                => $phone,
            'type'              => 'text',
            'text'              => [
                'preview_url' => false,
                'body'        => mb_substr($body, 0, 4096),
            ],
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$accessToken}",
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);

        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['success' => false, 'error' => $err];
        }

        $data = is_string($resp) ? (json_decode($resp, true) ?? []) : [];

        if ($code !== 200) {
            $apiError = $data['error']['message'] ?? "HTTP {$code}";
            return ['success' => false, 'error' => $apiError, 'http_code' => $code];
        }

        return [
            'success'    => true,
            'message_id' => $data['messages'][0]['id'] ?? null,
            'to'         => $phone,
        ];
    }
}
