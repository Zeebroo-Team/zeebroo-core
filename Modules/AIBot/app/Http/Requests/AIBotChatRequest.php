<?php

namespace Modules\AIBot\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AIBotChatRequest extends FormRequest
{
    /**
     * @return list<string>
     */
    private static function voiceMimeTypes(): array
    {
        return [
            'audio/webm',
            'audio/webm;codecs=opus',
            'audio/wav',
            'audio/x-wav',
            'audio/wave',
            'audio/mp4',
            'audio/mpeg',
            'audio/mp3',
            'audio/ogg',
            'audio/flac',
            'audio/aac',
        ];
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'speak_reply' => ['sometimes', 'boolean'],
            'messages' => ['required', 'array', 'min:1', 'max:50'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['nullable', 'string', 'max:24000'],
            'messages.*.audio' => ['sometimes', 'nullable', 'array'],
            'messages.*.audio.base64' => ['required_with:messages.*.audio', 'string', 'max:5610000'],
            'messages.*.audio.mime_type' => ['required_with:messages.*.audio', 'string', Rule::in(self::voiceMimeTypes())],
        ];
    }

    public function wantsSpokenReply(): bool
    {
        if ($this->has('speak_reply')) {
            return filter_var($this->input('speak_reply'), FILTER_VALIDATE_BOOLEAN);
        }

        return filter_var(config('aibot.gemini.reply_audio_enabled', false), FILTER_VALIDATE_BOOL);
    }

    public function withValidator(Validator $validator): void
    {
        $maxBytes = max(65536, (int) config('aibot.gemini.audio_input_max_bytes', 4_194_304));

        $validator->after(function (Validator $v) use ($maxBytes): void {
            $messages = $this->input('messages');
            if (! is_array($messages)) {
                return;
            }

            foreach ($messages as $i => $msg) {
                if (! is_array($msg)) {
                    continue;
                }

                $role = (string) ($msg['role'] ?? '');
                $content = isset($msg['content']) ? trim((string) $msg['content']) : '';
                $audio = isset($msg['audio']) && is_array($msg['audio']) ? $msg['audio'] : null;
                $audioB64 = isset($audio['base64']) && is_string($audio['base64']) ? trim($audio['base64']) : '';
                $hasAudio = $audioB64 !== '';

                if ($role === 'assistant') {
                    if ($content === '') {
                        $v->errors()->add(
                            'messages.'.$i.'.content',
                            'Assistant messages must include text.'
                        );
                    }
                    if ($hasAudio) {
                        $v->errors()->add('messages.'.$i.'.audio', 'Audio is only supported on user messages.');
                    }

                    continue;
                }

                if ($content === '' && ! $hasAudio) {
                    $v->errors()->add(
                        'messages.'.$i.'.content',
                        'Provide message text or a voice attachment.'
                    );
                }

                if ($hasAudio) {
                    $decoded = base64_decode($audioB64, true);
                    if ($decoded === false) {
                        $v->errors()->add('messages.'.$i.'.audio.base64', 'Invalid base64 encoding for audio.');
                    } elseif (strlen($decoded) > $maxBytes) {
                        $v->errors()->add(
                            'messages.'.$i.'.audio.base64',
                            'Voice attachment is too large.'
                        );
                    }
                }
            }

            foreach ($messages as $i => $msg) {
                if (! is_array($msg)) {
                    continue;
                }
                $audio = isset($msg['audio']) && is_array($msg['audio']) ? $msg['audio'] : null;
                $hasAudio = isset($audio['base64']) && trim((string) $audio['base64']) !== '';
                if ($hasAudio && (int) $i !== count($messages) - 1) {
                    $v->errors()->add(
                        'messages.'.$i.'.audio',
                        'Voice attachments are only accepted on the last message.'
                    );
                }
                if ($hasAudio && strtolower((string) ($msg['role'] ?? '')) !== 'user') {
                    $v->errors()->add(
                        'messages.'.$i.'.audio',
                        'Voice attachments are only allowed for user turns.'
                    );
                }
            }
        });
    }

    /**
     * @return list<array{role: string, content: string, audio?: array{base64: string, mime_type: string}}>
     */
    public function conversationMessages(): array
    {
        /** @var list<array<string, mixed>> $raw */
        $raw = $this->validated('messages');

        $out = [];
        foreach ($raw as $row) {
            $role = strtolower((string) ($row['role'] ?? '')) === 'assistant' ? 'assistant' : 'user';
            $payload = [
                'role' => $role,
                'content' => isset($row['content']) ? (string) $row['content'] : '',
            ];

            $audio = $row['audio'] ?? null;
            if (is_array($audio) && isset($audio['base64']) && is_string($audio['base64'])) {
                $mime = isset($audio['mime_type']) && is_string($audio['mime_type']) ? $audio['mime_type'] : 'audio/webm';
                $payload['audio'] = [
                    'base64' => preg_replace('/\s+/', '', $audio['base64']) ?? '',
                    'mime_type' => $mime,
                ];
            }

            $out[] = $payload;
        }

        return $out;
    }
}
