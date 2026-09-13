<?php

return [
    'enabled' => env('PASSKEYS_ENABLED', false),
    'rp_name' => env('PASSKEYS_RP_NAME', env('APP_NAME', 'V-People')),
    // Exact web origin including port, without path or trailing slash.
    'origin' => env('PASSKEYS_ORIGIN', ''),
    'rp_id' => env('PASSKEYS_RP_ID', ''),
    'timeout' => 120,
    'max_per_user' => 10,
    'requests_per_session' => 20,
    'requests_per_ip' => env('PASSKEYS_REQUESTS_PER_IP', 600),
];
