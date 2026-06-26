<?php

return [
    'name' => 'DataRouter',

    'vault' => [
        'timeout'     => (int) env('DATA_VAULT_TIMEOUT', 15),
        'retries'     => (int) env('DATA_VAULT_RETRIES', 0),
        'hmac_window' => (int) env('DATA_VAULT_HMAC_WINDOW', 300),
    ],
];
