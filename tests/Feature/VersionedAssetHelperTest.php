<?php

namespace Tests\Feature;

use Tests\TestCase;

class VersionedAssetHelperTest extends TestCase
{
    public function test_it_uses_configured_asset_version_when_available()
    {
        config(['app.asset_version' => 'release-20260509']);

        $path = 'assets/css/app-layout.css';

        $this->assertSame(
            asset($path) . '?v=release-20260509',
            versioned_asset($path)
        );
    }

    public function test_it_falls_back_to_filemtime_when_asset_version_is_empty()
    {
        config(['app.asset_version' => null]);

        $path = 'assets/css/app-layout.css';

        $this->assertFileExists(public_path($path));
        $this->assertSame(
            asset($path) . '?v=' . filemtime(public_path($path)),
            versioned_asset('/' . $path)
        );
    }

    public function test_it_does_not_add_version_for_missing_assets_without_configured_version()
    {
        config(['app.asset_version' => null]);

        $path = 'assets/css/missing-asset-for-version-test.css';

        $this->assertFileDoesNotExist(public_path($path));
        $this->assertSame(asset($path), versioned_asset($path));
    }
}
