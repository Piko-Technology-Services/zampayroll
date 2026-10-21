<?php

/*
| ZamPayroll's own pricing. These are DEFAULTS only: the ZamPayroll admin
| console (Platform → Settings) overrides every value without a code change.
| Amounts are in ZMW.  ⚠ Placeholder prices — set your real prices before launch.
*/
return [
    'currency'            => 'ZMW',
    'trial_days'          => 7,
    'prepaid_limit'       => 10,     // up to this many active employees → Starter (pay up front)

    'starter_monthly'     => 350,    // Starter: flat price per month
    'starter_annual'      => 3500,   // Starter: flat price per year

    'business_per_employee' => 35,   // Business (post-paid): per active employee per month
    'business_minimum'    => 500,    // Business: minimum monthly invoice

    'private_workspace_monthly' => 0, // extra per month for a private sub-domain (0 = included)

    'vat_rate'            => 0,      // % — set to your VAT rate once you are VAT-registered
    'invoice_due_days'    => 7,      // post-paid invoices are due this many days after issue
    'grace_days'          => 7,      // days after a due date before the account is limited
    'renewal_notice_days' => 5,      // pre-paid renewal invoice is sent this many days early

    'company_name'        => env('BILLING_COMPANY_NAME', 'ZamPayroll'),
    'company_address'     => env('BILLING_COMPANY_ADDRESS', 'Lusaka, Zambia'),
    'company_tpin'        => env('BILLING_COMPANY_TPIN'),
    'support_email'       => env('BILLING_SUPPORT_EMAIL', 'info@zampayroll.com'),
];
