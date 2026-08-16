<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CvMakerProgressReminderNotification extends Notification
{
    use Queueable;

    private int $currentStep;
    private string $currentStepLabel;

    public function __construct(int $currentStep, string $currentStepLabel)
    {
        $this->currentStep = $currentStep;
        $this->currentStepLabel = $currentStepLabel;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Pengingat Melengkapi CV Maker')
            ->greeting('Halo ' . ($notifiable->name ?: 'Karyawan') . ',')
            ->line('Data CV Maker Anda masih belum lengkap dan belum dilanjutkan dalam beberapa waktu terakhir.')
            ->line('Tahap yang perlu dilanjutkan: Tahap ' . $this->currentStep . '/8 - ' . $this->currentStepLabel . '.')
            ->line('Mohon periksa kembali data dan dokumen sebelum menyelesaikan CV Anda.');

        $publicUrl = trim((string) config('services.cv_maker.public_url'));

        if ($publicUrl !== '' && preg_match('/^https?:\/\//i', $publicUrl)) {
            $message->action('Lanjutkan Pengisian CV', $publicUrl);
        }

        return $message
            ->line('Jika Anda baru saja melengkapinya, abaikan email pengingat ini.')
            ->salutation('HRD PT Virtue Dragon Nickel Industry');
    }
}
