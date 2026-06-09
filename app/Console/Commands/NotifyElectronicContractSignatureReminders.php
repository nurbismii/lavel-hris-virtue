<?php

namespace App\Console\Commands;

use App\Models\ElectronicContractAuditLog;
use App\Models\EmployeeContract;
use App\Models\User;
use App\Notifications\ElectronicContractSignatureReminderNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class NotifyElectronicContractSignatureReminders extends Command
{
    private const EVENT_QUEUED = 'contract_signature_reminder_email_queued';
    private const EVENT_SKIPPED = 'contract_signature_reminder_email_skipped';

    protected $signature = 'contracts:notify-signature-reminders
        {--days=14,7,3 : Jadwal H-n sebelum tanggal akhir kontrak, pisahkan dengan koma}
        {--limit=500 : Maksimal kontrak yang diproses per run}';

    protected $description = 'Mengirim reminder email untuk kontrak elektronik yang belum ditandatangani pada H-14, H-7, dan H-3.';

    public function handle(): int
    {
        $days = $this->reminderDays();

        if ($days->isEmpty()) {
            $this->error('Opsi --days tidak valid.');
            return self::FAILURE;
        }

        $limit = max(1, min((int) $this->option('limit'), 1000));
        $today = Carbon::today();
        $targetDates = $days
            ->map(fn(int $day) => $today->copy()->addDays($day)->format('Y-m-d'))
            ->values()
            ->all();

        $contracts = $this->dueContracts($targetDates, $limit)->get();

        if ($contracts->isEmpty()) {
            $this->info('Tidak ada kontrak elektronik yang perlu direminder.');
            return self::SUCCESS;
        }

        $queued = 0;
        $skippedNoRecipient = 0;
        $skippedDuplicate = 0;

        foreach ($contracts as $contract) {
            $dueDate = $this->contractDueDate($contract);

            if (!$dueDate) {
                continue;
            }

            $daysBeforeEnd = $today->diffInDays($dueDate, false);

            if (!$days->contains($daysBeforeEnd)) {
                continue;
            }

            if ($this->alreadyRecorded(self::EVENT_QUEUED, $contract, $daysBeforeEnd, $dueDate)) {
                $skippedDuplicate++;
                continue;
            }

            $recipient = $this->recipientForContract($contract);

            if (!$recipient || blank($recipient->email)) {
                $skippedNoRecipient++;
                $this->recordSkippedNoRecipient($contract, $daysBeforeEnd, $dueDate);
                continue;
            }

            $recipient->notify(new ElectronicContractSignatureReminderNotification($contract->id, $daysBeforeEnd));
            $this->recordQueued($contract, $recipient, $daysBeforeEnd, $dueDate);
            $queued++;
        }

        $this->info(sprintf(
            'Reminder tanda tangan kontrak: %d email queued, %d tanpa email/user, %d sudah pernah queued.',
            $queued,
            $skippedNoRecipient,
            $skippedDuplicate
        ));

        return self::SUCCESS;
    }

    private function reminderDays(): Collection
    {
        return collect(explode(',', (string) $this->option('days')))
            ->map(fn($day) => (int) trim((string) $day))
            ->filter(fn(int $day) => $day > 0 && $day <= 90)
            ->unique()
            ->sortDesc()
            ->values();
    }

    private function dueContracts(array $targetDates, int $limit): Builder
    {
        return EmployeeContract::query()
            ->with(['employee:nik,nama_karyawan'])
            ->where('status', EmployeeContract::STATUS_READY)
            ->where('signing_method', EmployeeContract::SIGNING_METHOD_ELECTRONIC)
            ->where('signature_status', EmployeeContract::SIGNATURE_STATUS_WAITING)
            ->whereDoesntHave('signature')
            ->where(function (Builder $query) use ($targetDates) {
                $query->whereIn('contract_end_date', $targetDates)
                    ->orWhere(function (Builder $fallbackQuery) use ($targetDates) {
                        $fallbackQuery
                            ->whereNull('contract_end_date')
                            ->whereIn('first_extension_end_date', $targetDates);
                    });
            })
            ->orderBy('contract_end_date')
            ->orderBy('id')
            ->limit($limit);
    }

    private function contractDueDate(EmployeeContract $contract): ?Carbon
    {
        $date = $contract->contract_end_date ?: $contract->first_extension_end_date;

        return $date ? Carbon::parse($date)->startOfDay() : null;
    }

    private function recipientForContract(EmployeeContract $contract): ?User
    {
        $nik = $contract->nik ?: $contract->employee_nik;

        if (blank($nik)) {
            return null;
        }

        return User::query()
            ->where('nik_karyawan', $nik)
            ->whereNotNull('email')
            ->where(function (Builder $query) {
                $query->whereNull('status')
                    ->orWhere('status', 'aktif');
            })
            ->first();
    }

    private function alreadyRecorded(string $event, EmployeeContract $contract, int $daysBeforeEnd, Carbon $dueDate): bool
    {
        return ElectronicContractAuditLog::query()
            ->where('employee_contract_id', $contract->id)
            ->where('event', $event)
            ->where('metadata', 'like', '%"reminder_day":' . $daysBeforeEnd . '%')
            ->where('metadata', 'like', '%"contract_end_date":"' . $dueDate->format('Y-m-d') . '"%')
            ->exists();
    }

    private function recordQueued(EmployeeContract $contract, User $recipient, int $daysBeforeEnd, Carbon $dueDate): void
    {
        $this->recordAudit($contract, self::EVENT_QUEUED, [
            'reminder_day' => $daysBeforeEnd,
            'contract_end_date' => $dueDate->format('Y-m-d'),
            'recipient_user_id' => (string) $recipient->id,
            'recipient_nik' => $recipient->nik_karyawan,
        ]);
    }

    private function recordSkippedNoRecipient(EmployeeContract $contract, int $daysBeforeEnd, Carbon $dueDate): void
    {
        if ($this->alreadyRecorded(self::EVENT_SKIPPED, $contract, $daysBeforeEnd, $dueDate)) {
            return;
        }

        $this->recordAudit($contract, self::EVENT_SKIPPED, [
            'reminder_day' => $daysBeforeEnd,
            'contract_end_date' => $dueDate->format('Y-m-d'),
            'reason' => 'recipient_user_or_email_not_found',
        ]);
    }

    private function recordAudit(EmployeeContract $contract, string $event, array $metadata): void
    {
        ElectronicContractAuditLog::create([
            'employee_contract_id' => $contract->id,
            'nik' => $contract->nik ?: $contract->employee_nik,
            'event' => $event,
            'actor_user_id' => null,
            'actor_name' => 'HRIS Scheduler',
            'ip_address' => null,
            'user_agent' => null,
            'metadata' => $metadata,
        ]);
    }
}
