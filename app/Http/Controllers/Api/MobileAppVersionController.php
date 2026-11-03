<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\MobileApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MobileAppVersionController extends Controller
{
    public function check(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'version_code' => 'sometimes|integer|min:0|max:2147483647',
            'platform' => 'sometimes|in:android,ios',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Platform atau versi aplikasi tidak valid.',
                'errors' => $validator->errors(),
            ], 422)->header('Cache-Control', 'no-store');
        }
        $currentVersionCode = (int) $request->query('version_code', 0);
        $platform = (string) $request->query('platform', 'android');
        $prefix = $platform === 'ios' ? 'ios_' : '';

        $minimumVersionCode = (int) config('hris.mobile_app.' . $prefix . 'minimum_version_code', 1);
        $latestVersionCode = max($minimumVersionCode, (int) config('hris.mobile_app.' . $prefix . 'latest_version_code', $minimumVersionCode));
        $latestVersionName = (string) config('hris.mobile_app.' . $prefix . 'latest_version_name', '1.0.0');
        $downloadUrl = MobileApp::downloadUrl($platform) ?: route('mobile.download');

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
