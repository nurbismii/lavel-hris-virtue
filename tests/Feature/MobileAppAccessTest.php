<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MobileAppAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['hris.mobile_app.required' => true, 'hris.mobile_app.ios_required' => false, 'cache.default' => 'array']);
    }

    public function test_mobile_browsers_cannot_open_login_or_protected_pages_even_locally(): void
    {
        $this->app->detectEnvironment(function () { return 'local'; });
        foreach (['Android', 'Windows Phone', 'Opera Mini'] as $agent) {
            foreach (['/', '/login'] as $path) {
                $this->withHeader('User-Agent', $agent)->get($path)->assertRedirect('/download-app');
            }
        }
    }

    public function test_login_and_registration_posts_are_blocked_before_credentials_are_processed(): void
    {
        Route::middleware(['web', 'android.redirect'])->post('/mobile-test/register', [\App\Http\Controllers\Auth\RegisterController::class, 'register']);
        foreach (['/login', '/mobile-test/register'] as $path) {
            $this->withHeader('User-Agent', 'Android')->post($path, [])->assertRedirect('/download-app');
        }
        $this->assertGuest();
    }

    public function test_json_login_passkey_and_existing_session_endpoints_return_clear_errors(): void
    {
        foreach (['/login', '/passkeys/login/options', '/passkeys/login'] as $path) {
            $this->withHeader('User-Agent', 'Android')->postJson($path, [])
                ->assertForbidden()->assertJson([
                    'success' => false,
                    'code' => 'mobile_app_required',
                    'data' => ['download_url' => url('/download-app')],
                ]);
        }
        $this->assertGuest();
    }

    public function test_existing_mobile_sessions_cannot_access_protected_routes(): void
    {
        $this->actingAs(new User(['id' => 'mobile-test-user', 'email_verified_at' => now()]));
        $this->withHeader('User-Agent', 'Android')->get('/dashboard')->assertRedirect('/download-app');
        Route::middleware(['web', 'android.redirect', 'auth'])->get('/mobile-test/session', function () {
            return response()->json(['success' => true]);
        });
        $this->withHeader('User-Agent', 'iPhone')->getJson('/mobile-test/session')->assertOk();
        $this->assertAuthenticated();
    }

    public function test_desktop_and_native_apps_keep_login_access(): void
    {
        foreach ([
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
            'Mozilla/5.0 (X11; Linux x86_64)',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)',
            'Mozilla/5.0 (iPad; CPU OS 18_0 like Mac OS X)',
            'iPod',
            'Android VPEOPLE_APP',
            'Android VPEOPLE_APP/1.0.5',
            'iPhone VPEOPLE_APP/1.0.0',
        ] as $agent) {
            $this->withHeader('User-Agent', $agent)->get('/login')->assertOk()->assertViewIs('auth.login');
        }
    }

    public function test_client_hint_is_honoured_but_query_string_is_not_an_app_identity(): void
    {
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0', 'Sec-CH-UA-Mobile' => '?1'])
            ->get('/login?app=V-PEOPLE')->assertRedirect('/download-app');
        $this->withHeaders(['User-Agent' => 'Android FAKE_VPEOPLE_APP', 'Sec-CH-UA-Mobile' => '?0'])
            ->get('/login')->assertRedirect('/download-app');
    }

    public function test_download_and_password_recovery_remain_accessible(): void
    {
        $this->withHeader('User-Agent', 'iPhone')->get('/download-app')->assertOk();
        $this->withHeader('User-Agent', 'Android')->get('/forgot-password')->assertOk();
    }

    public function test_policy_can_be_disabled_for_a_controlled_rollout(): void
    {
        config(['hris.mobile_app.required' => false]);
        $this->withHeader('User-Agent', 'iPhone')->get('/login')->assertOk()->assertDontSee('mobile-app-guard.js');
    }

    public function test_ios_password_and_passkey_requests_reach_the_normal_validation(): void
    {
        foreach (['iPhone', 'iPad'] as $agent) {
            $this->withHeader('User-Agent', $agent)->postJson('/login', [])->assertUnprocessable()
                ->assertJsonValidationErrors(['email', 'password']);
        }
        config(['passkeys.enabled' => true]);
        $this->withHeader('User-Agent', 'iPhone')->postJson('/passkeys/login', [])->assertUnprocessable();
    }

    public function test_ios_can_be_required_again_after_the_native_release_is_ready(): void
    {
        config(['hris.mobile_app.ios_required' => true]);
        $this->withHeader('User-Agent', 'iPhone')->get('/login')->assertRedirect('/download-app');
    }

    public function test_download_links_are_platform_specific_and_missing_ios_is_honest(): void
    {
        config([
            'hris.mobile_app.ios_required' => true,
            'hris.mobile_app.android_download_url' => 'https://downloads.example.test/vpeople.apk',
            'hris.mobile_app.ios_download_url' => null,
        ]);
        $this->get('/download-app')->assertOk()
            ->assertSee('https://downloads.example.test/vpeople.apk')
            ->assertSee('Versi iOS belum tersedia');
        config(['hris.mobile_app.ios_download_url' => 'https://apps.apple.com/app/id123456789']);
        $this->get('/download-app')->assertSee('https://apps.apple.com/app/id123456789')
            ->assertDontSee('Versi iOS belum tersedia');
        config(['hris.mobile_app.ios_download_url' => 'javascript:alert(1)']);
        $this->get('/download-app')->assertDontSee('javascript:alert(1)');
        config(['hris.mobile_app.ios_required' => false]);
        $this->withHeader('User-Agent', 'iPhone')->get('/download-app')->assertSee('Masuk melalui browser')
            ->assertDontSee('Versi iOS belum tersedia');
    }
}
