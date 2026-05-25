<?php

namespace App\Notifications;

use App\Models\EmployeeContractRenewal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractRenewalTerminatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private int $renewalId;

    public function __construct(int $renewalId)
    {
        $this->renewalId = $renewalId;
    }

    public function via($notifiable): array
    {
        $channels = ['database'];

        if (filled($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $renewal = $this->renewal();
        $endDate = optional(optional($renewal)->current_contract_end_date)->format('d M Y') ?: '-';

        return (new MailMessage)
            ->subject('Informasi Status Kontrak Kerja')
            ->greeting('Halo ' . ($notifiable->name ?: 'Karyawan') . ',')
            ->line('Berdasarkan hasil proses evaluasi perpanjangan kontrak, kontrak kerja Anda tidak diperpanjang.')
            ->line('Tanggal akhir kontrak: ' . $endDate)
            ->line('Silakan menghubungi HRD untuk informasi administrasi dan arahan selanjutnya.')
            ->action('Buka Kotak Masuk', route('kotak-masuk.index'))
            ->salutation('HRD PT Virtue Dragon Nickel Industry');
    }

    public function toDatabase($notifiable): array
    {
        $renewal = $this->renewal();
        $endDate = optional(optional($renewal)->current_contract_end_date)->format('d M Y') ?: '-';

        return [
            'judul' => 'Informasi Status Kontrak Kerja',
            'pesan' => 'Kontrak kerja Anda tidak diperpanjang. Tanggal akhir kontrak: ' . $endDate . '.',
            'url' => route('kotak-masuk.index'),
            'tipe' => 'Perpanjangan Kontrak',
        ];
    }

    public function toArray($notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    private function renewal(): ?EmployeeContractRenewal
    {
        return EmployeeContractRenewal::query()->find($this->renewalId);
    }
}
