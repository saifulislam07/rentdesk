<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Business defaults
    |--------------------------------------------------------------------------
    |
    | Used on new rentals, invoices and PDF documents until the settings screen
    | lets administrators manage them.
    |
    */

    'company_name' => env('RENTDESK_COMPANY_NAME', env('APP_NAME', 'RentDesk')),

    'currency' => env('RENTDESK_CURRENCY', 'USD'),

    // Percentage applied to the discounted subtotal, e.g. 10 for 10%.
    'tax_rate' => (float) env('RENTDESK_TAX_RATE', 0),

    'invoice_due_days' => (int) env('RENTDESK_INVOICE_DUE_DAYS', 7),

    // PHP date() formats for dates and times shown in the app; administrators can change them under Settings.
    'date_format' => 'Y-m-d',

    'time_format' => 'H:i',

    // Switches the public booking-request page at /book on or off.
    'public_booking' => (bool) env('RENTDESK_PUBLIC_BOOKING', false),

    /*
    |--------------------------------------------------------------------------
    | Installation
    |--------------------------------------------------------------------------
    |
    | The web installer runs until the lock file exists. RENTDESK_INSTALLED can
    | force the state (the test suite does), otherwise the lock file decides.
    |
    */

    'installed' => env('RENTDESK_INSTALLED'),

    'install_lock' => storage_path('app/installed.lock'),

    'env_path' => base_path('.env'),

    // Run storage:link when installing, so uploaded logos are served.
    'link_storage' => true,

    /*
    |--------------------------------------------------------------------------
    | Languages
    |--------------------------------------------------------------------------
    |
    | Languages users can switch between. "dir" is the text direction, which
    | the layouts put on the <html> element; add "rtl" languages here.
    |
    */

    'locales' => [
        'en' => ['name' => 'English', 'dir' => 'ltr'],
        'bn' => ['name' => 'বাংলা', 'dir' => 'ltr'],
    ],

    // PDFs are always rendered in this language: dompdf cannot shape Bengali or right-to-left scripts.
    'pdf_locale' => 'en',

    // Late fee per started day late, as a percentage of the rental's average daily charge.
    'late_fee_percent' => (float) env('RENTDESK_LATE_FEE_PERCENT', 100),

    // Minutes after the end time before a rental counts as late.
    'late_fee_grace_minutes' => (int) env('RENTDESK_LATE_FEE_GRACE_MINUTES', 60),

];
