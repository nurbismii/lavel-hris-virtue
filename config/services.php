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
