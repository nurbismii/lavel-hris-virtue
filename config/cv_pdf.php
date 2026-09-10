<?php

return [
    // Deliberately separate from QUEUE_CONNECTION=sync: never render in an HTTP request.
    'connection' => env('CV_PDF_QUEUE_CONNECTION', 'database'),
    'queue' => env('CV_PDF_QUEUE', 'cv-pdf'),
    'batch_limit' => env('CV_PDF_BATCH_LIMIT', 50),
    'retention_days' => env('CV_PDF_RETENTION_DAYS', 7),
    'max_file_bytes' => env('CV_PDF_MAX_FILE_BYTES', 10485760),
    'max_archive_bytes' => env('CV_PDF_MAX_ARCHIVE_BYTES', 104857600),
];
