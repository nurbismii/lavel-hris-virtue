<?php

return [
    'connection' => env('SEVERANCE_QUEUE_CONNECTION', 'database'),
    'queue' => env('SEVERANCE_QUEUE'),
    'max_rows' => 10000,
    'max_export_rows' => 1000,
    'rules_version' => 'MASTER-2025-PP35-UPMK-2026-11-15',
    'articles' => [
        '16' => '16 — Kompensasi PKWT',
        '40' => '40 — Pesangon dasar (1 kali)',
        '50' => '50 — Mengundurkan diri sesuai prosedur',
        '51' => '51 — Mangkir',
        '52(1)' => '52(1) — Pelanggaran setelah surat peringatan',
        '52(2)' => '52(2) — Pelanggaran mendesak',
        '55' => '55 — Sakit berkepanjangan',
        '56' => '56 — Pensiun',
    ],
];
