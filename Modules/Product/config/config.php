<?php

return [
    'name' => 'Product',

    'gemini_image' => [
        'model' => env('GEMINI_PRODUCT_IMAGE_MODEL', env('GEMINI_LOGO_IMAGE_MODEL', 'gemini-2.5-flash-image')),
        'timeout' => max(30, (int) env('GEMINI_PRODUCT_IMAGE_TIMEOUT', 120)),
        'aspect_ratio' => env('GEMINI_PRODUCT_IMAGE_ASPECT_RATIO', '1:1'),
        'image_size' => env('GEMINI_PRODUCT_IMAGE_IMAGE_SIZE', ''),
    ],
];
