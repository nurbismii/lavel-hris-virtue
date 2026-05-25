<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\EmployeeContractRenewal;
use App\Models\Resign;
use App\Services\Audit\AuditTrailService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class SyncTerminatedContractRenewals extends Command
{
    private const TERMINATION_STATUS = 'PUTUS KONTRAK';

    protected $signature = 'contracts:sync-terminated-employees {--limit=500 : Maksimal workflow yang diproses per run}';

    protected $description = 'Update status_resign karyawan menjadi PUTUS KONTRAK pada H+1 tanggal akhir kontrak.';

    public function handle(AuditTrailService $auditTrail): int
    {
        if (!$this->schemaReady()) {
            $this->error('Kolom sinkronisasi status karyawan belum tersedia. Jalankan migration terlebih dahulu.');
            return self::FAILURE;
        }

        $today = Carbon::today();
        $limit = max(1, min((int) $this->option('limit'), 2000));

        $renewals = EmployeeContractRenewal::query()
            ->with('employee')
            ->where('status', EmployeeContractRenewal::STATUS_CONTRACT_TERMINATED)
            ->where('assessment_months', EmployeeContractRenewal::ASSESSMENT_TERMINATE_CONTRACT)
            ->whereNull('employee_status_synced_at')
            ->whereDate('current_contract_end_date', '<', $today->format('Y-m-d'))
            ->orderBy('current_contract_end_date')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($renewals->isEmpty()) {
            $this->info('Tidak ada status putus kontrak yang perlu disinkronkan.');
            return self::SUCCESS;
        }

        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($renewals as $renewal) {
            try {
                $result = DB::transaction(function () use ($renewal, $today, $auditTrail) {
                    return $this->syncRenewal($renewal->id, $today, $auditTrail);
                });

                if ($result === 'updated') {
                    $updated++;
                } elseif ($result === 'skipped') {
                    $skipped++;
                }
            } catch (Throwable $exception) {
                $failed++;
                report($exception);
                $this->error('Gagal sinkronisasi workflow #' . $renewal->id . ': ' . $exception->getMessage());
            }
        }

        $this->info("Sinkronisasi putus kontrak selesai. Updated: {$updated}, skipped: {$skipped}, failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function syncRenewal(int $renewalId, Carbon $today, AuditTrailService $auditTrail): string
    {
        $renewal = EmployeeContractRenewal::query()
            ->whereKey($renewalId)
            ->lockForUpdate()
            ->first();

        if (!$renewal || $renewal->employee_status_synced_at) {
            return 'skipped';
        }

        if ($renewal->status !== EmployeeContractRenewal::STATUS_CONTRACT_TERMINATED
            || !$renewal->isTerminationDecision()
            || !Carbon::parse($renewal->current_contract_end_date)->lt($today)) {
            return 'skipped';
        }

        $employee = Employee::query()
            ->whereKey($renewal->employee_nik)
            ->lockForUpdate()
            ->first();

        if (!$employee) {
            $this->markRenewalSynced($renewal, 'Dilewati: data karyawan tidak ditemukan.');
            return 'skipped';
        }

        $currentStatus = strtoupper(trim((string) $employee->status_resign));

        if ($currentStatus !== '' && $currentStatus !== 'AKTIF' && $currentStatus !== self::TERMINATION_STATUS) {
            $this->markRenewalSynced($renewal, 'Dilewati: status karyawan sudah ' . $employee->status_resign . '.');
            return 'skipped';
        }

        $resign = Resign::query()
            ->where('nik_karyawan', $renewal->employee_nik)
            ->lockForUpdate()
            ->first();

        if ($resign && filled($resign->tipe) && strtoupper(trim((string) $resign->tipe)) !== self::TERMINATION_STATUS) {
            $this->markRenewalSynced($renewal, 'Dilewati: data resign sudah ada dengan tipe ' . $resign->tipe . '.');
            return 'skipped';
        }

        $endDate = Carbon::parse($renewal->current_contract_end_date)->format('Y-m-d');
        $reason = $renewal->assessment_note ?: 'Kontrak tidak diperpanjang berdasarkan approval HRD perpanjangan kontrak.';

        $oldValues = [
            'status_resign' => $employee->status_resign,
            'tgl_resign' => optional($employee->tgl_resign)->format('Y-m-d'),
            'alasan_resign' => $employee->alasan_resign,
            'kategori_keluar' => $employee->kategori_keluar,
        ];

        $this->upsertResignRecord($renewal, $endDate, $reason, $resign);

        $employeePayload = [
            'tgl_resign' => $endDate,
            'alasan_resign' => $reason,
            'status_resign' => self::TERMINATION_STATUS,
            'kategori_keluar' => self::TERMINATION_STATUS,
        ];

        $employee->forceFill($employeePayload)->save();

        $this->markRenewalSynced($renewal, 'Status karyawan otomatis diubah menjadi PUTUS KONTRAK pada H+1 tanggal akhir kontrak.');

        $auditTrail->record([
            'event' => 'contract_renewal.employee_status_auto_terminated',
            'module' => 'contract_renewal',
            'auditable_type' => Employee::class,
            'auditable_id' => (string) $employee->getKey(),
            'reference_table' => 'employee_contract_renewals',
            'reference_id' => (string) $renewal->id,
            'employee_nik' => (string) $employee->nik,
            'actor_id' => 'system',
            'actor_name' => 'System Scheduler',
            'actor_role' => 'system',
            'old_values' => $oldValues,
            'new_values' => $employeePayload,
            'metadata' => [
                'current_contract_end_date' => $endDate,
                'synced_at' => now()->toDateTimeString(),
            ],
            'note' => 'Auto update status_resign karena workflow perpanjangan kontrak diputus.',
        ]);

        return 'updated';
    }

    private function upsertResignRecord(EmployeeContractRenewal $renewal, string $endDate, string $reason, ?Resign $resign): void
    {
        $payload = [
            'nik_karyawan' => $renewal->employee_nik,
            'tanggal_pengajuan' => optional($renewal->hrd_approved_at)->format('Y-m-d') ?: now()->format('Y-m-d'),
            'tanggal_keluar' => $endDate,
            'alasan_keluar' => $reason,
            'tipe' => self::TERMINATION_STATUS,
            'periode_akhir' => $endDate,
        ];

        if (Schema::hasColumn('resign', 'flg_kirim')) {
            $payload['flg_kirim'] = 1;
        }

        if ($resign) {
            $resign->forceFill($payload)->save();
            return;
        }

        Resign::query()->create($payload);
    }

    private function markRenewalSynced(EmployeeContractRenewal $renewal, string $note): void
    {
        $renewal->forceFill([
            'employee_status_synced_at' => now(),
            'employee_status_sync_note' => Str::limit($note, 500, ''),
        ])->save();
    }

    private function schemaReady(): bool
    {
        return Schema::hasTable('employee_contract_renewals')
            && Schema::hasColumn('employee_contract_renewals', 'employee_status_synced_at')
            && Schema::hasColumn('employee_contract_renewals', 'employee_status_sync_note')
            && Schema::hasTable('employees')
            && Schema::hasColumn('employees', 'status_resign')
            && Schema::hasColumn('employees', 'tgl_resign')
            && Schema::hasColumn('employees', 'alasan_resign')
            && Schema::hasColumn('employees', 'kategori_keluar')
            && Schema::hasTable('resign')
            && Schema::hasColumn('resign', 'nik_karyawan')
            && Schema::hasColumn('resign', 'tanggal_pengajuan')
            && Schema::hasColumn('resign', 'tanggal_keluar')
            && Schema::hasColumn('resign', 'alasan_keluar')
            && Schema::hasColumn('resign', 'tipe')
            && Schema::hasColumn('resign', 'periode_akhir');
    }
}
