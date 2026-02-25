<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Company Details
    |--------------------------------------------------------------------------
    |
    | Core company information used in Tally XML export headers and
    | GST reconciliation logic (intra-state vs inter-state).
    |
    */

    'name' => env('COMPANY_NAME', 'Zysk Technologies Private Limited - 2025 - 2026'),

    'gstin' => env('COMPANY_GSTIN', '29AABCZ5012F1ZG'),

    'state' => env('COMPANY_STATE', 'Karnataka'),

    'gst_registration_type' => env('COMPANY_GST_REGISTRATION_TYPE', 'Regular'),

    'financial_year' => env('COMPANY_FINANCIAL_YEAR', '2025-2026'),

    'currency' => env('COMPANY_CURRENCY', 'INR'),

];
