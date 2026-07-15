<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Company branding used across payslips, management reports, and
    | payslip emails. Centralized here so it's set once instead of being
    | hardcoded as a string in every controller.
    |--------------------------------------------------------------------------
    */

    'name' => env('COMPANY_NAME', 'ZAMPAYROLL SYSTEM'),

    'logo_url' => env('COMPANY_LOGO_URL', 'http://misc.zampayroll.com/logo.png'),

];