<?php

return [
    'name' => 'Pos',
    'bill_scan' => [
        'model' => env('GEMINI_BILL_SCAN_MODEL', env('GEMINI_MODEL', 'gemini-2.5-flash')),
        'timeout' => 45,
    ],
];
