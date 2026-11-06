<?php

/*
| ZamPayroll's own pricing. These are DEFAULTS only: the ZamPayroll admin
| console (Platform → Settings) overrides every value without a code change.
| Amounts are in ZMW.  ⚠ Proposed prices — confirm before publishing.
*/
return [
    'currency'            => 'ZMW',

    // Free experience: every new company gets this many months on the trial,
    // for up to `free_limit` active employees. After it, a paid plan is required.
    'trial_months'        => 2,
    'free_limit'          => 10,

    // Paid plans, chosen by active employees (see App\Services\Billing\Plans).
    // Teams of 1–10 employees pay the entry plan once the trial is over.
    'plan_tikula_monthly'     => 799,     // Tikula — 11–50 active employees
    'plan_tikula_annual'      => 7990,
    'plan_medium_monthly'     => 1499,    // Medium Enterprise — 51–150 active employees
    'plan_medium_annual'      => 14990,
    'plan_corporate_monthly'  => 2499,    // Corporate — 151–300 active employees
    'plan_corporate_annual'   => 24990,
    // Enterprise (301+ active employees) is priced by consultation: no invoice is created.

    // Legacy plans (existing accounts only; no longer offered to new companies)
    'prepaid_limit'       => 10,
    'starter_monthly'     => 350,
    'starter_annual'      => 3500,
    'business_per_employee' => 35,
    'business_minimum'    => 500,

    'private_workspace_monthly' => 0, // extra per month for a private sub-domain (0 = included)

    'vat_rate'            => 0,       // % — set to your VAT rate once you are VAT-registered
    'invoice_due_days'    => 7,       // invoices are due this many days after they are sent
    'grace_days'          => 7,       // days after a due date before the account is limited
    'renewal_notice_days' => 5,       // renewal invoice is sent this many days early

    // Manual payments: bank and mobile money details printed on every invoice.
    // Set these in the server environment (.env) before going live.
    'bank' => [
        'name'           => env('BILLING_BANK_NAME', 'First National Bank'),
        'account_name'   => env('BILLING_BANK_ACCOUNT_NAME', 'Piko Technology Services'),
        'account_number' => env('BILLING_BANK_ACCOUNT_NUMBER', '63216469741'),
        'branch'         => env('BILLING_BANK_BRANCH', 'Ndola Main Branch'),
        'swift'          => env('BILLING_BANK_SWIFT'),
    ],
    'mobile_money' => [
        ['network' => 'Airtel Money', 'number' => env('BILLING_AIRTEL_NUMBER')],
        ['network' => 'MTN MoMo',     'number' => env('BILLING_MTN_NUMBER')],
        ['network' => 'Zamtel Kwacha', 'number' => env('BILLING_ZAMTEL_NUMBER')],
    ],

    // Online payments: customers are sent to this page to pay.
    'online_payment_url'  => env('BILLING_ONLINE_PAYMENT_URL', 'https://pikozm.com/zampayroll-subscriptions'),

    // Who is emailed when a customer uploads proof of payment (comma-separated).
    'proof_admin_emails'  => array_values(array_filter(array_map('trim', explode(',', (string) env('BILLING_PROOF_ADMIN_EMAILS', 'billing@zampayroll.com'))))),

    // Partner programme (resellers)
    'partner_commission_rate'   => 20, // % of each paid invoice (before VAT) from a referred company
    'partner_commission_months' => 0,  // commission is paid for this many months after sign-up (0 = for as long as they pay)
    'partner_auto_approve'      => 1,  // 1 = partners get their code straight away, 0 = ZamPayroll reviews each application

    'company_name'        => env('BILLING_COMPANY_NAME', 'ZamPayroll'),
    'company_address'     => env('BILLING_COMPANY_ADDRESS', 'Lusaka, Zambia'),
    'company_tpin'        => env('BILLING_COMPANY_TPIN'),
    'support_email'       => env('BILLING_SUPPORT_EMAIL', 'info@zampayroll.com'),

    // Policies linked from the billing and checkout pages.
    'terms_url'           => env('BILLING_TERMS_URL', 'https://pikozm.com/docs/terms_and_conditions.pdf'),
    'privacy_url'         => env('BILLING_PRIVACY_URL', 'https://pikozm.com/docs/privacy_policy.pdf'),
    'refunds_url'         => env('BILLING_REFUNDS_URL', 'https://pikozm.com/docs/refund_and_cancellation-policy.pdf'),
];
