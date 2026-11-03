<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Public Employee Registration
    |--------------------------------------------------------------------------
    |
    | Self-registration can claim an employee identity from a NIK, so keep it
    | closed by default. Enable only when HR has a controlled verification
    | process for employee ownership.
    |
    */
    'self_registration_enabled' => env('HRIS_SELF_REGISTRATION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Email Link Base URL
    |--------------------------------------------------------------------------
    |
    | Queued notifications do not have access to the browser request host, so
    | email action links need a stable public URL. Set this to the production
    | HRIS domain when APP_URL cannot be trusted on the queue worker.
    |
    */
    'email' => [
        'base_url' => env('HRIS_EMAIL_BASE_URL', env('APP_URL', 'http://localhost')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mobile App Version Guard
    |--------------------------------------------------------------------------
    |
    | Android/iOS clients call the public app-version endpoint before the
    | HRIS page is loaded. Increase the minimum version when old APK builds
    | must be blocked, and increase the latest version when an update is
    | available but not mandatory yet.
    |
    */
    'mobile_app' => [
        'gps_signer_sha256' => env('MOBILE_APP_GPS_SIGNER_SHA256', '006b134a51a3f84db456c3a3ff4b9411c143055f4de9a33a5978f643992af576'),
        'required' => env('MOBILE_APP_REQUIRED', true),
        'ios_required' => env('MOBILE_APP_IOS_REQUIRED', false),
        'android_download_url' => env('MOBILE_APP_ANDROID_DOWNLOAD_URL'),
        'ios_download_url' => env('MOBILE_APP_IOS_DOWNLOAD_URL'),
        'ios_minimum_version_code' => (int) env('MOBILE_APP_IOS_MINIMUM_VERSION_CODE', 1),
        'ios_latest_version_code' => (int) env('MOBILE_APP_IOS_LATEST_VERSION_CODE', 1),
        'ios_latest_version_name' => env('MOBILE_APP_IOS_LATEST_VERSION_NAME', '1.0.0'),
        'minimum_version_code' => (int) env('MOBILE_APP_MINIMUM_VERSION_CODE', 2),
        'latest_version_code' => (int) env('MOBILE_APP_LATEST_VERSION_CODE', 7),
        'latest_version_name' => env('MOBILE_APP_LATEST_VERSION_NAME', '1.0.7'),
        'download_url' => env('MOBILE_APP_DOWNLOAD_URL'),
        'rate_limit_per_minute' => (int) env('MOBILE_APP_VERSION_RATE_LIMIT_PER_MINUTE', 600),
        'force_update_message' => env(
            'MOBILE_APP_FORCE_UPDATE_MESSAGE',
            'Versi aplikasi V-People Anda sudah tidak didukung. Silakan update aplikasi untuk melanjutkan.'
        ),
        'optional_update_message' => env(
            'MOBILE_APP_OPTIONAL_UPDATE_MESSAGE',
            'Update aplikasi V-People tersedia.'
        ),
    ],
];
