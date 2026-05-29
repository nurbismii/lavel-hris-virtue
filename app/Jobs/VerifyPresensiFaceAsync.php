<?php

namespace App\Jobs;

use App\Models\Presensi;
use App\Models\PresensiVerification;
use App\Models\User;
use App\Services\Presensi\AttendancePeriodLockService;
use App\Services\Presensi\ServerSideFaceVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class VerifyPresensiFaceAsync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 240;
    public $failOnTimeout = true;

    protected $presensiId;
    protected $userId;
    protected $referencePath;
    protected $challenge;
    protected $faceResult;
    protected $selfieAudit;
    protected $livenessEvidencePaths;
    protected $context;
    protected $presensiVerificationId;

    public function __construct(
        int $presensiId,
        string $userId,
        string $referencePath,
        array $challenge,
        array $faceResult,
        array $selfieAudit,
        array $livenessEvidencePaths,
        array $context = [],
        ?int $presensiVerificationId = null
    ) {
        $this->presensiId = $presensiId;
        $this->userId = $userId;
        $this->referencePath = $referencePath;
        $this->challenge = $challenge;
        $this->faceResult = $faceResult;
        $this->selfieAudit = $selfieAudit;
        $this->livenessEvidencePaths = $livenessEvidencePaths;
        $this->context = $context;
        $this->presensiVerificationId = $presensiVerificationId;
        $this->onQueue((string) config('services.presensi_face.queue', 'default'));
    }

    public function handle(ServerSideFaceVerificationService $verificationService): void
    {
        $presensi = Presensi::find($this->presensiId);
        $user = User::find($this->userId);
        $verificationRecord = $this->resolveVerificationRecord();

        if (!$presensi || !$user || ($this->presensiVerificationId && !$verificationRecord)) {
            $this->cleanupLivenessEvidence();

            return;
        }

        if (!$this->targetStillPending($presensi, $verificationRecord)) {
            $this->cleanupLivenessEvidence();

            return;
        }

        $context = array_merge($this->context, $this->verificationContext($verificationRecord));

        try {
            $verification = $verificationService->verifyStored(
                $presensi,
                $user,
                $this->referencePath,
                $this->challenge,
                $this->faceResult,
                $this->selfieAudit,
                $this->livenessEvidencePaths,
                $context
            );

            $this->updatePresensi($verification);
        } finally {
            $this->cleanupLivenessEvidence();
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Async presensi face verification job failed.', [
            'presensi_id' => $this->presensiId,
            'presensi_verification_id' => $this->presensiVerificationId,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);

        $this->markPendingReviewAfterFailure($exception);
        $this->cleanupLivenessEvidence();
    }

    protected function updatePresensi(array $verification): void
    {
        DB::transaction(function () use ($verification) {
            $status = (string) ($verification['status'] ?? Presensi::STATUS_ABSEN_PENDING_REVIEW);

            $status = $this->normalizeVerificationStatus($status);

            $provider = is_array($verification['provider'] ?? null)
                ? $verification['provider']
                : [];
            $verified = $status === Presensi::STATUS_ABSEN_VERIFIED;
            $verificationRecord = $this->lockedVerificationRecord();

            if ($this->presensiVerificationId && !$verificationRecord) {
                return;
            }

            if ($this->targetPeriodLocked($verificationRecord)) {
                return;
            }

            if ($verificationRecord) {
                if (
                    $verificationRecord->status !== Presensi::STATUS_ABSEN_PENDING_REVIEW
                    || !$this->matchesVerificationChallenge($verificationRecord)
                ) {
                    return;
                }

                $distance = $provider['distance'] ?? $verificationRecord->face_verification_distance;

                $verificationRecord->status = $status;
                $verificationRecord->face_verified = $verified;
                $verificationRecord->face_verified_at = $verified ? now() : null;
                $verificationRecord->face_verification_distance = is_numeric($distance)
                    ? (float) $distance
                    : $verificationRecord->face_verification_distance;
                $verificationRecord->face_verification_method = $verification['method'] ?? 'server-side-provider-async';
                $verificationRecord->face_verification_meta = $this->mergeVerificationMeta(
                    $verificationRecord->face_verification_meta,
                    $verification
                );
                $verificationRecord->save();
            }

            $presensi = Presensi::whereKey($this->presensiId)
                ->lockForUpdate()
                ->first();

            if (
                !$presensi
                || $presensi->status_absen !== Presensi::STATUS_ABSEN_PENDING_REVIEW
                || !$this->matchesCurrentChallenge($presensi)
            ) {
                return;
            }

            $distance = $provider['distance'] ?? $presensi->face_verification_distance;

            $presensi->status_absen = $status;
            $presensi->face_verified = $verified;
            $presensi->face_verified_at = $verified ? now() : null;
            $presensi->face_verification_distance = is_numeric($distance)
                ? (float) $distance
                : $presensi->face_verification_distance;
            $presensi->face_verification_method = $verification['method'] ?? 'server-side-provider-async';
            $presensi->face_verification_meta = $this->mergeVerificationMeta(
                $presensi->face_verification_meta,
                $verification
            );
            $presensi->save();
        });
    }

    protected function markPendingReviewAfterFailure(Throwable $exception): void
    {
        DB::transaction(function () use ($exception) {
            $verification = [
                'status' => Presensi::STATUS_ABSEN_PENDING_REVIEW,
                'passed' => false,
                'method' => 'server-side-provider-async-failed',
                'message' => 'Job verifikasi AI gagal dijalankan. Butuh review manual.',
                'provider' => [
                    'error' => Str::limit($exception->getMessage(), 500),
                ],
                'passive_liveness' => null,
            ];

            $verificationRecord = $this->lockedVerificationRecord();

            if ($this->presensiVerificationId && !$verificationRecord) {
                return;
            }

            if ($this->targetPeriodLocked($verificationRecord)) {
                return;
            }

            if ($verificationRecord) {
                if (
                    $verificationRecord->status !== Presensi::STATUS_ABSEN_PENDING_REVIEW
                    || !$this->matchesVerificationChallenge($verificationRecord)
                ) {
                    return;
                }

                $verificationRecord->face_verified = false;
                $verificationRecord->face_verified_at = null;
                $verificationRecord->face_verification_method = $verification['method'];
                $verificationRecord->face_verification_meta = $this->mergeVerificationMeta(
                    $verificationRecord->face_verification_meta,
                    $verification
                );
                $verificationRecord->save();
            }

            $presensi = Presensi::whereKey($this->presensiId)
                ->lockForUpdate()
                ->first();

            if (
                !$presensi
                || $presensi->status_absen !== Presensi::STATUS_ABSEN_PENDING_REVIEW
                || !$this->matchesCurrentChallenge($presensi)
            ) {
                return;
            }

            $presensi->face_verified = false;
            $presensi->face_verified_at = null;
            $presensi->face_verification_method = $verification['method'];
            $presensi->face_verification_meta = $this->mergeVerificationMeta(
                $presensi->face_verification_meta,
                $verification
            );
            $presensi->save();
        });
    }

    protected function mergeVerificationMeta(?string $currentMeta, array $verification): string
    {
        $meta = json_decode((string) $currentMeta, true);

        if (!is_array($meta)) {
            $meta = [];
        }

        $meta['server_face_verification'] = [
            'status' => $verification['status'] ?? null,
            'passed' => (bool) ($verification['passed'] ?? false),
            'method' => $verification['method'] ?? null,
            'message' => $verification['message'] ?? null,
            'provider' => $verification['provider'] ?? null,
            'passive_liveness' => $verification['passive_liveness'] ?? null,
            'async' => true,
            'validated_at' => now()->toIso8601String(),
        ];

        return json_encode($meta);
    }

    protected function normalizeVerificationStatus(string $status): string
    {
        if (in_array($status, [
            Presensi::STATUS_ABSEN_VERIFIED,
            Presensi::STATUS_ABSEN_PENDING_REVIEW,
            Presensi::STATUS_ABSEN_REJECTED,
        ], true)) {
            return $status;
        }

        return Presensi::STATUS_ABSEN_PENDING_REVIEW;
    }

    protected function matchesCurrentChallenge(Presensi $presensi): bool
    {
        $challengeId = (string) ($this->challenge['id'] ?? '');

        return $challengeId !== ''
            && hash_equals($challengeId, (string) $presensi->presensi_challenge_id);
    }

    protected function matchesVerificationChallenge(PresensiVerification $verification): bool
    {
        $challengeId = (string) ($this->challenge['id'] ?? '');

        return $challengeId !== ''
            && hash_equals($challengeId, (string) $verification->presensi_challenge_id);
    }

    protected function targetStillPending(Presensi $presensi, ?PresensiVerification $verification): bool
    {
        if ($verification) {
            return $verification->status === Presensi::STATUS_ABSEN_PENDING_REVIEW
                && $this->matchesVerificationChallenge($verification);
        }

        return $presensi->status_absen === Presensi::STATUS_ABSEN_PENDING_REVIEW
            && $this->matchesCurrentChallenge($presensi);
    }

    protected function resolveVerificationRecord(): ?PresensiVerification
    {
        if (!$this->presensiVerificationId) {
            return null;
        }

        return PresensiVerification::find($this->presensiVerificationId);
    }

    protected function lockedVerificationRecord(): ?PresensiVerification
    {
        if (!$this->presensiVerificationId) {
            return null;
        }

        return PresensiVerification::whereKey($this->presensiVerificationId)
            ->lockForUpdate()
            ->first();
    }

    protected function verificationContext(?PresensiVerification $verification): array
    {
        if (!$verification) {
            return [];
        }

        return [
            'face_selfie_path' => $verification->face_selfie_path,
            'presensi_verification_id' => $verification->id,
            'attendance_type' => $verification->attendance_type,
        ];
    }

    protected function targetPeriodLocked(?PresensiVerification $verification): bool
    {
        $tanggal = $verification && $verification->tanggal
            ? $verification->tanggal
            : Presensi::query()->whereKey($this->presensiId)->value('tanggal');

        return $tanggal
            ? app(AttendancePeriodLockService::class)->lockedPeriodForDate($tanggal) !== null
            : false;
    }

    protected function cleanupLivenessEvidence(): void
    {
        foreach ($this->livenessEvidencePaths as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }

            $normalizedPath = str_replace('\\', '/', ltrim($path, '/'));

            if (str_contains($normalizedPath, '..') || !Str::startsWith($normalizedPath, 'presensi-liveness/')) {
                continue;
            }

            Storage::disk('local')->delete($normalizedPath);
        }
    }
}
