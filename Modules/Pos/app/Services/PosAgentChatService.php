<?php

namespace Modules\Pos\Services;

use Modules\Business\Models\Business;

class PosAgentChatService
{
    private const SYSTEM_INSTRUCTION = <<<'TXT'
You are the Zeebroo POS AI agent — a business assistant that can both answer questions about live business data AND perform actions (create discount sale campaigns, create purchase orders, generate campaign posters) using the provided tools.

Operational rules:
- Ground every factual answer (stock levels, sales, overdue payments, expiry dates) in a tool call — never invent numbers, product names, dates or ids.
- For "lowest stock" / "under N qty" style requests use pos_list_products with sort=stock_asc and/or max_stock_quantity; for "expiring in the next N months" use pos_list_expiring_products.
- When the user refers back to products with words like "these", "those", or "them", you MUST reuse the exact product ids returned by the most recent relevant list/search tool call in this conversation — never substitute, guess, or invent different products, even if other product names appear earlier in the conversation.
- Writes (sale campaigns, purchase orders) always go through prepare → confirm:
  1. Call the matching pos_prepare_*_draft tool.
  2. Show the user a clear, concrete summary (exact products, discount/quantities, dates, totals).
  3. Only after the user explicitly confirms in their next message, call the matching pos_confirm_*_insert tool — pass only confirm:true (and place_order if relevant); never pass a draft_id unless you were given that exact id in this same turn, since the server always resolves the most recently prepared draft automatically.
  Never call a pos_confirm_*_insert tool without an explicit prior user confirmation in this conversation.
- Creating a purchase order never changes stock immediately — stock only increases once goods are received and a GRN is approved. Say so when relevant.
- "Overdue payments" can mean bills the business owes OR customer invoices owed to the business — pos_overdue_payments returns both; mention whichever is relevant (or both if the user's question is general).
- Chain tool calls across a single turn when the request has multiple steps (e.g. find expiring products → prepare a campaign from them → generate a poster) — you may call several tools in sequence before giving your final answer.
- Format your final answer using only <b>, <br>, <ul>, <li>, <span> HTML tags when presenting data, lists, or a draft summary; use plain conversational sentences otherwise. Keep answers concise and friendly.
- Only call pos_show_walkthrough when the user clearly wants a live on-screen demo, never for data questions or write actions.
- Never ask for passwords, API keys, or card numbers.
TXT;

    public function __construct(
        private PosGeminiClient $client,
        private PosAgentToolExecutor $executor,
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{reply: string, isHtml?: bool, walkthroughId?: ?string, posterCommands?: ?array, error?: string}
     */
    public function reply(Business $business, string $actorKey, array $messages): array
    {
        $contents = $this->toGeminiContents($messages);
        if ($contents === []) {
            return ['reply' => '', 'error' => 'No usable messages sent.'];
        }

        $maxRounds = 10;
        $body = [
            'systemInstruction' => ['parts' => [['text' => self::SYSTEM_INSTRUCTION]]],
            'contents' => $contents,
            'tools' => [
                ['functionDeclarations' => PosAgentToolExecutor::functionDeclarations()],
            ],
            'generationConfig' => ['maxOutputTokens' => 1200, 'temperature' => 0.2],
        ];

        $posterCommands = null;

        for ($round = 0; $round < $maxRounds; $round++) {
            $body['contents'] = $contents;

            $result = $this->client->generate($body);
            if (! $result['successful'] || ! is_array($result['json'])) {
                return ['reply' => '', 'error' => 'Assistant is unavailable right now.'];
            }
            $json = $result['json'];

            $candidate = (($json['candidates'] ?? [])[0]) ?? null;
            if (! is_array($candidate)) {
                return ['reply' => '', 'error' => 'No response from the assistant.'];
            }

            $finish = strtoupper((string) ($candidate['finishReason'] ?? ''));
            if (in_array($finish, ['SAFETY', 'BLOCKLIST', 'PROHIBITED_CONTENT'], true)) {
                return ['reply' => '', 'error' => 'This request was blocked. Please rephrase.'];
            }

            $modelContent = $candidate['content'] ?? null;
            if (! is_array($modelContent) || ! isset($modelContent['parts'])) {
                return ['reply' => '', 'error' => 'Incomplete assistant reply.'];
            }

            /** @var list<array<string, mixed>> $parts */
            $parts = is_array($modelContent['parts']) ? $modelContent['parts'] : [];
            ['text' => $text, 'function_calls' => $functionCalls] = $this->splitParts($parts);

            // Walkthrough calls are terminal — hand off to the client immediately
            // instead of feeding a function response back for more rounds.
            foreach ($functionCalls as $fc) {
                if ($fc['name'] === 'pos_show_walkthrough') {
                    $walkthroughId = (string) ($fc['args']['walkthrough_id'] ?? '');

                    return [
                        'reply' => $text !== null && $text !== '' ? $text : 'Sure, let me show you!',
                        'isHtml' => false,
                        'walkthroughId' => $walkthroughId !== '' ? $walkthroughId : null,
                        'productName' => $fc['args']['product_name'] ?? null,
                        'fieldName' => $fc['args']['field_name'] ?? null,
                        'posterCommands' => $posterCommands,
                    ];
                }
            }

            if ($functionCalls === []) {
                $reply = trim((string) $text);
                if ($reply === '') {
                    return ['reply' => '', 'error' => 'The assistant returned an empty reply.'];
                }

                return [
                    'reply' => $reply,
                    'isHtml' => true,
                    'walkthroughId' => null,
                    'posterCommands' => $posterCommands,
                ];
            }

            $contents[] = $this->normalizeModelTurnForGeminiApi($modelContent);

            $responseParts = [];
            foreach ($functionCalls as $fc) {
                $name = $fc['name'];
                /** @var array<string, mixed> $args */
                $args = is_array($fc['args']) ? $fc['args'] : [];

                $toolResult = $this->executor->execute($business, $actorKey, $name, $args);

                if ($name === 'pos_generate_campaign_poster' && isset($toolResult['commands']) && is_array($toolResult['commands'])) {
                    $posterCommands = $toolResult['commands'];
                }

                $functionResponse = [
                    'name' => $name,
                    'response' => $this->normalizeFunctionResponsePayload($toolResult),
                ];

                $id = $fc['id'] ?? null;
                if (is_string($id) && $id !== '') {
                    $functionResponse['id'] = $id;
                }

                $responseParts[] = ['functionResponse' => $functionResponse];
            }

            $contents[] = ['role' => 'user', 'parts' => $responseParts];
        }

        return ['reply' => '', 'error' => 'The assistant hit the tool-use limit before finishing. Try again or simplify the request.'];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return list<array{role: string, parts: list<array<string, mixed>>}>
     */
    private function toGeminiContents(array $messages): array
    {
        $contents = [];
        foreach ($messages as $m) {
            $role = strtolower((string) ($m['role'] ?? '')) === 'assistant' ? 'model' : 'user';
            $text = trim((string) ($m['content'] ?? ''));
            if ($text === '') {
                continue;
            }

            $contents[] = ['role' => $role, 'parts' => [['text' => $text]]];
        }

        return $contents;
    }

    /**
     * Rebuild the model turn for generateContent: Gemini rejects functionCall.args encoded as a JSON array ([]).
     * Protobuf Struct must be a JSON object ({}).
     *
     * @param  array<string, mixed>  $modelContent
     * @return array{role: string, parts: list<array<string, mixed>>}
     */
    private function normalizeModelTurnForGeminiApi(array $modelContent): array
    {
        $role = (string) ($modelContent['role'] ?? '');
        if ($role === '') {
            $role = 'model';
        }

        $partsOut = [];
        $partsIn = $modelContent['parts'] ?? [];
        if (! is_array($partsIn)) {
            return ['role' => $role, 'parts' => []];
        }

        foreach ($partsIn as $part) {
            if (! is_array($part)) {
                continue;
            }
            if (! empty($part['text'])) {
                $partsOut[] = ['text' => (string) $part['text']];

                continue;
            }

            $fc = null;
            if (! empty($part['functionCall']) && is_array($part['functionCall'])) {
                $fc = $part['functionCall'];
            } elseif (! empty($part['function_call']) && is_array($part['function_call'])) {
                $fc = $part['function_call'];
            }

            if ($fc === null) {
                continue;
            }

            $name = (string) ($fc['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $partsOut[] = [
                'functionCall' => [
                    'name' => $name,
                    'args' => $this->normalizeGeminiStructArgs($fc['args'] ?? null),
                ],
            ];
        }

        return ['role' => $role, 'parts' => $partsOut];
    }

    /** @return array<string, mixed>|\stdClass */
    private function normalizeGeminiStructArgs(mixed $args): array|\stdClass
    {
        if ($args === null) {
            return new \stdClass;
        }
        if ($args instanceof \stdClass) {
            $decoded = json_decode(json_encode($args) ?: '{}', true);
            if (! is_array($decoded) || $decoded === [] || array_is_list($decoded)) {
                return new \stdClass;
            }

            return $decoded;
        }
        if (is_array($args)) {
            if ($args === [] || array_is_list($args)) {
                return new \stdClass;
            }

            return $args;
        }

        return new \stdClass;
    }

    /** @param  array<string, mixed>  $payload */
    private function normalizeFunctionResponsePayload(array $payload): array|\stdClass
    {
        if ($payload === []) {
            return new \stdClass;
        }
        if (array_is_list($payload)) {
            return ['items' => array_values($payload)];
        }

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>  $parts
     * @return array{text: ?string, function_calls: list<array{name: string, args: array<string, mixed>, id: ?string}>}
     */
    private function splitParts(array $parts): array
    {
        $textChunks = [];
        $calls = [];

        foreach ($parts as $part) {
            if (! is_array($part)) {
                continue;
            }
            if (! empty($part['text'])) {
                $textChunks[] = (string) $part['text'];
            }

            $fcRaw = null;
            if (! empty($part['functionCall']) && is_array($part['functionCall'])) {
                $fcRaw = $part['functionCall'];
            } elseif (! empty($part['function_call']) && is_array($part['function_call'])) {
                $fcRaw = $part['function_call'];
            }

            if ($fcRaw === null) {
                continue;
            }

            /** @var array<string, mixed> $argsFlat */
            $argsFlat = [];
            foreach ((array) ($fcRaw['args'] ?? []) as $k => $v) {
                if (is_string($k)) {
                    $argsFlat[$k] = $v;
                }
            }

            $calls[] = [
                'name' => (string) ($fcRaw['name'] ?? ''),
                'args' => $argsFlat,
                'id' => isset($fcRaw['id']) ? (string) $fcRaw['id'] : null,
            ];
        }

        return [
            'text' => $textChunks === [] ? null : implode('', $textChunks),
            'function_calls' => array_values(array_filter($calls, fn ($c) => $c['name'] !== '')),
        ];
    }
}
