<?php

namespace App\Notifications;

use App\Jobs\SendRosterScheduleReminder;
use App\Models\RosterSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RosterScheduleReminderNotification extends Notification
{
    use Queueable;

    private RosterSchedule $schedule;
    private int $daysBefore;
    private string $mode;

    public function __construct(
        RosterSchedule $schedule,
        int $daysBefore = 14,
        string $mode = SendRosterScheduleReminder::MODE_SCHEDULED
    )
    {
        $this->schedule = $schedule;
        $this->daysBefore = $daysBefore;
        $this->mode = $mode;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $employeeName = optional($this->schedule->employee)->nama_karyawan ?: $notifiable->name;

        if ($this->isOverdue()) {
            return (new MailMessage)
                ->subject('Tindak Lanjut Jadwal Roster Terlewat')
                ->greeting('Halo ' . $employeeName . ',')
                ->line('Periode OFF roster ' . $this->schedule->period_label . ' telah dimulai dan pilihan realisasi masih menunggu tindak lanjut.')
                ->line('Periode cuti roster: ' . $this->schedule->off_start->format('d M Y') . ' - ' . $this->schedule->off_end->format('d M Y') . '.')
                ->line('Silakan segera ajukan Cuti Roster jika mengambil cuti roster atau pilih Insentif jika tetap bekerja sesuai permintaan HOD departemen.')
                // ->action('Buka Pengajuan Roster', $this->rosterUrl())
                ->line('Abaikan email ini bila pilihan roster Anda sudah diproses oleh HR.')
                ->salutation('HRIS V-People');
        }

        return (new MailMessage)
            ->subject('Reminder Jadwal Roster H-' . $this->daysBefore)
            ->greeting('Halo ' . $employeeName . ',')
            ->line('Jadwal roster periode ' . $this->schedule->period_label . ' akan dimulai ' . $this->daysBefore . ' hari lagi.')
            ->line('Masa kerja: ' . $this->schedule->work_start->format('d M Y') . ' - ' . $this->schedule->work_end->format('d M Y') . '.')
            ->line('Periode cuti roster: ' . $this->schedule->off_start->format('d M Y') . ' - ' . $this->schedule->off_end->format('d M Y') . '.')
            ->line('Silakan ajukan Cuti Roster jika mengambil cuti roster atau pilih Insentif jika tetap bekerja sesuai permintaan HOD departemen.')
            // ->action('Buka Pengajuan Roster', $this->rosterUrl())
            ->line('Abaikan email ini bila pilihan roster Anda sudah diproses oleh HR.')
            ->salutation('HRIS V-People');
    }

    public function toArray($notifiable): array
    {
        if ($this->isOverdue()) {
            $dispatchTimestamp = ($this->schedule->reminder_queued_at ?: now())->format('YmdHis');

            return [
                'judul' => 'Tindak Lanjut Jadwal Roster Terlewat',
                'pesan' => 'Periode OFF roster ' . $this->schedule->period_label . ' telah dimulai dan masih menunggu pilihan realisasi.',
                'url' => $this->rosterUrl(),
                'tipe' => 'Reminder Roster',
                'key' => 'roster_schedule:' . $this->schedule->id . ':overdue:' . $dispatchTimestamp,
                'metadata' => $this->metadata(),
            ];
        }

        return [
            'judul' => 'Reminder Jadwal Roster H-' . $this->daysBefore,
            'pesan' => 'Jadwal roster ' . $this->schedule->period_label . ' dimulai pada ' . $this->schedule->off_start->format('d M Y') . '.',
            'url' => $this->rosterUrl(),
            'tipe' => 'Reminder Roster',
            'key' => 'roster_schedule:' . $this->schedule->id . ':h-' . $this->daysBefore,
            'metadata' => $this->metadata(),
        ];
    }

    private function isOverdue(): bool
    {
        return $this->mode === SendRosterScheduleReminder::MODE_OVERDUE;
    }

    private function metadata(): array
    {
        return [
            'roster_schedule_id' => $this->schedule->id,
            'period_year' => $this->schedule->period_year,
            'period_number' => $this->schedule->period_number,
            'off_start' => $this->schedule->off_start->toDateString(),
            'mode' => $this->mode,
        ];
    }

    private function rosterUrl(): string
    {
        return route('roster.create', ['roster_schedule' => $this->schedule->id]);
    }
}
