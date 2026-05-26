<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileAppVersionController extends Controller
{
    public function check(Request $request): JsonResponse
    {
        $currentVersionCode = max((int) $request->query('version_code', 0), 0);
        $platform = strtolower((string) $request->query('platform', 'android'));

        $minimumVersionCode = (int) config('hris.mobile_app.minimum_version_code', 1);
        $latestVersionCode = (int) config('hris.mobile_app.latest_version_code', $minimumVersionCode);
        $latestVersionName = (string) config('hris.mobile_app.latest_version_name', '1.0.0');
        $downloadUrl = config('hris.mobile_app.download_url') ?: url('/download-app');

        $forceUpdate = $currentVersionCode < $minimumVersionCode;
        $updateAvailable = $currentVersionCode < $latestVersionCode;

        return response()->json([
            'success' => true,
            'platform' => $platform,
            'force_update' => $forceUpdate,
            'update_available' => $updateAvailable,
            'minimum_version_code' => $minimumVersionCode,
            'latest_version_code' => $latestVersionCode,
            'latest_version_name' => $latestVersionName,
            'download_url' => $downloadUrl,
            'message' => $forceUpdate
                ? config('hris.mobile_app.force_update_message')
                : config('hris.mobile_app.optional_update_message'),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
