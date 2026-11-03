<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireNativeGpsProof;
use App\Models\User;
use App\Services\Presensi\AndroidGpsAttestation;
use App\Services\Presensi\NativeGpsProof;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class NativeGpsProofTest extends TestCase
{
    private $user;
    private $gps;
    private $key;
    private $caKey;
    private $root;
    private $configPath;
    private $rootPath;
    private $attendanceId;
    private const STATUS = 'https://android.googleapis.com/attestation/status';

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        $this->attendanceId = (string) Str::uuid();
        $this->user = Mockery::mock(User::class)->makePartial();
        $this->user->forceFill(['id' => 'gps-test', 'email_verified_at' => now()]);
        $this->user->setRelation('role', null);
        $this->user->shouldReceive('hasAnyRole')->andReturn(true);
        $this->user->shouldReceive('hasMenuAccess')->andReturn(true);
        $this->actingAs($this->user);
        $this->gps = app(NativeGpsProof::class);
        $this->configPath = tempnam(sys_get_temp_dir(), 'gps-test-');
        $this->rootPath = tempnam(sys_get_temp_dir(), 'gps-test-');
        file_put_contents($this->configPath, "[req]\ndistinguished_name=dn\n[dn]\nCN=Test\n[ca]\nbasicConstraints=critical,CA:TRUE\nkeyUsage=critical,keyCertSign,cRLSign\n");
        $options = ['config' => $this->configPath, 'digest_alg' => 'sha256', 'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'];
        $this->key = openssl_pkey_new($options);
        $this->caKey = openssl_pkey_new($options);
        $csr = openssl_csr_new(['commonName' => 'Test root'], $this->caKey, $options);
        $this->root = openssl_csr_sign($csr, null, $this->caKey, 2, $options + ['x509_extensions' => 'ca'], 1);
        openssl_x509_export_to_file($this->root, $this->rootPath);
        config(['hris.mobile_app.gps_root_file' => $this->rootPath]);
        Http::preventStrayRequests();
        Http::fake([self::STATUS => Http::response(['entries' => (object) []], 200, ['Cache-Control' => 'max-age=3600'])]);
    }

    protected function tearDown(): void
    {
        foreach ([$this->configPath, $this->rootPath] as $path) {
            if ($path && is_file($path)) unlink($path);
        }
        parent::tearDown();
    }

    public function test_both_real_endpoints_refuse_a_client_supplied_mock_flag_or_unsigned_fix(): void
    {
        foreach (['Windows NT 10.0', 'iPhone', 'Android VPEOPLE_APP/1.0.7'] as $agent) {
            foreach (['/api/gps-log', '/absen/masuk'] as $url) {
                $this->withHeader('User-Agent', $agent)->postJson($url, ['mocked' => false, 'lat' => -4, 'long' => 122])
                    ->assertUnprocessable()->assertJson(['success' => false])->assertJsonValidationErrors('gps_proof');
            }
        }
        $this->withHeader('User-Agent', 'Android VPEOPLE_APP/1.0.6')->postJson('/presensi/gps-challenge', [
            'purpose' => 'log', 'attendance_challenge_id' => $this->attendanceId,
        ])->assertForbidden();
    }

    public function test_only_signed_coordinates_reach_attendance_and_a_nonce_cannot_be_replayed(): void
    {
        $req = $this->request();
        $challenge = $this->gps->issue($req, 'masuk', $this->attendanceId);
        $this->assertSame('new', $challenge['key']);
        $proof = $this->proof($challenge['nonce']);
        $req->merge(['gps_proof' => json_encode($proof), 'lat_user' => 9, 'long_user' => 10]);
        $req->setRouteResolver(function () {
            $route = new \Illuminate\Routing\Route('POST', 'absen/{type}', function () {});
            return $route->bind(Request::create('/absen/masuk', 'POST'));
        });
        $fix = (new RequireNativeGpsProof())->handle($req, function ($request) { return $request->only('lat_user', 'long_user', 'accuracy', 'speed'); });
        $this->assertEquals(['lat_user' => -3.99, 'long_user' => 122.5, 'accuracy' => 4.5, 'speed' => 0], $fix);
        $this->rejects(function () use ($req) { $this->verify($req, 'masuk'); });

        $fresh = $this->gps->issue($req, 'log', $this->attendanceId);
        $this->assertSame($proof['key'], $fresh['key']);
        $proof = $this->signedFix($fresh['nonce'], $proof['key']);
        $req->merge(['gps_proof' => $proof]);
        $this->assertEquals(-3.99, $this->verify($req, 'log')['lat']);
    }

    public function test_proof_is_bound_to_session_user_action_challenge_and_time(): void
    {
        $req = $this->request();
        $nonce = $this->gps->issue($req, 'log', $this->attendanceId)['nonce'];
        $req->merge(['gps_proof' => $this->proof($nonce)]);
        $this->rejects(function () use ($req) { $this->verify($req, 'pulang'); });
        $req->merge(['attendance_challenge_id' => (string) Str::uuid()]);
        $this->rejects(function () use ($req) { $this->verify($req, 'log'); });
        $req->merge(['attendance_challenge_id' => $this->attendanceId]);
        $this->user->id = 'other-user';
        $this->rejects(function () use ($req) { $this->verify($req, 'log'); });
        $this->user->id = 'gps-test';
        $id = $req->session()->getId();
        $req->session()->regenerate();
        $this->rejects(function () use ($req) { $this->verify($req, 'log'); });
        $req->session()->setId($id);
        $this->travel(46)->seconds();
        $this->rejects(function () use ($req) { $this->verify($req, 'log'); });
    }

    public function test_changed_coordinates_wrong_app_unlocked_boot_software_key_and_wrong_attestation_challenge_are_rejected(): void
    {
        $req = $this->request();
        foreach (['tamper', 'signer', 'boot', 'software', 'nonce', 'purpose', 'untrusted', 'revoked', 'bad-certificate'] as $case) {
            $nonce = $this->gps->issue($req, 'log', $this->attendanceId)['nonce'];
            $proof = $this->proof($nonce, $case);
            if ($case === 'tamper') $proof['data'] = str_replace('-3990000', '-3990123', $proof['data']);
            if ($case === 'untrusted') config(['hris.mobile_app.gps_root_file' => resource_path('security/android-attestation-roots.pem')]);
            if ($case === 'revoked') Cache::put('gps:android:revocations:v1', ['2' => true], 100);
            if ($case === 'bad-certificate') $proof['chain'][0] = base64_encode('invalid');
            $req->merge(['gps_proof' => $proof]);
            $this->rejects(function () use ($req) { $this->verify($req, 'log'); }, $case);
            config(['hris.mobile_app.gps_root_file' => $this->rootPath]);
        }
    }

    private function verify(Request $req, string $purpose): array
    {
        return $this->gps->verifiedFix($req, $purpose, app(AndroidGpsAttestation::class));
    }

    public function test_revocation_status_is_cached_with_cdn_age_and_outages_fail_closed(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        Http::fake([self::STATUS => Http::sequence()
            ->push(['entries' => (object) []], 200, ['Cache-Control' => 'max-age=86400', 'Age' => '69342'])
            ->push([], 503)]);
        $attestation = app(AndroidGpsAttestation::class);
        $attestation->checkRevocation(['2']);
        $attestation->checkRevocation(['2']);
        Http::assertSentCount(1);
        Cache::forget('gps:android:revocations:v1');
        $this->rejects(function () use ($attestation) { $attestation->checkRevocation(['2']); });
        $this->rejects(function () use ($attestation) { $attestation->checkRevocation(['2']); });
    }

    public function test_a_concurrent_consumer_cannot_accept_the_same_challenge(): void
    {
        $req = $this->request();
        $nonce = $this->gps->issue($req, 'log', $this->attendanceId)['nonce'];
        $req->merge(['gps_proof' => $this->proof($nonce)]);
        $binding = hash('sha256', $this->user->id . '|' . $req->session()->getId());
        $lock = Cache::lock('gps:consume:' . $binding . ':log', 5);
        $this->assertTrue($lock->get());
        try {
            $this->rejects(function () use ($req) { $this->verify($req, 'log'); });
        } finally {
            $lock->release();
        }
        $this->assertEquals(-3.99, $this->verify($req, 'log')['lat']);
    }

    private function rejects(callable $call, string $case = ''): void
    {
        try { $call(); $this->fail('Accepted invalid GPS proof ' . $case); }
        catch (ValidationException $e) { $this->assertArrayHasKey('gps_proof', $e->errors(), $case); }
    }

    private function request(): Request
    {
        $req = Request::create('/absen/masuk', 'POST', ['attendance_challenge_id' => $this->attendanceId]);
        $req->setUserResolver(function () { return $this->user; });
        $req->setLaravelSession(app('session.store'));
        return $req;
    }

    private function signedFix(string $nonce, string $id): array
    {
        $data = "v1|$nonce|-3990000|122500000|450|0";
        openssl_sign($data, $signature, $this->key, OPENSSL_ALGO_SHA256);
        return ['key' => $id, 'data' => $data, 'signature' => base64_encode($signature)];
    }

    // A synthetic hardware description, signed by a CA trusted only by this test.
    private function proof(string $nonce, string $case = ''): array
    {
        $tlv = function ($tag, $value) {
            $length = strlen($value);
            return hex2bin($tag) . ($length < 128 ? chr($length) : chr(0x81) . chr($length)) . $value;
        };
        $app = $tlv('30', $tlv('31', $tlv('30', $tlv('04', 'com.vdni.vpeople') . $tlv('02', chr(7))))
            . $tlv('31', $tlv('04', hex2bin($case === 'signer' ? str_repeat('11', 32) : config('hris.mobile_app.gps_signer_sha256')))));
        $boot = $tlv('30', $tlv('04', str_repeat('b', 32)) . $tlv('01', $case === 'boot' ? chr(0) : chr(255)) . $tlv('0a', chr(0)));
        $desc = $tlv('30', $tlv('02', chr(4)) . $tlv('0a', chr($case === 'software' ? 0 : 1)) . $tlv('02', chr(41)) . $tlv('0a', chr(1))
            . $tlv('04', hex2bin($case === 'nonce' ? str_repeat('12', 32) : $nonce)) . $tlv('04', '')
            . $tlv('30', $tlv('bf8545', $tlv('04', $app))) . $tlv('30', $tlv('a1', $tlv('31', $tlv('02', chr($case === 'purpose' ? 3 : 2))))
                . $tlv('bf853e', $tlv('02', chr(0))) . $tlv('bf8540', $boot)));
        $section = 'gps' . bin2hex(random_bytes(4));
        file_put_contents($this->configPath, "\n[$section]\nbasicConstraints=critical,CA:FALSE\nkeyUsage=critical,digitalSignature\n1.3.6.1.4.1.11129.2.1.17=DER:" . bin2hex($desc) . "\n", FILE_APPEND);
        $options = ['config' => $this->configPath, 'digest_alg' => 'sha256'];
        $csr = openssl_csr_new(['commonName' => 'Test device'], $this->key, $options);
        $leaf = openssl_csr_sign($csr, $this->root, $this->caKey, 1, $options + ['x509_extensions' => $section], 2);
        $proof = $this->signedFix($nonce, openssl_x509_fingerprint($leaf, 'sha256'));
        foreach ([$leaf, $this->root] as $cert) {
            openssl_x509_export($cert, $pem);
            $proof['chain'][] = preg_replace('/-----[^\n]+-----|\s/', '', $pem);
        }
        return $proof;
    }
}
