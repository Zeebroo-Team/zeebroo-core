<?php

// Master catalog of feature keys used across the app (POS feature toggles, business
// onboarding, and package management). Keep this in sync with the feature key list in
// Modules\Pos\Http\Controllers\Api\PosSettingsApiController and
// Modules\Business\Http\Controllers\BusinessController.
return [
    'list' => [
        'account_management'   => 'Account Management',
        'automation_editor'    => 'Automation Editor',
        'bill_management'      => 'Bill Management',
        'crm'                  => 'CRM',
        'developers'           => 'Developer Tools',
        'event_management'     => 'Event Management',
        'human_resources'      => 'Human Resources',
        'mail'                 => 'Mail',
        'point_of_sale'        => 'Point of Sale',
        'product_management'   => 'Product Management',
        'project_management'   => 'Project Management',
        'restaurant'           => 'Restaurant',
        'service_management'   => 'Services',
        'social_media_campaign' => 'Social Media Campaign',
        'stock_management'     => 'Stock Management',
    ],
];
