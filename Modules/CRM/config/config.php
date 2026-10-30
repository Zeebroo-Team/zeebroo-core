<?php

return [
    'name' => 'CRM',

    // Country calling code used to normalise local phone numbers ("077…" → "9477…") for the
    // duplicate check, until a business sets its own in the CRM settings.
    'default_phone_country_code' => env('CRM_PHONE_COUNTRY_CODE', '94'),
];
