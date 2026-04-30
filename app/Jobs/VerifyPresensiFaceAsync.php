<?php

namespace App\Jobs;

use App\Models\Presensi;
use App\Models\User;
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

    public function __construct(
        int $presensiId,
        string $userId,
        string $referencePath,
        array $challenge,
        array $faceResult,
        array $selfieAudit,
        array $livenessEvidencePaths,
        array $context = []
    ) {
        $this->presensiId = $presensiId;
        $this->userId = $userId;
        $this->referencePath = $referencePath;
        $this->challenge = $challenge;
        $this->faceResult = $faceResult;
        $this->selfieAudit = $selfieAudit;
        $this->livenessEvidencePaths = $livenessEvidencePaths;
        $this->context = $context;
        $this->onQueue((string) config('services.presensi_face.queue', 'default'));
    }

    public function handle(ServerSideFaceVerificationService $verificationService): void
    {
        $presensi = Presensi::find($this->presensiId);
        $user = User::find($this->userId);

        if (!$presensi || !$user) {
            $this->cleanupLivenessEvidence();

            return;
        }

        if ($presensi->status_absen !== Presensi::STATUS_ABSEN_PENDING_REVIEW) {
            $this->cleanupLivenessEvidence();

            return;
        }

        if (!$this->matchesCurrentChallenge($presensi)) {
            $this->cleanupLivenessEvidence();

            return;
        }

        try {
            $verification = $verificationService->verifyStored(
                $presensi,
                $user,
                $this->referencePath,
                $this->challenge,
                $this->faceResult,
                $this->selfieAudit,
                $this->livenessEvidencePaths,
                $this->context
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
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);

        $this->markPendingReviewAfterFailure($exception);
        $this->cleanupLivenessEvidence();
    }

    protected function updatePresensi(array $verification): void
    {
        DB::transaction(function () use ($verification) {
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

            $status = (string) ($verification['status'] ?? Presensi::STATUS_ABSEN_PENDING_REVIEW);

            if (!in_array($status, [
                Presensi::STATUS_ABSEN_VERIFIED,
                Presensi::STATUS_ABSEN_PENDING_REVIEW,
                Presensi::STATUS_ABSEN_REJECTED,
            ], true)) {
                $status = Presensi::STATUS_ABSEN_PENDING_REVIEW;
            }

            $provider = is_array($verification['provider'] ?? null)
                ? $verification['provider']
                : [];
            $distance = $provider['distance'] ?? $presensi->face_verification_distance;
            $verified = $status === Presensi::STATUS_ABSEN_VERIFIED;

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

    protected function matchesCurrentChallenge(Presensi $presensi): bool
    {
        $challengeId = (string) ($this->challenge['id'] ?? '');

        return $challengeId !== ''
            && hash_equals($challengeId, (string) $presensi->presensi_challenge_id);
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
