<?php

namespace App\Jobs;

use App\Models\CvMakerProgressStatus;
use App\Models\CvMakerReminderDelivery;
use App\Models\User;
use App\Notifications\CvMakerProgressReminderNotification;
use App\Services\CvMaker\CvMakerReminderService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendCvMakerReminderEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [60, 300, 900];

    private int $deliveryId;

    public function __construct(int $deliveryId)
    {
        $this->deliveryId = $deliveryId;
    }

    public function handle(CvMakerReminderService $service): void
    {
        $delivery = CvMakerReminderDelivery::query()->with('batch')->find($this->deliveryId);

        if (!$delivery || $delivery->status !== CvMakerReminderDelivery::STATUS_PENDING) {
            return;
        }

        $progress = CvMakerProgressStatus::query()->where('employee_nik', $delivery->employee_nik)->first();
        $user = $delivery->user_id ? User::query()->find($delivery->user_id) : null;

        if (!$progress || !$progress->needs_reminder || !$user || !filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            $delivery->forceFill([
                'status' => CvMakerReminderDelivery::STATUS_SKIPPED,
                'skip_reason' => 'Status progress atau email berubah sebelum reminder dikirim.',
            ])->save();
            $service->refreshBatch($delivery->batch);
            return;
        }

        $user->notify(new CvMakerProgressReminderNotification(
            (int) $progress->current_step,
            (string) ($progress->current_step_label ?: 'Tahap berikutnya')
        ));

        $delivery->forceFill([
            'status' => CvMakerReminderDelivery::STATUS_SENT,
            'email' => $user->email,
            'sent_at' => Carbon::now(),
            'error_message' => null,
        ])->save();
        $service->refreshBatch($delivery->batch);
    }

    public function failed(Throwable $exception): void
    {
        $delivery = CvMakerReminderDelivery::query()->with('batch')->find($this->deliveryId);

        if (!$delivery || $delivery->status === CvMakerReminderDelivery::STATUS_SENT) {
            return;
        }

        $delivery->forceFill([
            'status' => CvMakerReminderDelivery::STATUS_FAILED,
            'error_message' => mb_substr($exception->getMessage(), 0, 500),
        ])->save();

        if ($delivery->batch) {
            app(CvMakerReminderService::class)->refreshBatch($delivery->batch);
        }
    }
}
