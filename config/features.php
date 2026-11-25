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
        'ecommerce'            => 'E-Commerce',
        'event_management'     => 'Event Management',
        'human_resources'      => 'Human Resources',
        'mail'                 => 'Mail',
        'point_of_sale'        => 'Point of Sale',
        'product_management'   => 'Product Management',
        'project_management'   => 'Project Management',
        'restaurant'           => 'Restaurant',
        'sales_management'     => 'Sales Management',
        'service_management'   => 'Services',
        'social_media_campaign' => 'Social Media Campaign',
        'stock_management'     => 'Stock Management',
    ],

    // Records that count as "this business really used the feature" — used by
    // the plan-change warning ("Silver doesn't include CRM: 24 leads will be
    // hidden"). Each entry is table => label; every table must have a
    // business_id column (tables that are missing are skipped).
    'usage' => [
        'automation_editor'     => ['automation_flows' => 'automations'],
        'bill_management'       => ['bills' => 'bills', 'loans' => 'loans', 'rentals' => 'rentals'],
        'crm'                   => ['crm_leads' => 'leads', 'crm_projects' => 'CRM projects', 'crm_tasks' => 'CRM tasks'],
        'developers'            => ['developer_api_keys' => 'API keys', 'developer_webhooks' => 'webhooks'],
        'ecommerce'             => ['ecom_stores' => 'stores', 'ecom_listings' => 'listings', 'ecom_orders' => 'orders'],
        'event_management'      => ['em_events' => 'events'],
        'human_resources'       => ['hr_employees' => 'employees', 'hr_attendance_records' => 'attendance records'],
        'mail'                  => ['mail_mailboxes' => 'mailboxes', 'mail_messages' => 'messages'],
        'point_of_sale'         => ['pos_sales' => 'sales', 'pos_customers' => 'customers'],
        'product_management'    => ['products' => 'products'],
        'project_management'    => ['pm_projects' => 'projects'],
        'restaurant'            => ['restaurant_orders' => 'orders', 'restaurant_tables' => 'tables'],
        'sales_management'      => ['quotations' => 'quotations', 'invoices' => 'invoices', 'sales_orders' => 'sales orders'],
        'service_management'    => ['service_items' => 'services', 'service_requests' => 'service requests'],
        'social_media_campaign' => ['design_studio_designs' => 'designs', 'social_media_connections' => 'social accounts'],
        'stock_management'      => ['suppliers' => 'suppliers', 'purchases' => 'purchases', 'goods_receive_notes' => 'GRNs'],
    ],
];
