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
];
