<?php

namespace Tests\Feature;

use App\Support\EmailUrl;
use Tests\TestCase;

class EmailUrlTest extends TestCase
{
    public function test_login_url_uses_public_email_base_url_with_internal_redirect(): void
    {
        config([
            'app.url' => 'http://localhost',
            'hris.email.base_url' => 'https://hris.example.com',
        ]);

        $url = EmailUrl::login('/kontrak-elektronik/15?tab=sign');
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);

        $this->assertSame('https', $parts['scheme']);
        $this->assertSame('hris.example.com', $parts['host']);
        $this->assertSame('/login', $parts['path']);
        $this->assertSame('/kontrak-elektronik/15?tab=sign', $query['redirect']);
    }

    public function test_login_url_drops_external_redirect_targets(): void
    {
        config([
            'app.url' => 'http://localhost',
            'hris.email.base_url' => 'https://hris.example.com',
        ]);

        $url = EmailUrl::login('https://example.net/phishing');
        $parts = parse_url($url);

        $this->assertSame('hris.example.com', $parts['host']);
        $this->assertSame('/login', $parts['path']);
        $this->assertArrayNotHasKey('query', $parts);
    }

    public function test_localhost_target_is_normalized_to_internal_redirect_path(): void
    {
        config([
            'app.url' => 'http://localhost',
            'hris.email.base_url' => 'https://hris.example.com',
        ]);

        $url = EmailUrl::login('http://localhost/email/verify/1/hash?expires=1&signature=abc');
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);

        $this->assertSame('https://hris.example.com', $parts['scheme'] . '://' . $parts['host']);
        $this->assertSame('/email/verify/1/hash?expires=1&signature=abc', $query['redirect']);
    }

    public function test_login_page_keeps_safe_redirect_for_submit(): void
    {
        $response = $this->get('/login?redirect=' . urlencode('/kotak-masuk'));

        $response->assertOk();
        $response->assertSee('name="redirect"', false);
        $response->assertSee('value="/kotak-masuk"', false);
    }
}
