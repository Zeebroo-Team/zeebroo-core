<?php

return [
    'name' => 'CRM',

    // Country calling code used to normalise local phone numbers ("077…" → "9477…") for the
    // duplicate check, until a business sets its own in the CRM settings.
    'default_phone_country_code' => env('CRM_PHONE_COUNTRY_CODE', '94'),

    // Files on a lead: the disk they are kept on (never a public one — they are only served
    // through the CRM's authorised download route) and the largest file accepted, in KB.
    'attachments' => [
        'disk'   => env('CRM_ATTACHMENTS_DISK', env('FILESYSTEM_DISK', 'local')),
        'max_kb' => (int) env('CRM_ATTACHMENTS_MAX_KB', 10240),
    ],
];
