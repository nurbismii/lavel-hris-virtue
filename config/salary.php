<?php

return [
    'import' => [
        'connection' => env('SALARY_IMPORT_QUEUE_CONNECTION', 'database'),
        'queue' => env('SALARY_IMPORT_QUEUE', 'default'),
        'max_kb' => 5120,
        'max_rows' => 10000,
    ],
];
