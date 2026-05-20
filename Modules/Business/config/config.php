<?php

return [
    'name' => 'Business',
    /*
     * Gemini native image generation (Google AI Studio key). Runs in a queued job.
     */
    'logo_ai' => [
        'model' => env('GEMINI_LOGO_IMAGE_MODEL', 'gemini-2.5-flash-image'),
        'timeout' => (int) env('GEMINI_LOGO_GENERATION_TIMEOUT', 120),
        /** Optional: Gemini imageConfig (supported models only). */
        'aspect_ratio' => env('GEMINI_LOGO_IMAGE_ASPECT_RATIO'),
        'image_size' => env('GEMINI_LOGO_IMAGE_SIZE'),
    ],
];
