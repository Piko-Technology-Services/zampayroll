<?php

/*
| Public website, SEO and social settings. Fill the social profile URLs in .env
| once the accounts exist — empty ones are simply not shown.
*/
return [
    'name'        => 'ZamPayroll',
    'url'         => rtrim(env('SITE_URL', 'https://zampayroll.com'), '/'),
    'tagline'     => 'HR & payroll made simple for Zambian businesses',
    'description' => 'ZamPayroll is HR and payroll software built for Zambia: PAYE, NAPSA and NHIMA calculated for you, payslips on every phone, leave, attendance, overtime and loans in one place, and an AI assistant. Free for teams of up to 10.',
    'og_image'    => env('SITE_OG_IMAGE', 'https://misc.zampayroll.com/images/zampayroll-og-image.png'),
    'logo'        => env('SITE_LOGO', 'https://misc.zampayroll.com/logo.png'),
    'locale'      => 'en_ZM',

    'contact' => [
        'email'    => env('SITE_EMAIL', 'info@zampayroll.com'),
        'phone'    => env('SITE_PHONE', '+260 776 136 965'),
        'whatsapp' => env('SITE_WHATSAPP', '260776136965'), // international format, digits only
        'address'  => 'Lusaka, Zambia',
        'hours'    => 'Mon – Fri, 08:00 – 17:00',
    ],

    // Profile links (Follow us). Leave empty until the account exists.
    'social' => [
        'facebook'  => env('SOCIAL_FACEBOOK'),
        'instagram' => env('SOCIAL_INSTAGRAM'),
        'tiktok'    => env('SOCIAL_TIKTOK'),
        'linkedin'  => env('SOCIAL_LINKEDIN'),
        'youtube'   => env('SOCIAL_YOUTUBE'),
        'x'         => env('SOCIAL_X'),
    ],

    'demo_video' => env('SITE_DEMO_VIDEO'), // YouTube/Vimeo embed URL
];
