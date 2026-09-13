<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\UserPasskey;
use App\Services\Audit\AuditTrailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;

class PasskeyService
{
    public function __construct(private PasskeyChallengeStore $challenges)
    {
    }

    public function available(): bool
    {
        if (!config('passkeys.enabled') || !class_exists(WebAuthn::class)) {
            return false;
        }
        $origin = (string) config('passkeys.origin');
        $parts = parse_url($origin);
        $rpId = (string) config('passkeys.rp_id');
        // Restrict to the exact host. Do not infer trust from the request Host header.
        if (!$parts || !$rpId || ($parts['host'] ?? '') !== $rpId
            || isset($parts['path']) || isset($parts['query']) || isset($parts['fragment'])
            || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        if (($parts['scheme'] ?? '') !== 'https'
            && !(app()->environment(['local', 'testing']) && $rpId === 'localhost'
                && ($parts['scheme'] ?? '') === 'http')) {
            return false;
        }

        return Schema::hasTable('user_passkeys') && Schema::hasTable('passkey_challenges');
    }

    public function registrationOptions(User $user, string $sessionId, string $name): array
    {
        $this->checkLimit($user);
        $server = $this->server();
        $ids = $user->passkeys()->pluck('credential_id')->map(fn ($id) => $this->decode($id))->all();
        $options = $server->getCreateArgs($this->userHandle($user), $user->email, $user->name,
            config('passkeys.timeout'), true, true, null, $ids);

        return $this->options($server, $options, $sessionId, 'register', [
            'user_id' => (string) $user->id,
            'password_hash' => hash('sha256', $user->getAuthPassword()),
            'name' => $name,
        ]);
    }

    public function register(User $user, string $sessionId, array $input): void
    {
        $context = $this->challenges->consume($input['challenge_id'], $sessionId, 'register');
        $this->requireValid(($context['user_id'] ?? null) === (string) $user->id
            && hash_equals($context['password_hash'], hash('sha256', $user->getAuthPassword())));
        $clientData = $this->clientData($input['response']['clientDataJSON']);
        try {
            $result = $this->server()->processCreate($clientData,
                $this->decode($input['response']['attestationObject']),
                $this->decode($context['challenge']), true, true);
        } catch (WebAuthnException $exception) {
            $this->invalid();
        }
        $id = $this->decode($input['id']);
        $this->requireValid(strlen($id) <= 1023 && hash_equals($result->credentialId, $id));
        $this->requireValid(!$result->isBackedUp || $result->isBackupEligible);

        $passkey = DB::transaction(function () use ($user, $context, $result, $id) {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->requireValid(hash_equals($context['password_hash'], hash('sha256', $lockedUser->getAuthPassword())));
            $this->checkLimit($lockedUser);
            if (UserPasskey::where('credential_hash', hash('sha256', $id))->exists()) {
                throw ValidationException::withMessages(['passkey' => 'Passkey ini sudah terdaftar. Gunakan passkey lain.']);
            }
            return $lockedUser->passkeys()->create([
                'name' => $context['name'],
                'credential_hash' => hash('sha256', $id),
                'credential_id' => $this->encode($id),
                'public_key' => $result->credentialPublicKey,
                'sign_count' => $result->signatureCounter ?? 0,
                'backup_eligible' => $result->isBackupEligible,
            ]);
        });
        $this->audit('registered', $user, $passkey);
    }

    public function loginOptions(string $sessionId, bool $remember, ?string $redirect): array
    {
        $server = $this->server();
        // Discoverable credentials: no email lookup or account enumeration endpoint.
        $options = $server->getGetArgs([], config('passkeys.timeout'), true, true, true, true, true, true);
        return $this->options($server, $options, $sessionId, 'login', compact('remember', 'redirect'));
    }

    public function authenticate(string $sessionId, array $input): array
    {
        $context = $this->challenges->consume($input['challenge_id'], $sessionId, 'login');
        $clientData = $this->clientData($input['response']['clientDataJSON']);
        $id = $this->decode($input['id']);

        return DB::transaction(function () use ($context, $clientData, $id, $input) {
            $passkey = UserPasskey::where('credential_hash', hash('sha256', $id))->lockForUpdate()->first();
            $this->requireValid($passkey !== null);
            $user = $passkey->user;
            $this->requireValid($user !== null && hash_equals($this->decode($passkey->credential_id), $id));
            $this->requireValid(hash_equals($this->userHandle($user), $this->decode($input['response']['userHandle'])));

            $authData = $this->decode($input['response']['authenticatorData']);
            $this->requireValid(strlen($authData) >= 37);
            $flags = ord($authData[32]);
            $backupEligible = (bool) ($flags & 0x08);
            $this->requireValid($backupEligible === $passkey->backup_eligible
                && (!(bool) ($flags & 0x10) || $backupEligible));
            $server = $this->server();
            try {
                $server->processGet($clientData, $authData, $this->decode($input['response']['signature']),
                    $passkey->public_key, $this->decode($context['challenge']),
                    // Synced passkeys may have independent counters across devices.
                    $backupEligible ? null : $passkey->sign_count, true, true);
            } catch (WebAuthnException $exception) {
                $this->invalid();
            }

            $passkey->update(['sign_count' => max($passkey->sign_count, $server->getSignatureCounter() ?? 0),
                'last_used_at' => now()]);
            return ['user' => $user, 'remember' => $context['remember'], 'redirect' => $context['redirect']];
        });
    }

    public function revoke(User $user, string $id): void
    {
        $passkey = DB::transaction(function () use ($user, $id) {
            $passkey = $user->passkeys()->whereKey($id)->lockForUpdate()->first();
            abort_unless($passkey, 404, 'Passkey tidak ditemukan pada akun Anda.');
            $passkey->delete();
            return $passkey;
        });
        $this->audit('revoked', $user, $passkey);
    }

    public function userHandle(User $user): string
    {
        return hash('sha256', 'hris-passkey:' . $user->getKey(), true);
    }

    public function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public function decode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        $this->requireValid($decoded !== false && $decoded !== '' && $this->encode($decoded) === $value);
        return $decoded;
    }

    private function server(): WebAuthn
    {
        // No biometric data or device attestation certificates are collected.
        return new WebAuthn(config('passkeys.rp_name'), config('passkeys.rp_id'), ['none'], true);
    }

    private function options(WebAuthn $server, object $options, string $sessionId, string $purpose, array $context): array
    {
        $context['challenge'] = $this->encode($server->getChallenge()->getBinaryString());
        return [
            'challenge_id' => $this->challenges->issue($sessionId, $purpose, $context),
            'publicKey' => $options->publicKey,
        ];
    }

    private function clientData(string $encoded): string
    {
        $json = $this->decode($encoded);
        $data = json_decode($json, true);
        $this->requireValid(is_array($data) && ($data['origin'] ?? null) === config('passkeys.origin')
            && ($data['crossOrigin'] ?? false) === false && !isset($data['topOrigin']));
        return $json;
    }

    private function checkLimit(User $user): void
    {
        if ($user->passkeys()->count() >= config('passkeys.max_per_user')) {
            throw ValidationException::withMessages(['passkey' => 'Batas jumlah passkey tercapai. Cabut passkey lama terlebih dahulu.']);
        }
    }

    private function audit(string $event, User $user, UserPasskey $passkey): void
    {
        app(AuditTrailService::class)->record([
            'event' => 'passkey.' . $event, 'module' => 'account_security',
            'actor_id' => (string) $user->id, 'actor_name' => $user->name,
            'auditable_type' => UserPasskey::class, 'auditable_id' => (string) $passkey->id,
            'reference_table' => 'user_passkeys', 'reference_id' => (string) $passkey->id,
        ]);
    }

    private function requireValid(bool $valid): void
    {
        if (!$valid) {
            $this->invalid();
        }
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages([
            'passkey' => 'Passkey tidak dapat diverifikasi. Silakan coba lagi atau masuk dengan password.',
        ]);
    }
}
