<?php

/*
| Public website, SEO and social settings. Fill the social profile URLs in .env
| once the accounts exist — empty ones are simply not shown.
*/
return [
    'name'        => 'ZamPayroll',
    'url'         => rtrim(env('SITE_URL', 'https://zampayroll.com'), '/'),
    'tagline'     => 'HR & payroll made simple for Zambian businesses',
    'description' => 'ZamPayroll is HR and payroll software built for Zambia: PAYE, NAPSA and NHIMA calculated for you, payslips on every phone, leave, attendance, overtime and loans in one place, and an AI assistant. Free for 2 months for teams of up to 10.',
    'og_image'    => env('SITE_OG_IMAGE', 'https://misc.zampayroll.com/images/zampayroll-og-image.png'),
    'logo'        => env('SITE_LOGO', 'https://misc.zampayroll.com/logo.png'),
    'locale'      => 'en_ZM',

    // Who gets ZamPayroll team alerts: new company sign-ups, support tickets, partner applications and billing contact changes.
    // Comma-separated in .env (ADMIN_ALERT_EMAILS) if you want more than one.
    'admin_alert_emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('ADMIN_ALERT_EMAILS', 'admin@zampayroll.com'))))),

    'contact' => [
        'email'    => env('SITE_EMAIL', 'info@zampayroll.com'),
        'phone'    => env('SITE_PHONE', '+260 952 992 844'),
        'whatsapp' => env('SITE_WHATSAPP', '260952992844'), // international format, digits only
        'address'  => 'Lusaka, Zambia',
        'hours'    => 'Mon – Fri, 08:00 – 17:00',
    ],

    // Profile links (Follow us). Leave empty until the account exists.
    'social' => [
        'facebook'  => env('SOCIAL_FACEBOOK', 'https://web.facebook.com/p/Zampayroll-61594033763386/'),
        'instagram' => env('SOCIAL_INSTAGRAM'),
        'tiktok'    => env('SOCIAL_TIKTOK', 'https://www.tiktok.com/@zampayroll'),
        'linkedin'  => env('SOCIAL_LINKEDIN', 'https://www.linkedin.com/company/zampayroll/'),
        'youtube'   => env('SOCIAL_YOUTUBE', 'https://www.youtube.com/@ZamPayroll'),
        'x'         => env('SOCIAL_X'),
    ],

    'demo_video' => env('SITE_DEMO_VIDEO'), // YouTube/Vimeo embed URL
];
