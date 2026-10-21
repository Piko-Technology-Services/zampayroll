<?php

/*
| DPO Pay (Network International). Put your keys in .env — never in code:
|   DPO_COMPANY_TOKEN=...
|   DPO_SERVICE_TYPE=...
|   DPO_MODE=live            (or "sandbox")
*/
return [
    'company_token' => env('DPO_COMPANY_TOKEN'),
    'service_type'  => env('DPO_SERVICE_TYPE'),
    'mode'          => env('DPO_MODE', 'sandbox'),

    'endpoints' => [
        'live'    => ['api' => 'https://secure.3gdirectpay.com/API/v6/', 'pay' => 'https://secure.3gdirectpay.com/payv2.php?ID='],
        'sandbox' => ['api' => 'https://secure1.sandbox.directpay.online/API/v6/', 'pay' => 'https://secure1.sandbox.directpay.online/payv2.php?ID='],
    ],

    // Payment-time limit on DPO's side, in hours.
    'ptl' => 5,

    // Mobile money networks offered in-app. Confirm the exact MNO names with DPO for your account.
    'mobile_country' => env('DPO_MNO_COUNTRY', 'zambia'),
    'networks' => [
        'airtel' => ['label' => 'Airtel Money', 'mno' => env('DPO_MNO_AIRTEL', 'Airtel'), 'prefixes' => ['097', '077']],
        'mtn'    => ['label' => 'MTN MoMo', 'mno' => env('DPO_MNO_MTN', 'MTN'), 'prefixes' => ['096', '076']],
        'zamtel' => ['label' => 'Zamtel Kwacha', 'mno' => env('DPO_MNO_ZAMTEL', 'Zamtel'), 'prefixes' => ['095', '075']],
    ],

    // Local development / automated tests only: pretend DPO approved every payment.
    'fake' => (bool) env('DPO_FAKE', false),
];
