<?php

namespace App\Notifications;

use App\Models\RosterSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RosterScheduleReminderNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly RosterSchedule $schedule, private readonly int $daysBefore = 14)
    {
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $employeeName = optional($this->schedule->employee)->nama_karyawan ?: $notifiable->name;

        return (new MailMessage)
            ->subject('Reminder Jadwal Roster H-' . $this->daysBefore)
            ->greeting('Halo ' . $employeeName . ',')
            ->line('Jadwal roster periode ' . $this->schedule->period_label . ' akan dimulai ' . $this->daysBefore . ' hari lagi.')
            ->line('Masa kerja: ' . $this->schedule->work_start->format('d M Y') . ' - ' . $this->schedule->work_end->format('d M Y') . '.')
            ->line('Periode off roster: ' . $this->schedule->off_start->format('d M Y') . ' - ' . $this->schedule->off_end->format('d M Y') . '.')
            ->line('Silakan ajukan Cuti Roster jika mengambil jadwal off, atau pilih Insentif jika tetap bekerja sesuai ketentuan HR.')
            ->action('Buka Pengajuan Roster', $this->rosterUrl())
            ->line('Abaikan email ini bila pilihan roster Anda sudah diproses oleh HR.')
            ->salutation('HRIS V-People');
    }

    public function toArray($notifiable): array
    {
        return [
            'judul' => 'Reminder Jadwal Roster H-' . $this->daysBefore,
            'pesan' => 'Jadwal roster ' . $this->schedule->period_label . ' dimulai pada ' . $this->schedule->off_start->format('d M Y') . '.',
            'url' => $this->rosterUrl(),
            'tipe' => 'Reminder Roster',
            'key' => 'roster_schedule:' . $this->schedule->id . ':h-' . $this->daysBefore,
            'metadata' => [
                'roster_schedule_id' => $this->schedule->id,
                'period_year' => $this->schedule->period_year,
                'period_number' => $this->schedule->period_number,
                'off_start' => $this->schedule->off_start->toDateString(),
            ],
        ];
    }

    private function rosterUrl(): string
    {
        return route('roster.create', ['roster_schedule' => $this->schedule->id]);
    }
}
