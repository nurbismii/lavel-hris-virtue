<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserPasskey;
use App\Services\Audit\AuditTrailService;
use App\Services\Auth\PasskeyChallengeStore;
use App\Services\Auth\PasskeyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PasskeyAuthenticationTest extends TestCase
{
    private PasskeyService $service;
    private User $user;
    private $key;
    private string $credentialId;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        config()->set('passkeys.enabled', true);
        config()->set('passkeys.origin', 'https://hris.example.test');
        config()->set('passkeys.rp_id', 'hris.example.test');
        config()->set('cache.default', 'array');

        Schema::create('users', function (Blueprint $table) {
            $table->string('id', 32)->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('terakhir_login')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        (require database_path('migrations/2026_09_13_000001_create_passkey_tables.php'))->up();
        $this->mock(AuditTrailService::class)->shouldReceive('record')->andReturnNull();
        $this->service = app(PasskeyService::class);
        $this->user = User::create(['id' => 'test-user-1', 'name' => 'Test User', 'email' => 'user@example.test',
            'password' => Hash::make('test-password'), 'email_verified_at' => now()]);
        $this->key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1',
            'config' => base_path('tests/Fixtures/passkeys-openssl.cnf')]);
        $this->assertNotFalse($this->key);
        $this->credentialId = random_bytes(32);
    }

    public function test_real_attestation_registration_and_signed_authentication(): void
    {
        $this->register();
        $this->assertDatabaseCount('user_passkeys', 1);
        $options = $this->service->loginOptions('session-one', true, '/dashboard');
        $result = $this->service->authenticate('session-one', $this->assertion($options));
        $this->assertSame($this->user->id, $result['user']->id);
        $this->assertTrue($result['remember']);
        $this->assertSame('/dashboard', $result['redirect']);
        $this->assertNotNull(UserPasskey::first()->last_used_at);
        $this->assertSame(1, UserPasskey::first()->sign_count);
    }

    public function test_assertion_cannot_be_replayed_even_when_device_uses_zero_counter(): void
    {
        $this->register();
        $options = $this->service->loginOptions('session-one', false, null);
        $input = $this->assertion($options, [], 0);
        $this->service->authenticate('session-one', $input);
        $this->expectException(ValidationException::class);
        $this->service->authenticate('session-one', $input);
    }

    public function test_invalid_origin_challenge_handle_signature_flags_and_rp_are_rejected(): void
    {
        $this->register();
        foreach (['origin', 'challenge', 'userHandle', 'signature', 'crossOrigin', 'topOrigin', 'uv', 'rp', 'backup'] as $case) {
            $options = $this->service->loginOptions('session-one', false, null);
            $client = [];
            if ($case === 'origin') $client['origin'] = 'https://sub.hris.example.test';
            if ($case === 'challenge') $client['challenge'] = $this->service->encode(random_bytes(32));
            if ($case === 'crossOrigin') $client['crossOrigin'] = true;
            if ($case === 'topOrigin') $client['topOrigin'] = config('passkeys.origin');
            $input = $this->assertion($options, $client, 1, $case === 'uv' ? 1 : ($case === 'backup' ? 13 : 5),
                $case === 'rp' ? 'other.example.test' : null);
            if ($case === 'userHandle') $input['response']['userHandle'] = $this->service->encode('another-user');
            if ($case === 'signature') $input['response']['signature'] = $this->service->encode(random_bytes(64));
            try {
                $this->service->authenticate('session-one', $input);
                $this->fail('Accepted invalid ' . $case);
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('passkey', $exception->errors());
            }
            $this->assertSame(0, UserPasskey::first()->sign_count);
            $this->assertDatabaseMissing('passkey_challenges', ['id' => $options['challenge_id']]);
        }
    }

    public function test_challenge_is_bound_to_session_purpose_and_expiry(): void
    {
        $store = app(PasskeyChallengeStore::class);
        foreach (['session', 'purpose', 'expiry'] as $case) {
            $id = $store->issue('session-one', 'login', ['sample' => true]);
            if ($case === 'expiry') $this->travel(121)->seconds();
            try {
                $store->consume($id, $case === 'session' ? 'session-two' : 'session-one', $case === 'purpose' ? 'register' : 'login');
                $this->fail('Accepted invalid ' . $case);
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('passkey', $exception->errors());
            } finally { $this->travelBack(); }
        }
    }

    public function test_registration_rejects_changed_password_and_duplicate_credentials(): void
    {
        $options = $this->service->registrationOptions($this->user, 'session-one', 'Personal');
        $this->user->update(['password' => Hash::make('replacement-password')]);
        try {
            $this->service->register($this->user, 'session-one', $this->attestation($options));
            $this->fail('Registered after password change');
        } catch (ValidationException $exception) { $this->assertDatabaseCount('user_passkeys', 0); }
        $this->register();
        $this->expectException(ValidationException::class);
        $this->register();
    }

    public function test_registration_rejects_wrong_id_and_missing_user_verification(): void
    {
        foreach (['id', 'uv', 'origin'] as $case) {
            $options = $this->service->registrationOptions($this->user, 'session-one', 'Personal');
            $input = $this->attestation($options, $case === 'uv' ? 65 : 69,
                $case === 'origin' ? 'https://other.example.test' : null);
            if ($case === 'id') $input['id'] = $this->service->encode('wrong-id');
            try {
                $this->service->register($this->user, 'session-one', $input);
                $this->fail('Accepted invalid registration ' . $case);
            } catch (ValidationException $exception) { $this->assertDatabaseCount('user_passkeys', 0); }
        }
    }

    public function test_synced_passkeys_can_login_with_non_increasing_counters(): void
    {
        $this->register(77);
        foreach ([5, 2, 0] as $counter) {
            $options = $this->service->loginOptions('session-one', false, null);
            $result = $this->service->authenticate('session-one', $this->assertion($options, [], $counter, 13));
            $this->assertSame($this->user->id, $result['user']->id);
        }
    }

    public function test_device_bound_counter_rollback_is_rejected(): void
    {
        $this->register();
        $options = $this->service->loginOptions('session-one', false, null);
        $this->service->authenticate('session-one', $this->assertion($options, [], 2));
        $options = $this->service->loginOptions('session-one', false, null);
        $this->expectException(ValidationException::class);
        $this->service->authenticate('session-one', $this->assertion($options, [], 1));
    }

    public function test_management_requires_authenticated_verified_user_and_correct_password(): void
    {
        $this->getJson('/passkeys')->assertUnauthorized();
        $this->actingAs($this->user)->postJson('/passkeys/register/options', ['name' => 'Personal', 'current_password' => 'wrong'])
            ->assertUnprocessable();
        $this->assertDatabaseCount('passkey_challenges', 0);
        $this->postJson('/passkeys/register/options', ['name' => 'Personal', 'current_password' => 'test-password'])
            ->assertOk()->assertJsonPath('data.publicKey.authenticatorSelection.userVerification', 'required')
            ->assertJsonPath('data.publicKey.authenticatorSelection.residentKey', 'required');
        $this->user->update(['email_verified_at' => null]);
        $this->getJson('/passkeys')->assertForbidden();
    }

    public function test_list_hides_key_material_and_revoke_is_owner_scoped(): void
    {
        $this->register();
        $id = UserPasskey::first()->id;
        $other = User::create(['id' => 'test-user-2', 'name' => 'Other', 'email' => 'other@example.test',
            'password' => Hash::make('other-password'), 'email_verified_at' => now()]);
        $this->actingAs($other)->deleteJson('/passkeys/' . $id, ['current_password' => 'other-password'])->assertNotFound();
        $this->assertDatabaseCount('user_passkeys', 1);
        $response = $this->actingAs($this->user)->getJson('/passkeys')->assertOk();
        $this->assertArrayNotHasKey('public_key', $response->json('data.passkeys.0'));
        $this->assertArrayNotHasKey('credential_id', $response->json('data.passkeys.0'));
        $this->deleteJson('/passkeys/' . $id, ['current_password' => 'wrong'])->assertUnprocessable();
        $this->deleteJson('/passkeys/' . $id, ['current_password' => 'test-password'])->assertOk();
        $options = $this->service->loginOptions('session-one', false, null);
        $this->expectException(ValidationException::class);
        $this->service->authenticate('session-one', $this->assertion($options));
    }

    public function test_disabled_or_misconfigured_feature_fails_closed_and_login_view_still_renders(): void
    {
        config()->set('passkeys.enabled', false);
        $this->postJson('/passkeys/login/options')->assertStatus(503);
        $this->get('/login')->assertOk()->assertDontSee('data-passkey-login', false);
        config()->set('passkeys.enabled', true);
        $this->get('/login')->assertOk()->assertSee('data-passkey-login', false);
        config()->set('passkeys.origin', 'https://other.example.test');
        $this->postJson('/passkeys/login/options')->assertStatus(503);
    }

    public function test_login_options_do_not_enumerate_accounts_and_discard_external_redirects(): void
    {
        $response = $this->postJson('/passkeys/login/options', ['redirect' => 'https://evil.example/path', 'remember' => true])->assertOk();
        $this->assertArrayNotHasKey('allowCredentials', $response->json('data.publicKey'));
        $payload = json_decode(DB::table('passkey_challenges')->first()->payload, true);
        $this->assertNull($payload['redirect']);
        $this->assertTrue($payload['remember']);
    }

    public function test_http_login_regenerates_session_and_updates_last_login(): void
    {
        $this->register();
        // No business role tables are required to exercise the existing home-route helper.
        Schema::create('roles', function (Blueprint $table) { $table->id(); $table->string('name'); });
        $options = $this->postJson('/passkeys/login/options', ['remember' => false, 'redirect' => '/pengaturan-akun/update'])
            ->assertOk()->json('data');
        $sessionId = session()->getId();
        $this->withCookie(config('session.cookie'), $sessionId)->withCredentials()
            ->postJson('/passkeys/login', $this->assertion($options))->assertOk()
            ->assertJsonPath('data.redirect', '/pengaturan-akun/update');
        $this->assertAuthenticatedAs($this->user);
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotNull($this->user->fresh()->terakhir_login);
    }

    public function test_rate_limit_and_passkey_limit_are_enforced(): void
    {
        $this->withSession([])->withCookie(config('session.cookie'), session()->getId())->withCredentials();
        for ($i = 0; $i < 20; $i++) $this->postJson('/passkeys/login/options')->assertOk();
        $this->postJson('/passkeys/login/options')->assertStatus(429);
        $this->register();
        config()->set('passkeys.max_per_user', 1);
        $this->expectException(ValidationException::class);
        $this->service->registrationOptions($this->user, 'session-one', 'Another');
    }

    public function test_separate_sessions_on_same_office_ip_have_separate_limits(): void
    {
        $this->withSession([])->withCookie(config('session.cookie'), session()->getId())->withCredentials();
        for ($i = 0; $i < 20; $i++) $this->postJson('/passkeys/login/options')->assertOk();
        $this->postJson('/passkeys/login/options')->assertStatus(429);
        $this->withCookie(config('session.cookie'), str_repeat('b', 40))
            ->postJson('/passkeys/login/options')->assertOk();
    }

    public function test_registration_http_flow_uses_password_confirmation_and_single_use_challenge(): void
    {
        $this->actingAs($this->user)->withSession([])
            ->withCookie(config('session.cookie'), session()->getId())->withCredentials();
        $options = $this->postJson('/passkeys/register/options', ['name' => 'Personal', 'current_password' => 'test-password'])
            ->assertOk()->json('data');
        $input = $this->attestation($options);
        $this->postJson('/passkeys/register', $input)->assertOk()->assertJsonPath('success', true);
        $this->postJson('/passkeys/register', $input)->assertUnprocessable();
        $this->assertDatabaseCount('user_passkeys', 1);
    }

    private function register(int $flags = 69): void
    {
        $options = $this->service->registrationOptions($this->user, 'session-one', 'Personal device');
        $this->service->register($this->user, 'session-one', $this->attestation($options, $flags));
    }

    private function attestation(array $options, int $flags = 69, ?string $origin = null): array
    {
        $key = openssl_pkey_get_details($this->key)['ec'];
        // Minimal CBOR EC2/ES256 COSE public key and anonymized attestation.
        $cose = "\xa5\x01\x02\x03\x26\x20\x01\x21" . $this->bytes($key['x']) . "\x22" . $this->bytes($key['y']);
        $authData = hash('sha256', config('passkeys.rp_id'), true) . chr($flags) . pack('N', 0)
            . str_repeat("\x00", 16) . pack('n', strlen($this->credentialId)) . $this->credentialId . $cose;
        $attestation = "\xa3\x63fmt\x64none\x67attStmt\xa0\x68authData" . $this->bytes($authData);
        $publicKey = json_decode(json_encode($options['publicKey']), true);
        return ['id' => $this->service->encode($this->credentialId), 'type' => 'public-key',
            'challenge_id' => $options['challenge_id'], 'response' => [
                'clientDataJSON' => $this->service->encode(json_encode(['type' => 'webauthn.create',
                    'challenge' => $publicKey['challenge'], 'origin' => $origin ?? config('passkeys.origin')])),
                'attestationObject' => $this->service->encode($attestation),
            ]];
    }

    private function assertion(array $options, array $clientOverrides = [], int $counter = 1, int $flags = 5, ?string $rp = null): array
    {
        $publicKey = json_decode(json_encode($options['publicKey']), true);
        $json = json_encode(array_merge(['type' => 'webauthn.get', 'challenge' => $publicKey['challenge'],
            'origin' => config('passkeys.origin')], $clientOverrides));
        $authData = hash('sha256', $rp ?? config('passkeys.rp_id'), true) . chr($flags) . pack('N', $counter);
        openssl_sign($authData . hash('sha256', $json, true), $signature, $this->key, OPENSSL_ALGO_SHA256);
        return ['id' => $this->service->encode($this->credentialId), 'type' => 'public-key',
            'challenge_id' => $options['challenge_id'], 'response' => [
                'clientDataJSON' => $this->service->encode($json), 'authenticatorData' => $this->service->encode($authData),
                'signature' => $this->service->encode($signature), 'userHandle' => $this->service->encode($this->service->userHandle($this->user)),
            ]];
    }

    private function bytes(string $binary): string
    {
        return (strlen($binary) < 256 ? "\x58" . chr(strlen($binary)) : "\x59" . pack('n', strlen($binary))) . $binary;
    }
}
