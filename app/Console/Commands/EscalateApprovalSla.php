<?php

namespace App\Console\Commands;

use App\Services\Approvals\ApprovalSlaService;
use Illuminate\Console\Command;

class EscalateApprovalSla extends Command
{
    protected $signature = 'approvals:escalate-sla {--limit= : Batas maksimum item overdue yang diperiksa} {--dry-run : Hitung item tanpa mengirim notifikasi}';

    protected $description = 'Mengirim eskalasi untuk approval yang melewati SLA.';

    public function handle(ApprovalSlaService $service): int
    {
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $dryRun = (bool) $this->option('dry-run');
        $result = $service->escalateOverdue(null, $limit, $dryRun);

        if ($result['missing_table'] ?? false) {
            $this->error('Tabel approval_sla_escalation_logs belum tersedia. Jalankan php artisan migrate terlebih dahulu.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'SLA approval diperiksa: %d, eskalasi baru: %d, dilewati: %d%s',
            $result['checked'],
            $result['created'],
            $result['skipped'],
            $dryRun ? ' (dry-run)' : ''
        ));

        return self::SUCCESS;
    }
}
