<?php

namespace Modules\AIBot\Services;

use Illuminate\Support\Facades\Log;

class GeminiTextToSpeechService
{
    public function __construct(
        private GeminiGenerateContentClient $client,
    ) {}

    /**
     * Gemini TTS returns raw PCM (s16le); wrap as WAV for easy browser playback.
     *
     * @return array{mime: string, data: string}|null  MIME + base64 payload
     */
    public function synthesizeReply(string $spokenText): ?array
    {
        if (! filter_var(config('aibot.gemini.reply_audio_enabled', false), FILTER_VALIDATE_BOOL)) {
            return null;
        }

        $model = trim((string) config('aibot.gemini.tts_model', ''));
        if ($model === '') {
            return null;
        }

        $text = trim($spokenText);
        if ($text === '') {
            return null;
        }

        /** @see https://ai.google.dev/gemini-api/docs/speech-generation REST example */
        $voice = trim((string) config('aibot.gemini.tts_voice', 'Kore'));
        if ($voice === '') {
            $voice = 'Kore';
        }

        $body = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => 'Say clearly and concisely, as a neutral assistant voice: '.$text],
                    ],
                ],
            ],
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => [
                    'voiceConfig' => [
                        'prebuiltVoiceConfig' => [
                            'voiceName' => $voice,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = $this->client->generateForModel($model, $body);
            $json = $response->successful() ? $response->json() : null;
            if (! is_array($json)) {
                Log::notice('Gemini TTS HTTP error', [
                    'status' => method_exists($response, 'status') ? $response->status() : null,
                ]);

                return null;
            }

            $pcm = $this->extractInlineAudioBase64($json);
            if ($pcm === null || $pcm === '') {
                return null;
            }

            $binary = base64_decode($pcm, true);
            if ($binary === false || $binary === '') {
                return null;
            }

            $sampleRate = max(8000, (int) config('aibot.gemini.tts_sample_rate', 24000));
            $wav = $this->pcmS16LeMonoToWav($binary, $sampleRate);

            return [
                'mime' => 'audio/wav',
                'data' => base64_encode($wav),
            ];
        } catch (\Throwable $e) {
            Log::notice('Gemini TTS failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /** @param  array<string, mixed>|null  $json */
    private function extractInlineAudioBase64(?array $json): ?string
    {
        if (! is_array($json)) {
            return null;
        }

        $candidate = (($json['candidates'] ?? [])[0]) ?? null;
        if (! is_array($candidate)) {
            return null;
        }

        $content = $candidate['content'] ?? null;
        if (! is_array($content)) {
            return null;
        }

        $parts = $content['parts'] ?? [];
        if (! is_array($parts)) {
            return null;
        }

        foreach ($parts as $part) {
            if (! is_array($part)) {
                continue;
            }

            $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if (! is_array($inline)) {
                continue;
            }

            $data = $inline['data'] ?? null;
            if (is_string($data) && $data !== '') {
                return $data;
            }
        }

        return null;
    }

    private function pcmS16LeMonoToWav(string $pcmBinary, int $sampleRate): string
    {
        $channels = 1;
        $bitsPerSample = 16;
        $byteRate = (int) ($sampleRate * $channels * ($bitsPerSample / 8));
        $blockAlign = (int) ($channels * ($bitsPerSample / 8));
        $subChunkSize = 16;
        $dataChunkSize = strlen($pcmBinary);
        $chunkSize = 36 + $dataChunkSize;

        return 'RIFF'
            . pack('V', $chunkSize)
            . 'WAVE'
            . 'fmt '
            . pack('V', $subChunkSize)
            . pack('v', 1)
            . pack('v', $channels)
            . pack('V', $sampleRate)
            . pack('V', $byteRate)
            . pack('v', $blockAlign)
            . pack('v', $bitsPerSample)
            . 'data'
            . pack('V', $dataChunkSize)
            .$pcmBinary;
    }
}
