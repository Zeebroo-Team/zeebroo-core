<?php

return [
    'name' => 'AIBot',

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY', ''),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 60),
        'max_tool_rounds' => (int) env('GEMINI_MAX_TOOL_ROUNDS', 16),
        /** Second generateContent call using a TTS-capable model (not combinable with tools on the same request). */
        'reply_audio_enabled' => filter_var(env('GEMINI_REPLY_AUDIO', false), FILTER_VALIDATE_BOOL),
        'tts_model' => env('GEMINI_TTS_MODEL', 'gemini-2.5-flash-preview-tts'),
        'tts_voice' => env('GEMINI_TTS_VOICE', 'Kore'),
        'tts_sample_rate' => (int) env('GEMINI_TTS_SAMPLE_RATE', 24000),
        /** Max decoded audio bytes accepted on a user message (inlineData). */
        'audio_input_max_bytes' => (int) env('GEMINI_AUDIO_INPUT_MAX_BYTES', 4_194_304),
    ],
];
