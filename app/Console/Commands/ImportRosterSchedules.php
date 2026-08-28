<?php

namespace App\Console\Commands;

use App\Services\Roster\RosterScheduleWorkbookImportService;
use App\Services\Audit\AuditTrailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportRosterSchedules extends Command
{
    protected $signature = 'roster:import-schedules
        {file : Path file XLSX roster}
        {--dry-run : Validasi dan hitung tanpa menyimpan data}';

    protected $description = 'Import jadwal aktif dan riwayat roster lama dari workbook tahun/periode I-V.';

    public function handle(RosterScheduleWorkbookImportService $service): int
    {
        try {
            $summary = $service->import(
                (string) $this->argument('file'),
                (bool) $this->option('dry-run')
            );
        } catch (Throwable $exception) {
            Log::warning('Roster schedule CLI import failed.', [
                'code' => 'roster_schedule_cli_import_failed',
                'exception_class' => get_class($exception),
            ]);
            $this->error('Import gagal diproses. Periksa log aplikasi untuk kode error.');
            return self::FAILURE;
        }

        $this->table(['Metrik', 'Jumlah'], collect($summary)->map(fn($value, $key) => [$key, $value])->values()->all());

        if (!$this->option('dry-run')) {
            app(AuditTrailService::class)->record([
                'event' => 'roster_schedule_history.imported',
                'module' => 'roster_schedule',
                'reference_table' => 'roster_schedule_histories',
                'new_values' => $summary,
                'metadata' => ['source' => 'cli_workbook'],
                'note' => 'Import jadwal dan riwayat roster dari workbook.',
            ]);
        }

        $this->info($this->option('dry-run') ? 'Dry-run selesai. Tidak ada data yang disimpan.' : 'Import roster selesai.');

        return self::SUCCESS;
    }
}
