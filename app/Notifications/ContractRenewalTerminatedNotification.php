<?php

namespace App\Notifications;

use App\Models\EmployeeContractRenewal;
use App\Support\EmailUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractRenewalTerminatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private int $renewalId;
    private string $baseUrl;

    public function __construct(int $renewalId)
    {
        $this->renewalId = $renewalId;
        $this->baseUrl = EmailUrl::currentBaseUrl();
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
            ->action(
                'Login dan Buka Kotak Masuk',
                EmailUrl::login(EmailUrl::route('kotak-masuk.index', [], $this->baseUrl), $this->baseUrl)
            )
            ->salutation('HRD PT Virtue Dragon Nickel Industry');
    }

    public function toDatabase($notifiable): array
    {
        $renewal = $this->renewal();
        $endDate = optional(optional($renewal)->current_contract_end_date)->format('d M Y') ?: '-';

        return [
            'judul' => 'Informasi Status Kontrak Kerja',
            'pesan' => 'Kontrak kerja Anda tidak diperpanjang. Tanggal akhir kontrak: ' . $endDate . '.',
            'url' => route('kotak-masuk.index', [], false),
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
