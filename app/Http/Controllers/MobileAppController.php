<?php

namespace App\Http\Controllers;

use App\Support\MobileApp;
use App\Services\Presensi\NativeGpsProof;
use Illuminate\Http\Request;

class MobileAppController extends Controller
{
    public function gpsChallenge(Request $request, NativeGpsProof $gps)
    {
        if (!NativeGpsProof::supported($request)) {
            return response()->json(['success' => false, 'message' => 'Gunakan V-People Android 1.0.7 atau lebih baru untuk presensi GPS.'], 403);
        }
        $data = $request->validate(['purpose' => 'required|in:log,masuk,istirahat,kembali,pulang', 'attendance_challenge_id' => 'required|uuid']);
        return response()->json(['success' => true, 'data' => $gps->issue($request, $data['purpose'], $data['attendance_challenge_id'])])
            ->header('Cache-Control', 'no-store');
    }

    public function services(Request $request)
    {
        if (!MobileApp::isNative($request)) {
            return redirect()->route($request->user()->preferredHomeRouteName());
        }

        return view('mobile.services');
    }

    public function download()
    {
        return response()->view('download-app', [
            'androidUrl' => MobileApp::downloadUrl('android'),
            'iosUrl' => MobileApp::downloadUrl('ios'),
        ])->header('Cache-Control', 'no-store, private');
    }
}
