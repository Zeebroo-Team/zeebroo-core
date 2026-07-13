<?php

namespace Modules\Mail\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Modules\Business\Models\Business;
use Modules\Mail\Models\MailMessage;
use Modules\Pos\Models\Customer;
use RuntimeException;

class MailAssistantService
{
    private const MODELS = [
        'gemini-2.5-flash',
        'gemini-2.0-flash',
        'gemini-1.5-flash',
    ];

    private const SUMMARY_PROMPT = <<<'PROMPT'
You are an assistant summarizing a business's email conversation with a customer.
Read the transcript and write a concise summary (3-5 sentences) covering: what the
customer wants, what has been offered/discussed so far, and the current status.
Reply with plain text only — no markdown, no headings, no code fences.
PROMPT;

    private const REPLY_PROMPT = <<<'PROMPT'
You are a sales-savvy assistant drafting an email reply on behalf of a business.
Read the conversation transcript and write ONE suggested reply to send next that
moves the customer toward a purchase/booking/conversion — friendly, concise,
confident, with a clear next step or call to action.

CRITICAL — do not fabricate: this business's specific prices, package names,
features, discounts, availability, and policies are NOT known to you unless they
literally appear in the transcript below. Never state a number, price, feature
name, or promise that is not already written in the transcript. If the customer
is asking for details (pricing, specs, availability, etc.) that are not present
in the transcript, do NOT invent an answer — instead write a reply that
acknowledges the question and moves things forward without stating the missing
specifics (e.g. "let me confirm the exact details and get right back to you" or
asking a clarifying question), while still being warm and proactive.

Reply with the plain-text email body only — no subject line, no markdown, no
greeting placeholders like "[Name]", no code fences.
PROMPT;

    /**
     * @param Collection<int, MailMessage> $timeline
     */
    public function summarize(Business $business, Collection $timeline): string
    {
        return $this->callGemini(self::SUMMARY_PROMPT, $this->transcript($business, $timeline));
    }

    /**
     * @param Collection<int, MailMessage> $timeline
     */
    public function suggestReply(Business $business, Collection $timeline, ?Customer $customer): string
    {
        $context = $this->transcript($business, $timeline);
        if ($customer) {
            $context = "Customer name: {$customer->name}\n\n" . $context;
        }

        return $this->callGemini(self::REPLY_PROMPT, $context);
    }

    /**
     * @param Collection<int, MailMessage> $timeline
     */
    private function transcript(Business $business, Collection $timeline): string
    {
        return $timeline->map(function (MailMessage $m) use ($business) {
            $speaker = $m->direction === MailMessage::DIRECTION_OUTBOUND
                ? ($business->name ?: 'Business')
                : ($m->from_name ?: $m->from_address);

            return "{$speaker} ({$m->occurred_at?->format('d M Y H:i')}): " . $m->snippet(600);
        })->implode("\n\n");
    }

    private function callGemini(string $systemPrompt, string $userContent): string
    {
        $apiKey = trim((string) config('services.gemini.key'));
        if ($apiKey === '') {
            throw new RuntimeException('Gemini is not configured for this app (missing GEMINI_API_KEY).');
        }

        $envModel = config('services.gemini.model');
        $models   = $envModel
            ? array_unique(array_merge([$envModel], self::MODELS))
            : self::MODELS;

        $payload = [
            'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents'          => [['role' => 'user', 'parts' => [['text' => $userContent]]]],
            'generationConfig'  => [
                'maxOutputTokens' => 1024,
                'temperature'     => 0.4,
                // These are short, direct drafting tasks — disable extended
                // "thinking" so its token budget doesn't crowd out the answer.
                'thinkingConfig'  => ['thinkingBudget' => 0],
            ],
        ];

        foreach ($models as $model) {
            $response = Http::timeout(25)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                $payload
            );

            if ($response->status() === 429) {
                continue;
            }

            if (!$response->successful()) {
                continue;
            }

            $text = $response->json('candidates.0.content.parts.0.text');
            if (filled($text)) {
                return trim($text);
            }
        }

        throw new RuntimeException('The AI assistant is temporarily unavailable (quota exhausted or no response). Please try again shortly.');
    }
}
