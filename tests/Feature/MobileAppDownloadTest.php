<?php

namespace Tests\Feature;

use App\Support\MobileApp;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileAppDownloadTest extends TestCase
{
    private const SITE = 'https://hris.example.test';
    private $publicDirectory;
    private $originalPublicDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalPublicDirectory = public_path();
        $this->publicDirectory = sys_get_temp_dir() . '/vpeople-download-' . Str::uuid();
        mkdir($this->publicDirectory . '/downloads', 0777, true);
        $this->app->usePublicPath($this->publicDirectory);
        config([
            'hris.mobile_app.android_download_url' => null,
            'hris.mobile_app.download_url' => null,
            'hris.mobile_app.ios_download_url' => null,
            'hris.mobile_app.ios_required' => false,
        ]);
    }

    protected function tearDown(): void
    {
        $this->app->usePublicPath($this->originalPublicDirectory);
        $apk = $this->publicDirectory . '/downloads/vpeople-android.apk';
        if (is_file($apk)) {
            unlink($apk);
        }
        rmdir($this->publicDirectory . '/downloads');
        rmdir($this->publicDirectory);
        parent::tearDown();
    }

    private function provideApk(): void
    {
        file_put_contents($this->publicDirectory . '/downloads/vpeople-android.apk', 'synthetic APK fixture');
    }

    public function test_legacy_landing_page_url_falls_back_to_apk_in_both_page_and_version_api(): void
    {
        $this->provideApk();
        foreach ([self::SITE . '/download-app', self::SITE . '/download-app/?platform=android#download',
            'https://HRIS.EXAMPLE.TEST:443/download-app'] as $url) {
            config(['hris.mobile_app.download_url' => $url]);
            $this->withHeader('User-Agent', 'Android')->get(self::SITE . '/download-app')->assertOk()
                ->assertViewHas('androidUrl', self::SITE . '/downloads/vpeople-android.apk')
                ->assertSee('href="' . self::SITE . '/downloads/vpeople-android.apk"', false);
            $this->getJson(self::SITE . '/api/mobile/app-version?platform=android&version_code=10')->assertOk()
                ->assertJsonPath('download_url', self::SITE . '/downloads/vpeople-android.apk');
        }
    }

    public function test_invalid_platform_self_link_can_use_a_valid_legacy_link(): void
    {
        config([
            'hris.mobile_app.android_download_url' => self::SITE . '/download-app',
            'hris.mobile_app.download_url' => 'https://downloads.example.test/releases/vpeople.apk',
        ]);
        $this->get(self::SITE . '/download-app')->assertOk()
            ->assertViewHas('androidUrl', 'https://downloads.example.test/releases/vpeople.apk');
    }

    public function test_valid_platform_apk_and_store_links_keep_priority(): void
    {
        foreach (['https://downloads.example.test/vpeople.apk?release=10',
            'https://play.google.com/store/apps/details?id=com.vdni.vpeople'] as $url) {
            config([
                'hris.mobile_app.android_download_url' => $url,
                'hris.mobile_app.download_url' => 'https://legacy.example.test/vpeople.apk',
            ]);
            $this->get(self::SITE . '/download-app')->assertOk()->assertViewHas('androidUrl', $url);
        }
    }

    public function test_unsafe_urls_are_skipped_before_using_local_apk(): void
    {
        $this->provideApk();
        foreach (['javascript:alert(1)', 'http://downloads.example.test/vpeople.apk', 'not a URL'] as $url) {
            config(['hris.mobile_app.android_download_url' => $url, 'hris.mobile_app.download_url' => self::SITE . '/download-app']);
            $this->get(self::SITE . '/download-app')->assertOk()
                ->assertViewHas('androidUrl', self::SITE . '/downloads/vpeople-android.apk');
        }
    }

    public function test_missing_apk_shows_unavailable_instead_of_a_circular_download_button(): void
    {
        foreach ([null, self::SITE . '/download-app'] as $url) {
            config(['hris.mobile_app.android_download_url' => $url, 'hris.mobile_app.download_url' => $url]);
            $this->get(self::SITE . '/download-app')->assertOk()->assertViewHas('androidUrl', null)
                ->assertSee('Aplikasi Android belum tersedia')->assertDontSee('Unduh aplikasi Android');
        }
    }

    public function test_ios_never_falls_back_to_the_android_apk(): void
    {
        $this->provideApk();
        config(['hris.mobile_app.download_url' => 'https://downloads.example.test/vpeople.apk']);
        $this->get(self::SITE . '/download-app')->assertOk()->assertViewHas('iosUrl', null);
        config(['hris.mobile_app.ios_download_url' => self::SITE . '/download-app']);
        $this->get(self::SITE . '/download-app')->assertOk()->assertViewHas('iosUrl', null);
        config(['hris.mobile_app.ios_download_url' => 'https://apps.apple.com/app/id123456789']);
        $this->assertSame('https://apps.apple.com/app/id123456789', MobileApp::downloadUrl('ios'));
    }
}
