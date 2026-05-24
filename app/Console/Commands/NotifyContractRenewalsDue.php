<?php

namespace App\Console\Commands;

use App\Models\ContractTemplate;
use App\Models\Employee;
use App\Models\EmployeeContractHistory;
use App\Models\EmployeeContractRenewal;
use App\Models\User;
use App\Notifications\StatusPengajuanNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class NotifyContractRenewalsDue extends Command
{
    protected $signature = 'contracts:notify-renewal-due {--days=30 : Window hari sebelum kontrak berakhir} {--limit=200 : Maksimal history yang diproses per run}';

    protected $description = 'Mengirim notifikasi kontrak yang akan berakhir kepada HOD/Admin Divisi/HR sesuai scope.';

    public function handle(): int
    {
        $days = max(1, min((int) $this->option('days'), 90));
        $limit = max(1, min((int) $this->option('limit'), 1000));
        $histories = $this->dueHistories($days, $limit)->get();

        if ($histories->isEmpty()) {
            $this->info('Tidak ada kontrak jatuh tempo yang perlu dinotifikasi.');
            return self::SUCCESS;
        }

        $recipients = User::query()
            ->with(['role', 'employee'])
            ->whereNotNull('role_id')
            ->get()
            ->filter(fn(User $user) => $user->hasRole(['Super Admin', 'HR', 'HOD', 'Admin Divisi'])
                && $user->hasMenuAccess('contract_renewal'))
            ->values();

        $sent = 0;

        foreach ($histories as $history) {
            $history->loadMissing('employee');
            $targets = $this->recipientsForHistory($recipients, $history);

            if ($targets->isEmpty()) {
                continue;
            }

            foreach ($targets as $user) {
                $user->notify(new StatusPengajuanNotification([
                    'judul' => 'Kontrak Akan Berakhir',
                    'pesan' => 'Kontrak ' . (optional($history->employee)->nama_karyawan ?: $history->employee_name ?: $history->nik) .
                        ' akan berakhir pada ' . optional($history->contract_end_date)->format('d M Y') . '.',
                    'url' => route('contract-renewals.index', ['days' => $days]),
                    'tipe' => 'Perpanjangan Kontrak',
                ]));
                $sent++;
            }

            $history->forceFill(['renewal_notice_sent_at' => now()])->save();
        }

        $this->info('Notifikasi perpanjangan kontrak terkirim: ' . $sent);
        return self::SUCCESS;
    }

    private function dueHistories(int $days, int $limit): Builder
    {
        $today = Carbon::today();
        $until = $today->copy()->addDays($days);

        $latestEndSubquery = EmployeeContractHistory::query()
            ->select('nik', DB::raw('MAX(contract_end_date) as latest_contract_end_date'))
            ->whereNotNull('contract_end_date')
            ->whereIn('history_type', [ContractTemplate::TYPE_PKWT_1, ContractTemplate::TYPE_ADDENDUM_PKWT])
            ->groupBy('nik');

        return EmployeeContractHistory::query()
            ->with('employee')
            ->select('employee_contract_histories.*')
            ->joinSub($latestEndSubquery, 'latest_contract_histories', function ($join) {
                $join->on('employee_contract_histories.nik', '=', 'latest_contract_histories.nik')
                    ->on('employee_contract_histories.contract_end_date', '=', 'latest_contract_histories.latest_contract_end_date');
            })
            ->whereNull('employee_contract_histories.renewal_notice_sent_at')
            ->whereBetween('employee_contract_histories.contract_end_date', [
                $today->format('Y-m-d'),
                $until->format('Y-m-d'),
            ])
            ->whereHas('employee')
            ->whereNotExists(function ($subQuery) {
                $subQuery->selectRaw('1')
                    ->from('employee_contract_renewals')
                    ->whereColumn('employee_contract_renewals.employee_nik', 'employee_contract_histories.nik')
                    ->whereColumn('employee_contract_renewals.current_contract_end_date', 'employee_contract_histories.contract_end_date');
            })
            ->orderBy('employee_contract_histories.contract_end_date')
            ->orderBy('employee_contract_histories.id')
            ->limit($limit);
    }

    private function recipientsForHistory($recipients, EmployeeContractHistory $history)
    {
        return $recipients->filter(function (User $user) use ($history) {
            if ($user->canAccessAllEmployees()) {
                return true;
            }

            return $user->applyEmployeeScope(
                Employee::query()->whereKey($history->nik)
            )->exists();
        });
    }
}
