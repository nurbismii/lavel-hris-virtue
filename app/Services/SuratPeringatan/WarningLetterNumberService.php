<?php

namespace App\Services\SuratPeringatan;

use App\Models\SuratPeringatan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarningLetterNumberService
{
    // All writers (approval and legacy import) take this same lock inside a transaction.
    public function lock(): object
    {
        $sequence = DB::table('warning_letter_sequences')->where('key', 'sp')->lockForUpdate()->first();
        if (!$sequence) {
            throw ValidationException::withMessages(['approval' => 'Konfigurasi nomor SP belum tersedia. Hubungi administrator sistem.']);
        }

        return $sequence;
    }

    public function next(): int
    {
        $sequence = $this->lock();
        $cast = DB::getDriverName() === 'sqlite' ? 'INTEGER' : 'UNSIGNED';
        $legacyMax = (int) SuratPeringatan::query()->selectRaw('MAX(CAST(no_sp AS ' . $cast . ')) AS highest')->value('highest');
        $next = max((int) $sequence->last_number, $legacyMax) + 1;
        if ($next > 99999999) {
            throw ValidationException::withMessages(['approval' => 'Nomor SP melebihi kapasitas kolom lama. Hubungi administrator sistem.']);
        }
        DB::table('warning_letter_sequences')->where('key', 'sp')->update(['last_number' => $next]);

        return $next;
    }
}
