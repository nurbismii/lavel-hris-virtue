<?php

namespace Tests\Unit;

use App\Services\ElectronicContracts\ContractHtmlSanitizer;
use Tests\TestCase;

class ContractHtmlSanitizerTest extends TestCase
{
    public function test_protocol_relative_urls_are_removed(): void
    {
        $html = '<a href="//evil.example/phish">Link</a><img src="//evil.example/pixel.png"><img src="/safe/logo.png">';

        $clean = app(ContractHtmlSanitizer::class)->clean($html);

        $this->assertStringNotContainsString('//evil.example', $clean);
        $this->assertStringContainsString('/safe/logo.png', $clean);
    }
}
