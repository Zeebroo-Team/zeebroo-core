<?php

namespace Modules\AIBot\Services;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Modules\Business\Models\Business;

class GeminiAgentChatService
{
    private const SYSTEM_INSTRUCTION = <<<'TXT'
You are SociBiz AI Agent, an assistant inside SociBiz Panel (finance, lending, rentals, bills, ledger, HR payroll).

Operational rules:
- Ground answers in workspace tools whenever the question needs factual data (balances, loans, rentals, bills, employees, transactions, overdue flags). Call soci_biz_workspace_overview before other tools when the user asks generally about “my business”.
- For simple quantity questions (“how many departments”, “how many employees”, etc.), call soci_biz_workspace_overview once and read `counts` — then answer in plain text. Do not chain extra tools unless the user asks for names, lists, or details. After tool results are enough to answer, reply without calling more functions.
- Never invent overdue status, ledger rows, salaries, IDs, balances, dates, names, or payment history. If tools are empty, say clearly that no records exist.
- Bill insertion is allowed only via explicit two-step flow:
  1) call soci_biz_prepare_bill_draft after collecting required bill fields from user (reuse returned draft_id for follow-up updates),
  2) show draft summary and ask confirmation,
  3) only after clear user confirmation, call soci_biz_confirm_bill_insert.
- Do not call soci_biz_confirm_bill_insert unless the user explicitly confirms insertion.
- If confirm returns "draft expired/not found", do not ask the user to restart from zero immediately. Retry by calling soci_biz_prepare_bill_draft with known fields from conversation, then ask confirmation again.
- If no business is selected, tell them to choose one from the SociBiz header dropdown before expecting company-specific insights.
- When HR tools note “HR payroll not opted in”, explain that onboarding is required in HR settings.
- Do not ask for passwords, API keys, or card numbers.

Tone: concise, professional, actionable. Mention currency from tool results when quoting money.
TXT;

    public function __construct(
        private GeminiGenerateContentClient $client,
        private SociBizAgentToolExecutor $executor,
        private GeminiTextToSpeechService $textToSpeech,
    ) {}

    /**
     * @param  array<int, array{role: string, content: string, audio?: array{base64: string, mime_type: string}}>  $messages
     * @return array{reply: string, error?: string, reply_audio?: array{mime: string, data: string}}
     */
    public function reply(User $user, ?Business $business, array $messages, bool $speakReply = false): array
    {
        $contents = $this->toGeminiContents($messages);
        if ($contents === []) {
            return ['reply' => '', 'error' => 'No usable messages sent.'];
        }

        $maxRounds = max(1, (int) config('aibot.gemini.max_tool_rounds', 16));

        $body = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => self::SYSTEM_INSTRUCTION],
                ],
            ],
            'contents' => $contents,
            'tools' => [
                [
                    'functionDeclarations' => SociBizAgentToolExecutor::functionDeclarations(),
                ],
            ],
        ];

        for ($round = 0; $round < $maxRounds; $round++) {
            $body['contents'] = $contents;

            $response = $this->client->generate($body);
            $json = $response->json();

            if (! $response->successful()) {
                $msg = is_array($json) && isset($json['error']['message'])
                    ? (string) $json['error']['message']
                    : ('Gemini HTTP '.$response->status());

                return ['reply' => '', 'error' => $msg];
            }

            if (! is_array($json)) {
                return ['reply' => '', 'error' => 'Invalid Gemini response.'];
            }

            $candidate = (($json['candidates'] ?? [])[0]) ?? null;
            if (! is_array($candidate)) {
                return ['reply' => '', 'error' => 'No response candidates from Gemini.'];
            }

            $finish = strtoupper((string) ($candidate['finishReason'] ?? ''));
            if (in_array($finish, ['SAFETY', 'BLOCKLIST', 'PROHIBITED_CONTENT'], true)) {
                return ['reply' => '', 'error' => 'This request was rejected by Gemini safety filters. Rephrase and try again.'];
            }

            $modelContent = $candidate['content'] ?? null;
            if (! is_array($modelContent) || ! isset($modelContent['parts'])) {
                return ['reply' => '', 'error' => 'Incomplete model reply.'];
            }

            /** @var list<array<string, mixed>> $parts */
            $parts = is_array($modelContent['parts']) ? $modelContent['parts'] : [];
            ['text' => $text, 'function_calls' => $functionCalls] = $this->splitParts($parts);

            if ($functionCalls === []) {
                $reply = $text ?? '';
                if ($reply === '') {
                    return ['reply' => '', 'error' => 'The model returned an empty reply.'];
                }

                return $this->withOptionalSpeech($reply, $speakReply);
            }

            $contents[] = $this->normalizeModelTurnForGeminiApi($modelContent);

            $responseParts = [];
            foreach ($functionCalls as $fc) {
                $name = $fc['name'];
                /** @var array<string, mixed> $args */
                $args = is_array($fc['args']) ? $fc['args'] : [];

                $result = $this->executor->execute($user, $business, $name, $args);
                $functionResponse = [
                    'name' => $name,
                    'response' => $this->normalizeFunctionResponsePayload($result),
                ];

                $id = $fc['id'] ?? null;
                if (is_string($id) && $id !== '') {
                    $functionResponse['id'] = $id;
                }

                $responseParts[] = [
                    'functionResponse' => $functionResponse,
                ];
            }

            $contents[] = [
                'role' => 'user',
                'parts' => $responseParts,
            ];

            continue;
        }

        $recovered = $this->recoverTextOnlyAnswer($contents);
        if ($recovered !== null && $recovered !== '') {
            return $this->withOptionalSpeech($recovered, $speakReply);
        }

        $direct = $this->tryDirectDepartmentCountAnswer($user, $business, $messages);
        if ($direct !== null && $direct !== '') {
            return $this->withOptionalSpeech($direct, $speakReply);
        }

        return ['reply' => '', 'error' => 'The assistant hit the tool-use limit before producing a final answer. Try again, or pick your business in the header first.'];
    }

    /**
     * @return array{reply: string, reply_audio?: array{mime: string, data: string}}
     */
    private function withOptionalSpeech(string $reply, bool $speakReply): array
    {
        $reply = trim($reply);
        $out = ['reply' => $reply];
        if (! $speakReply || $reply === '') {
            return $out;
        }

        $audio = $this->textToSpeech->synthesizeReply($reply);
        if ($audio !== null) {
            $out['reply_audio'] = $audio;
        }

        return $out;
    }

    /**
     * One last Gemini call without tools so the model must answer in plain text from prior tool outputs.
     *
     * @param  list<array{role: string, parts: list<array<string, mixed>>}>  $contents
     */
    private function recoverTextOnlyAnswer(array $contents): ?string
    {
        $nudge = <<<'TXT'
You have used the maximum number of tool rounds. Do not request tools. Read the function responses already in this conversation and answer the user's latest question in clear prose (under 6 sentences). If counts.departments appears in soci_biz_workspace_overview, give that number. If no business was selected, say so.
TXT;

        $recoveryContents = $contents;
        $recoveryContents[] = [
            'role' => 'user',
            'parts' => [
                ['text' => $nudge],
            ],
        ];

        $body = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => self::SYSTEM_INSTRUCTION],
                ],
            ],
            'contents' => $recoveryContents,
        ];

        $response = $this->client->generate($body);
        $json = $response->json();

        if (! $response->successful() || ! is_array($json)) {
            return null;
        }

        $text = $this->extractTextFromCandidateJson($json);
        $text = trim((string) $text);

        return $text !== '' ? $text : null;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    private function tryDirectDepartmentCountAnswer(User $user, ?Business $business, array $messages): ?string
    {
        if ($business === null || ! $user->businesses()->whereKey($business->id)->exists()) {
            return null;
        }

        if (! Schema::hasTable('hr_departments')) {
            return null;
        }

        $last = end($messages);
        if (! is_array($last)) {
            return null;
        }

        $q = strtolower((string) ($last['content'] ?? ''));

        $looksLikeDeptCount = (bool) preg_match(
            '/\b(how many|number of|count of|total)\b[\s\S]{0,48}\bdepartments?\b/',
            $q
        ) || (bool) preg_match(
            '/\bdepartments?\b[\s\S]{0,28}\b(how many|count|number)\b/',
            $q
        );

        if (! $looksLikeDeptCount) {
            return null;
        }

        $n = (int) $business->departments()->count();

        return __('This business has :count department(s). Open HR → Departments to manage or rename them.', ['count' => $n]);
    }

    /** @param  array<string, mixed>  $json */
    private function extractTextFromCandidateJson(array $json): ?string
    {
        $candidate = (($json['candidates'] ?? [])[0]) ?? null;
        if (! is_array($candidate)) {
            return null;
        }

        $finish = strtoupper((string) ($candidate['finishReason'] ?? ''));
        if (in_array($finish, ['SAFETY', 'BLOCKLIST', 'PROHIBITED_CONTENT'], true)) {
            return null;
        }

        $modelContent = $candidate['content'] ?? null;
        if (! is_array($modelContent) || ! isset($modelContent['parts'])) {
            return null;
        }

        $parts = is_array($modelContent['parts']) ? $modelContent['parts'] : [];
        $chunks = [];

        foreach ($parts as $part) {
            if (! is_array($part) || empty($part['text'])) {
                continue;
            }
            $chunks[] = (string) $part['text'];
        }

        if ($chunks === []) {
            return null;
        }

        return implode('', $chunks);
    }

    /**
     * @param  array<int, array{role: string, content: string, audio?: array{base64: string, mime_type: string}}>  $messages
     * @return list<array{role: string, parts: list<array<string, mixed>>}>
     */
    private function toGeminiContents(array $messages): array
    {
        $contents = [];

        foreach ($messages as $m) {
            $role = strtolower((string) ($m['role'] ?? '')) === 'assistant' ? 'model' : 'user';
            $text = trim((string) ($m['content'] ?? ''));
            $audio = isset($m['audio']) && is_array($m['audio']) ? $m['audio'] : null;
            $audioB64Raw = isset($audio['base64']) && is_string($audio['base64']) ? $audio['base64'] : '';
            $audioB64 = $audioB64Raw !== '' ? preg_replace('/\s+/', '', $audioB64Raw) : '';
            $mime = isset($audio['mime_type']) && is_string($audio['mime_type']) ? trim($audio['mime_type']) : 'audio/webm';

            $parts = [];
            if ($text !== '') {
                $parts[] = ['text' => $text];
            }

            if ($audioB64 !== '') {
                if ($parts === []) {
                    $parts[] = ['text' => 'Voice input about the SociBiz workspace — use tools when you need factual data, then reply in plain text.'];
                }
                $parts[] = [
                    'inlineData' => [
                        'mimeType' => $mime !== '' ? $mime : 'audio/webm',
                        'data' => $audioB64,
                    ],
                ];
            }

            if ($parts === []) {
                continue;
            }

            $contents[] = [
                'role' => $role,
                'parts' => $parts,
            ];
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

    /**
     * @return array<string, mixed>|\stdClass
     */
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

    /**
     * @param  array<string, mixed>  $payload
     */
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

            $fc = $fcRaw;

            /** @var array<string, mixed> $argsFlat */
            $argsFlat = [];
            foreach ((array) ($fc['args'] ?? []) as $k => $v) {
                if (is_string($k)) {
                    $argsFlat[$k] = $v;
                }
            }

            $calls[] = [
                'name' => (string) ($fc['name'] ?? ''),
                'args' => $argsFlat,
                'id' => isset($fc['id']) ? (string) $fc['id'] : null,
            ];
        }

        return [
            'text' => $textChunks === [] ? null : implode('', $textChunks),
            'function_calls' => array_values(array_filter($calls, fn ($c) => $c['name'] !== '')),
        ];
    }
}
