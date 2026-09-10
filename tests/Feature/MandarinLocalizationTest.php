<?php

namespace Tests\Feature;

use App\Services\Localization\LocaleService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class MandarinLocalizationTest extends TestCase
{
    public function test_mandarin_catalog_covers_indonesian_keys_and_preserves_parameters()
    {
        foreach (glob(resource_path('lang/id/*.php')) as $source) {
            $target = resource_path('lang/zh_CN/' . basename($source));
            $this->assertFileExists($target);
            $indonesian = Arr::dot(require $source);
            $mandarin = Arr::dot(require $target);

            foreach ($indonesian as $key => $value) {
                $this->assertArrayHasKey($key, $mandarin, basename($source) . ':' . $key);
                if (!is_string($value)) {
                    continue;
                }
                $this->assertNotSame('', trim($mandarin[$key]), $key);
                preg_match_all('/:[a-zA-Z_][a-zA-Z0-9_]*/', $value, $expected);
                preg_match_all('/:[a-zA-Z_][a-zA-Z0-9_]*/', $mandarin[$key], $actual);
                sort($expected[0]);
                sort($actual[0]);
                $this->assertSame($expected[0], $actual[0], basename($source) . ':' . $key);
            }
        }
    }

    public function test_password_recovery_renders_in_both_languages()
    {
        $this->startSession();
        view()->share('errors', new ViewErrorBag());

        foreach (['id' => 'Kirim Link Reset', 'zh_CN' => '发送重置链接'] as $locale => $label) {
            app(LocaleService::class)->apply($locale);
            $html = view('auth.forgot-password')->render();
            $this->assertStringContainsString($label, $html);
            $this->assertStringContainsString('name="email"', $html);
            $this->assertStringContainsString('name="_token"', $html);
        }
    }

    public function test_roster_option_labels_change_without_changing_submitted_values()
    {
        $this->startSession();
        $source = file_get_contents(resource_path('views/user/roster/create.blade.php'));
        preg_match_all('/<option value="(?:OFF|BEKERJA)".*?<\/option>/', $source, $matches);
        $this->assertCount(2, $matches[0]);

        foreach (['id' => ['OFF', 'BEKERJA'], 'zh_CN' => ['休息', '工作']] as $locale => $labels) {
            app(LocaleService::class)->apply($locale);
            $html = Blade::render(implode('', $matches[0]), ['index' => 0]);
            $this->assertStringContainsString('value="OFF"', $html);
            $this->assertStringContainsString('value="BEKERJA"', $html);
            foreach ($labels as $label) {
                $this->assertStringContainsString('>' . $label . '</option>', $html);
            }
        }
    }

    public function test_dashboard_feedback_renders_as_javascript_in_both_languages()
    {
        foreach (['id', 'zh_CN'] as $locale) {
            app(LocaleService::class)->apply($locale);
            $script = view('admin.cv-maker-dashboard.scripts')->render();
            $this->assertStringNotContainsString('@json', $script);
            $this->assertStringNotContainsString('@js(', $script);
            $this->assertStringContainsString('renderCvDashboard', $script);
            $this->assertStringContainsString($locale === 'zh_CN' ? 'zh-CN' : 'id-ID', $script);
        }
    }
}
