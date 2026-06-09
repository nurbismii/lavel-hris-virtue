<?php

namespace App\Notifications;

use App\Models\EmployeeContract;
use App\Support\EmailUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ElectronicContractSignatureReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private int $contractId;
    private int $daysBeforeEnd;
    private string $baseUrl;

    public function __construct(int $contractId, int $daysBeforeEnd)
    {
        $this->contractId = $contractId;
        $this->daysBeforeEnd = $daysBeforeEnd;
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
        $endDate = optional($this->contractEndDate($contract))->format('d M Y') ?: '-';

        return (new MailMessage)
            ->subject('Reminder Tanda Tangan Kontrak Elektronik H-' . $this->daysBeforeEnd)
            ->greeting('Halo ' . ($notifiable->name ?: 'Karyawan') . ',')
            ->line('Kontrak elektronik Anda masih menunggu tanda tangan.')
            ->line('Nomor kontrak: ' . (optional($contract)->display_number ?: '-'))
            ->line('Tanggal akhir kontrak: ' . $endDate . ' (H-' . $this->daysBeforeEnd . ').')
            ->action('Login dan Tandatangani Kontrak', EmailUrl::login($contractUrl, $this->baseUrl))
            ->line('Silakan baca dokumen dengan teliti sebelum memberikan tanda tangan elektronik.')
            ->line('Jika Anda sudah menandatangani kontrak setelah email ini dikirim, abaikan pengingat ini.')
            ->salutation('HRD PT Virtue Dragon Nickel Industry');
    }

    public function toDatabase($notifiable): array
    {
        $contract = $this->contract();
        $endDate = optional($this->contractEndDate($contract))->format('d M Y') ?: '-';

        return [
            'judul' => 'Reminder Tanda Tangan Kontrak',
            'pesan' => 'Kontrak elektronik ' . (optional($contract)->display_number ?: '-') .
                ' masih menunggu tanda tangan. Tanggal akhir kontrak: ' . $endDate .
                ' (H-' . $this->daysBeforeEnd . ').',
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
        return EmployeeContract::query()
            ->with('employee:nik,nama_karyawan')
            ->find($this->contractId);
    }

    private function contractEndDate(?EmployeeContract $contract)
    {
        if (!$contract) {
            return null;
        }

        return $contract->contract_end_date ?: $contract->first_extension_end_date;
    }
}
