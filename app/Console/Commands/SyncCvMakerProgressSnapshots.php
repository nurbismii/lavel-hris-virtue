<?php

namespace App\Console\Commands;

use App\Services\CvMaker\CvMakerProgressSnapshotService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncCvMakerProgressSnapshots extends Command
{
    protected $signature = 'cv-maker:sync-progress
        {--limit=500 : Maksimal karyawan aktif yang diperiksa per run}
        {--chunk=100 : Ukuran batch query ke database CV Maker}
        {--now= : Waktu referensi untuk testing, format Y-m-d H:i:s}
        {--dry-run : Hitung progress tanpa menulis snapshot dan histori}';

    protected $description = 'Sinkronisasi snapshot progress CV Maker ke HRIS untuk badge reminder.';

    public function handle(CvMakerProgressSnapshotService $service): int
    {
        $limit = max(1, min((int) $this->option('limit'), 5000));
        $chunk = max(1, min((int) $this->option('chunk'), 1000));
        $dryRun = (bool) $this->option('dry-run');
        $now = $this->resolveNow();

        if (!$now) {
            return self::FAILURE;
        }

        $summary = $service->syncActiveEmployees($limit, $chunk, $dryRun, $now);

        if (!$summary['configured']) {
            $this->error('Koneksi CV Maker belum dikonfigurasi. Set CV_MAKER_DB_* dan CV_MAKER_NIK_HASH_KEY.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'CV Maker progress sync: checked=%d, synced=%d, skipped_no_profile=%d, histories=%d%s',
            $summary['checked'],
            $summary['synced'],
            $summary['skipped_no_profile'],
            $summary['history_created'],
            $dryRun ? ' (dry-run)' : ''
        ));

        return self::SUCCESS;
    }

    private function resolveNow(): ?Carbon
    {
        $value = $this->option('now');

        if (!$value) {
            return Carbon::now();
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable $exception) {
            $this->error('Opsi --now tidak valid. Gunakan format Y-m-d H:i:s.');

            return null;
        }
    }
}
