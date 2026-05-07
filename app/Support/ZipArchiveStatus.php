<?php

namespace App\Support;

use ZipArchive;

class ZipArchiveStatus
{
    public static function message($status, string $context = 'ZIP'): string
    {
        if ($status === true) {
            return "{$context} berhasil dibuka.";
        }

        $messages = [
            ZipArchive::ER_EXISTS => 'File sudah ada.',
            ZipArchive::ER_INCONS => 'Struktur ZIP tidak konsisten.',
            ZipArchive::ER_INVAL => 'File ZIP tidak valid.',
            ZipArchive::ER_MEMORY => 'Memori server tidak cukup untuk membaca ZIP.',
            ZipArchive::ER_NOENT => 'File ZIP tidak ditemukan.',
            ZipArchive::ER_NOZIP => 'File bukan ZIP yang valid.',
            ZipArchive::ER_OPEN => 'File ZIP tidak dapat dibuka.',
            ZipArchive::ER_READ => 'File ZIP tidak dapat dibaca.',
            ZipArchive::ER_SEEK => 'File ZIP tidak dapat diproses.',
        ];

        $reason = $messages[$status] ?? 'File ZIP tidak dapat diproses.';

        return "{$context} ditolak. {$reason}";
    }
}
