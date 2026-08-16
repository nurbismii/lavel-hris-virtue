<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google_maps' => [
        'api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'recruitment' => [
        'base_url' => env('RECRUITMENT_API_URL'),
        'token' => env('RECRUITMENT_API_TOKEN'),
        'timeout' => env('RECRUITMENT_API_TIMEOUT', 10),
        'cache_ttl' => env('RECRUITMENT_DOCUMENT_CACHE_TTL', 3),
    ],

    'vhire' => [
        'base_url' => env('VHIRE_API_BASE_URL'),
        'outbound_token' => env('VHIRE_API_TOKEN'),
        'inbound_token' => env('VHIRE_HRIS_INBOUND_TOKEN', env('VHIRE_API_TOKEN')),
        'timeout' => env('VHIRE_API_TIMEOUT', 15),
        'queue' => env('VHIRE_SYNC_QUEUE', env('QUEUE_NAME', 'default')),
        'max_retry_attempts' => env('VHIRE_SYNC_MAX_RETRY_ATTEMPTS', 3),
        'bulk_generate_chunk_size' => env('VHIRE_BULK_GENERATE_CHUNK_SIZE', 25),
    ],

    'cv_maker' => [
        'transport' => env('CV_MAKER_TRANSPORT', 'database'),
        'connection' => env('CV_MAKER_DB_CONNECTION_NAME', 'cv_maker'),
        'nik_hash_key' => env('CV_MAKER_NIK_HASH_KEY'),
        'api_base_url' => env('CV_MAKER_API_BASE_URL'),
        'api_token' => env('CV_MAKER_API_TOKEN'),
        'api_timeout' => env('CV_MAKER_API_TIMEOUT', 15),
        'api_connect_timeout' => env('CV_MAKER_API_CONNECT_TIMEOUT', 5),
        'max_page_size' => env('CV_MAKER_COMPARE_MAX_PAGE_SIZE', 100),
        'public_url' => env('CV_MAKER_PUBLIC_URL'),
        'reminder_queue' => env('CV_MAKER_REMINDER_QUEUE', env('QUEUE_NAME', 'default')),
        'reminder_batch_limit' => env('CV_MAKER_REMINDER_BATCH_LIMIT', 500),
        'reminder_cooldown_days' => env('CV_MAKER_REMINDER_COOLDOWN_DAYS', 3),
        'reminder_delay_seconds' => env('CV_MAKER_REMINDER_DELAY_SECONDS', 2),
    ],

    'presensi_face' => [
        'endpoint' => env('PRESENSI_FACE_VERIFICATION_URL'),
        'token' => env('PRESENSI_FACE_VERIFICATION_TOKEN'),
        'timeout' => env('PRESENSI_FACE_VERIFICATION_TIMEOUT', 120),
        'connect_timeout' => env('PRESENSI_FACE_VERIFICATION_CONNECT_TIMEOUT', 10),
        'queue' => env('PRESENSI_FACE_QUEUE', env('QUEUE_NAME', 'default')),
        'min_confidence' => env('PRESENSI_FACE_MIN_CONFIDENCE', 0.78),
        'min_liveness_score' => env('PRESENSI_LIVENESS_MIN_SCORE', 0.78),
        'require_active_liveness' => env('PRESENSI_REQUIRE_ACTIVE_LIVENESS', true),
        'fail_closed' => env('PRESENSI_FACE_FAIL_CLOSED', false),
    ],

];
