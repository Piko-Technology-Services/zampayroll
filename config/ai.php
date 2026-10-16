<?php

return [
    // AI mode (floating assistant). Without a key it runs in "basic mode":
    // a built-in keyword engine that still answers the common questions
    // and prepares actions — just less flexible with free-form language.
    'enabled'   => filter_var(env('AI_MODE_ENABLED', true), FILTER_VALIDATE_BOOL),

    // "basic"     = built-in rules on your own server. No external calls, no cost. (default)
    // "anthropic" = Claude API (paid per message; needs ANTHROPIC_API_KEY).
    'provider'  => env('AI_PROVIDER', 'basic'),

    'api_key'   => env('ANTHROPIC_API_KEY'),
    'model'     => env('AI_MODEL', 'claude-sonnet-5-5'),
    'endpoint'  => env('AI_ENDPOINT', 'https://api.anthropic.com/v1/messages'),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 2048),
    'timeout'   => (int) env('AI_TIMEOUT', 60),

    // How long a proposed action (e.g. "approve 3 leave requests") waits for a click on Confirm.
    'action_ttl_minutes' => 15,

    // Largest attachment text sent to the model (characters).
    'attachment_char_limit' => 60000,
];
