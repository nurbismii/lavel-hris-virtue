<?php

namespace App\Services\SuratPeringatan;

use App\Models\User;
use App\Models\WarningLetterRequest;
use App\Models\WarningLetterVerificationLog;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class WarningLetterVerificationService
{
    public function credentials(array $snapshot): array
    {
        $token = bin2hex(random_bytes(32));

        return [
            'verification_token' => $token,
            'verification_token_hash' => hash('sha256', $token),
            'verification_document_hash' => $this->documentHash($snapshot),
        ];
    }

    public function documentHash(array $snapshot): string
    {
        return hash_hmac(
            'sha256',
            json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            (string) config('app.key')
        );
    }

    public function url(WarningLetterRequest $letter): ?string
    {
        if (!$letter->verification_token) {
            return null;
        }

        return route('warning-letters.verify', ['token' => $letter->verification_token]);
    }

    public function ensureCredentials(WarningLetterRequest $letter, User $actor): WarningLetterRequest
    {
        return DB::transaction(function () use ($letter, $actor) {
            $locked = WarningLetterRequest::query()->lockForUpdate()->findOrFail($letter->id);
            abort_unless($locked->status === WarningLetterRequest::APPROVED, 409, 'Verifikasi hanya tersedia untuk surat yang sudah diterbitkan.');

            try {
                $token = (string) $locked->verification_token;
            } catch (DecryptException $exception) {
                $token = '';
            }

            $tokenIsValid = preg_match('/^[a-f0-9]{64}$/', $token) === 1
                && filled($locked->verification_token_hash)
                && hash_equals((string) $locked->verification_token_hash, hash('sha256', $token));

            if ($tokenIsValid && filled($locked->verification_document_hash)) {
                return $locked;
            }

            $credentials = $this->credentials((array) $locked->letter_snapshot);
            DB::table($locked->getTable())->where('id', $locked->id)->update([
                'verification_token' => Crypt::encryptString($credentials['verification_token']),
                'verification_token_hash' => $credentials['verification_token_hash'],
                'verification_document_hash' => $credentials['verification_document_hash'],
                'updated_at' => now(),
            ]);
            WarningLetterVerificationLog::create([
                'warning_letter_request_id' => $locked->id,
                'event' => 'rotated',
                'actor_id' => (string) $actor->id,
                'accessed_at' => now(),
            ]);

            return $locked->refresh();
        });
    }

    public function qrDataUri(WarningLetterRequest $letter): string
    {
        $url = $this->url($letter);
        abort_unless($url, 500, 'Kode verifikasi surat belum tersedia. Hubungi administrator.');
        if (!class_exists(QrCode::class) || !class_exists(SvgWriter::class)) {
            throw new \RuntimeException('QR_CODE_PACKAGE_NOT_INSTALLED');
        }

        $qrCode = QrCode::create($url)
            ->setEncoding(new Encoding('UTF-8'))
            ->setErrorCorrectionLevel(ErrorCorrectionLevel::Medium)
            ->setSize(320)
            ->setMargin(12);

        return (new SvgWriter())->write($qrCode)->getDataUri();
    }

    public function find(string $token): ?WarningLetterRequest
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        return WarningLetterRequest::query()
            ->where('status', WarningLetterRequest::APPROVED)
            ->where('verification_token_hash', hash('sha256', $token))
            ->first();
    }

    public function publicData(WarningLetterRequest $letter): array
    {
        $snapshot = (array) $letter->letter_snapshot;
        $employee = (array) ($snapshot['employee'] ?? []);
        $status = 'valid';
        $currentDocumentHash = $this->documentHash($snapshot);
        if (!$letter->verification_document_hash || !hash_equals($letter->verification_document_hash, $currentDocumentHash)) {
            $status = 'mismatch';
        } elseif ($letter->verification_revoked_at) {
            $status = 'revoked';
        } elseif ($letter->tgl_berakhir->isBefore(now()->startOfDay())) {
            $status = 'expired';
        }

        return [
            'status' => $status,
            'number' => $letter->letter_number,
            'level' => WarningLetterRequest::levels()[$letter->level_sp] ?? $letter->level_sp,
            'employee_name' => $this->maskName((string) ($employee['name'] ?? '')),
            'nik' => $this->maskNik((string) $letter->nik),
            'department' => (string) ($employee['department'] ?? '-'),
            'issued_at' => $snapshot['issued_at'] ?? optional($letter->reviewed_at)->format('Y-m-d'),
            'valid_from' => $letter->tgl_mulai->format('Y-m-d'),
            'valid_until' => $letter->tgl_berakhir->format('Y-m-d'),
            'signer_name' => (string) ($snapshot['signer_name'] ?? '-'),
            'signer_position' => (string) ($snapshot['signer_position'] ?? '-'),
            'document_hash' => $letter->verification_document_hash,
        ];
    }

    public function recordAccess(WarningLetterRequest $letter, Request $request): void
    {
        WarningLetterVerificationLog::create([
            'warning_letter_request_id' => $letter->id,
            'event' => 'scan',
            'ip_hash' => $request->ip() ? hash_hmac('sha256', $request->ip(), (string) config('app.key')) : null,
            'user_agent_hash' => $request->userAgent() ? hash('sha256', $request->userAgent()) : null,
            'accessed_at' => now(),
        ]);
    }

    public function setRevoked(WarningLetterRequest $letter, bool $revoked, User $actor): WarningLetterRequest
    {
        return DB::transaction(function () use ($letter, $revoked, $actor) {
            $locked = WarningLetterRequest::query()->lockForUpdate()->findOrFail($letter->id);
            abort_unless($locked->status === WarningLetterRequest::APPROVED, 409, 'Verifikasi hanya tersedia untuk surat yang sudah diterbitkan.');
            $locked->update([
                'verification_revoked_at' => $revoked ? now() : null,
                'verification_revoked_by' => $revoked ? (string) $actor->id : null,
            ]);
            WarningLetterVerificationLog::create([
                'warning_letter_request_id' => $locked->id,
                'event' => $revoked ? 'revoked' : 'activated',
                'actor_id' => (string) $actor->id,
                'accessed_at' => now(),
            ]);

            return $locked;
        });
    }

    private function maskNik(string $nik): string
    {
        $length = mb_strlen($nik);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return mb_substr($nik, 0, 2) . str_repeat('*', $length - 5) . mb_substr($nik, -3);
    }

    private function maskName(string $name): string
    {
        return collect(preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY))
            ->map(function ($part) {
                $length = mb_strlen($part);
                return mb_substr($part, 0, 1) . str_repeat('*', max(1, $length - 1));
            })->implode(' ');
    }
}
