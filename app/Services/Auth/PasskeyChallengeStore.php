<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PasskeyChallengeStore
{
    public function issue(string $sessionId, string $purpose, array $payload): string
    {
        // Bounded, indexed cleanup: no cron or queue worker is required.
        $expired = DB::table('passkey_challenges')->where('expires_at', '<=', now())
            ->limit(100)->pluck('id');
        if ($expired->isNotEmpty()) {
            DB::table('passkey_challenges')->whereIn('id', $expired)->delete();
        }

        $id = bin2hex(random_bytes(32));
        DB::table('passkey_challenges')->insert([
            'id' => $id,
            'session_hash' => hash('sha256', $sessionId),
            'purpose' => $purpose,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'expires_at' => now()->addSeconds(config('passkeys.timeout')),
        ]);

        return $id;
    }

    public function consume(string $id, string $sessionId, string $purpose): array
    {
        // Commit consumption before verification, including unsuccessful attempts.
        $row = DB::transaction(function () use ($id, $sessionId, $purpose) {
            $query = DB::table('passkey_challenges')->where('id', $id)
                ->where('session_hash', hash('sha256', $sessionId))->where('purpose', $purpose);
            $row = (clone $query)->lockForUpdate()->first();
            if ($row) {
                $query->delete();
            }
            return $row;
        });

        if (!$row || now()->greaterThanOrEqualTo($row->expires_at)) {
            throw ValidationException::withMessages([
                'passkey' => 'Permintaan passkey sudah kedaluwarsa atau telah digunakan. Silakan mulai lagi.',
            ]);
        }

        return json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
    }
}
