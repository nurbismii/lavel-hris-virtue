<?php

namespace App\Services\Presensi;

use App\Models\Presensi;
use App\Models\PresensiVerification;
use Illuminate\Support\Carbon;

class PresensiVerificationStatusService
{
    public function createPending(Presensi $presensi, string $type, array $payload): PresensiVerification
    {
        $verification = PresensiVerification::firstOrNew([
            'presensi_id' => $presensi->id,
            'attendance_type' => $type,
        ]);

        $status = $this->normalizeStatus($payload['status'] ?? Presensi::STATUS_ABSEN_PENDING_REVIEW);
        $verified = $status === Presensi::STATUS_ABSEN_VERIFIED;

        $verification->fill([
            'nik_karyawan' => $presensi->nik_karyawan,
            'tanggal' => Carbon::parse($presensi->tanggal)->toDateString(),
            'status' => $status,
            'face_verified' => $verified,
            'face_selfie_path' => $payload['face_selfie_path'] ?? null,
            'face_selfie_hash' => $payload['face_selfie_hash'] ?? null,
            'face_verification_distance' => $payload['face_verification_distance'] ?? null,
            'face_verified_at' => $verified ? now() : null,
            'face_verification_method' => $payload['face_verification_method'] ?? 'server-side-async-pending',
            'face_verification_meta' => $payload['face_verification_meta'] ?? null,
            'presensi_challenge_id' => $payload['presensi_challenge_id'] ?? null,
            'submitted_at' => $payload['submitted_at'] ?? now(),
        ]);

        $verification->save();

        return $verification;
    }

    public function normalizeStatus(?string $status): string
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
}
