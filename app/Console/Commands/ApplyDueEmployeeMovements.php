<?php

namespace App\Console\Commands;

use App\Services\Karyawan\EmployeeMovementService;
use Illuminate\Console\Command;

class ApplyDueEmployeeMovements extends Command
{
    protected $signature = 'employee-movements:apply-due {--limit=200 : Maksimal pengajuan jatuh tempo yang diproses per run}';

    protected $description = 'Menerapkan promosi, demosi, dan mutasi karyawan yang sudah disetujui HRD dan jatuh tempo.';

    public function handle(EmployeeMovementService $service): int
    {
        $limit = max(1, min((int) $this->option('limit'), 1000));
        $summary = $service->applyDueMovements($limit);

        $this->info(sprintf(
            'Pergerakan karyawan jatuh tempo diperiksa: %d, diterapkan: %d, gagal: %d, dilewati: %d.',
            $summary['checked'],
            $summary['applied'],
            $summary['failed'],
            $summary['skipped']
        ));

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
