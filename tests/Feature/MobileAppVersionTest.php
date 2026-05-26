<?php

namespace Tests\Feature;

use Tests\TestCase;

class MobileAppVersionTest extends TestCase
{
    public function test_it_forces_update_when_version_is_below_minimum(): void
    {
        config([
            'hris.mobile_app.minimum_version_code' => 2,
            'hris.mobile_app.latest_version_code' => 3,
            'hris.mobile_app.latest_version_name' => '1.0.2',
            'hris.mobile_app.download_url' => 'https://example.com/download-app',
        ]);

        $response = $this->getJson('/api/mobile/app-version?version_code=1&platform=android');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'platform' => 'android',
                'force_update' => true,
                'update_available' => true,
                'minimum_version_code' => 2,
                'latest_version_code' => 3,
                'latest_version_name' => '1.0.2',
                'download_url' => 'https://example.com/download-app',
            ]);
    }

    public function test_it_allows_supported_version(): void
    {
        config([
            'hris.mobile_app.minimum_version_code' => 2,
            'hris.mobile_app.latest_version_code' => 2,
        ]);

        $response = $this->getJson('/api/mobile/app-version?version_code=2&platform=android');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'force_update' => false,
                'update_available' => false,
                'minimum_version_code' => 2,
                'latest_version_code' => 2,
            ]);
    }
}
