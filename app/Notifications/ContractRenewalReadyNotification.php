<?php

namespace App\Notifications;

use App\Models\EmployeeContract;
use App\Support\EmailUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractRenewalReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private int $contractId;
    private string $baseUrl;

    public function __construct(int $contractId)
    {
        $this->contractId = $contractId;
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
        $contract = $this->contract();
        $contractUrl = EmailUrl::route(
            'user-electronic-contracts.show',
            ['contract' => $this->contractId],
            $this->baseUrl
        );

        return (new MailMessage)
            ->subject('Kontrak Elektronik Perpanjangan Siap Ditandatangani')
            ->greeting('Halo ' . ($notifiable->name ?: 'Karyawan') . ',')
            ->line('Kontrak elektronik perpanjangan Anda sudah tersedia di V-People.')
            ->line('Nomor kontrak: ' . optional($contract)->display_number)
            ->action('Login dan Buka Kontrak Elektronik', EmailUrl::login($contractUrl, $this->baseUrl))
            ->line('Silakan baca dokumen dengan teliti sebelum memberikan tanda tangan elektronik.')
            ->salutation('HRD PT Virtue Dragon Nickel Industry');
    }

    public function toDatabase($notifiable): array
    {
        $contract = $this->contract();

        return [
            'judul' => 'Kontrak Perpanjangan Siap Ditandatangani',
            'pesan' => 'Kontrak elektronik ' . optional($contract)->display_number . ' sudah tersedia di menu Kontrak Elektronik.',
            'url' => route('user-electronic-contracts.show', ['contract' => $this->contractId], false),
            'tipe' => 'Kontrak Elektronik',
        ];
    }

    public function toArray($notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    private function contract(): ?EmployeeContract
    {
        return EmployeeContract::query()->find($this->contractId);
    }
}
