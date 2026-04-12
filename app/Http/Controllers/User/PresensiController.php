<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\LogPresensi;
use App\Models\LokasiAbsen;
use App\Models\Presensi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

class PresensiController extends Controller
{
    private const FACE_DISTANCE_THRESHOLD = 0.5;

    public function index()
    {
        $title = 'Delete Data!';
        $text = "Are you sure you want to delete?";
        confirmDelete($title, $text);

        $user = Auth::user();
        $karyawan = $user->employee;

        $lokasi = LokasiAbsen::where('divisi_id', $karyawan->divisi_id)->first();

        $absensiHariIni = Presensi::where('nik_karyawan', Auth::user()->nik_karyawan)
            ->whereDate('created_at', today())
            ->first();

        $today = Carbon::today();

        if ($today->day >= 16) {
            $start = Carbon::create($today->year, $today->month, 16);
            $end = (clone $start)->addMonth()->day(15);
        } else {
            $start = Carbon::create($today->year, $today->month, 16)->subMonth();
            $end = Carbon::create($today->year, $today->month, 15);
        }

        return view('user.presensi.index', [
            'presensi' => Presensi::where('nik_karyawan', $user->nik_karyawan)
                ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
                ->orderBy('tanggal', 'desc')
                ->get(),
            'absensiHariIni' => $absensiHariIni,
            'lokasi' => $lokasi,
            'cutoffStart' => $start,
            'cutoffEnd' => $end,
        ]);
    }

    public function store(Request $request, $type)
    {
        $request->validate([
            'lat_user' => 'required|numeric',
            'long_user' => 'required|numeric',
            'accuracy' => 'required|numeric',
            'speed' => 'nullable|numeric',
            'device_info' => 'nullable|string',
            'selfie_capture' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
            'face_verified' => 'required|boolean',
            'face_distance' => 'required|numeric|min:0|max:2',
            'face_detection_count' => 'required|integer|min:1|max:5',
            'face_verification_meta' => 'nullable|string|max:5000',
        ]);

        $user = Auth::user();
        $karyawan = $user->employee;
        $today = Carbon::today()->format('Y-m-d');

        $lokasi = LokasiAbsen::where('divisi_id', $karyawan->divisi_id)->first();

        if (!$lokasi) {
            toast()->error('Error', 'Lokasi presensi belum diatur');
            return back();
        }

        if (empty($karyawan->face_reference_path)) {
            toast()->error('Error', 'Foto referensi wajah belum didaftarkan oleh admin.');
            return back();
        }

        if ($request->accuracy < 5 || $request->accuracy > 60) {
            return back()->with('error', 'GPS tidak valid.');
        }

        if ($request->speed > 50) {
            return back()->with('error', 'Pergerakan tidak wajar.');
        }

        $distance = $this->calculateDistance(
            $request->lat_user,
            $request->long_user,
            $lokasi->lat,
            $lokasi->long
        );

        if ($distance > $lokasi->radius) {
            toast()->success('Error', 'Anda berada di luar radius presensi!');
            return back();
        }

        $faceVerified = filter_var($request->face_verified, FILTER_VALIDATE_BOOLEAN);

        if (!$faceVerified) {
            toast()->error('Error', 'Verifikasi wajah gagal. Silakan ambil selfie lagi.');
            return back();
        }

        if ((int) $request->face_detection_count !== 1) {
            toast()->error('Error', 'Selfie harus memuat tepat satu wajah.');
            return back();
        }

        if ((float) $request->face_distance > self::FACE_DISTANCE_THRESHOLD) {
            toast()->error('Error', 'Wajah tidak cocok dengan foto referensi.');
            return back();
        }

        $securityScore = 100;

        if ($request->accuracy > 75) {
            $securityScore -= 20;
        }

        if ($request->speed && $request->speed > 40) {
            $securityScore -= 30;
        }

        $lastPresensi = Presensi::where('nik_karyawan', $user->nik_karyawan)
            ->whereDate('tanggal', '<', $today)
            ->latest()
            ->first();

        $currentIp = $request->ip();

        if ($lastPresensi && $lastPresensi->ip_address !== $currentIp) {
            $securityScore -= 15;
        }

        $currentDevice = $request->device_info;

        if ($lastPresensi && $lastPresensi->device_info !== $currentDevice) {
            $securityScore -= 25;
        }

        $isSuspicious = $securityScore < 60 ? 'TRUE' : 'FALSE';
        $selfiePath = $this->storeFaceSelfie($request->file('selfie_capture'), $user->nik_karyawan, $type);

        $absensi = Presensi::updateOrCreate(
            [
                'nik_karyawan' => $user->nik_karyawan,
                'tanggal' => $today,
            ],
            [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_info' => $request->device_info,
                'security_score' => $securityScore,
                'is_suspicious' => $isSuspicious,
                'face_selfie_path' => $selfiePath,
                'face_verified' => true,
                'face_verification_distance' => $request->face_distance,
                'face_verified_at' => now(),
                'face_verification_method' => 'face-api.js',
                'face_verification_meta' => $request->face_verification_meta,
            ]
        );

        $now = Carbon::now();

        switch ($type) {
            case 'masuk':
                if ($absensi->jam_masuk) {
                    toast()->error('Error', 'Anda sudah absen masuk.');
                    return back();
                }

                $absensi->jam_masuk = $now;
                break;

            case 'istirahat':
                if (!$absensi->jam_masuk) {
                    toast()->error('Error', 'Silakan absen masuk dulu.');
                    return back();
                }

                if ($absensi->jam_istirahat) {
                    toast()->error('Error', 'Kamu sudah absen istirahat.');
                    return back();
                }

                $absensi->jam_istirahat = $now;
                break;

            case 'kembali':
                if (!$absensi->jam_istirahat) {
                    toast()->error('Error', 'Silakan mulai istirahat dulu.');
                    return back();
                }

                if ($absensi->jam_kembali_istirahat) {
                    toast()->error('Error', 'Kamu sudah kembali dari istirahat');
                    return back();
                }

                $absensi->jam_kembali_istirahat = $now;
                break;

            case 'pulang':
                if (!$absensi->jam_kembali_istirahat) {
                    toast()->error('Error', 'Silakan kembali dari istirahat dulu');
                    return back();
                }

                if ($absensi->jam_pulang) {
                    toast()->error('Error', 'Kamu sudah presensi pulang');
                    return back();
                }

                $absensi->jam_pulang = $now;
                break;

            default:
                toast()->error('Error', 'Tipe presensi tidak valid');
                return back();
        }

        $absensi->save();

        toast()->success('Success', 'Presensi berhasil dicatat.');
        return back();
    }

    public function logGps(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'long' => 'required|numeric',
            'accuracy' => 'nullable|numeric',
            'speed' => 'nullable|numeric',
        ]);

        $user = auth()->user();

        LogPresensi::create([
            'nik_karyawan' => $user->nik_karyawan,
            'tanggal' => now()->format('Y-m-d'),
            'lat' => $request->lat,
            'long' => $request->long,
            'accuracy' => $request->accuracy,
            'speed' => $request->speed,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return response()->json(['status' => 'ok']);
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) *
            cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function storeFaceSelfie($file, $nik, $type)
    {
        $datePath = now()->format('Y/m/d');
        $directory = public_path('presensi-selfie/' . $nik . '/' . $datePath);

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filename = $type . '_' . now()->format('His') . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move($directory, $filename);

        return 'presensi-selfie/' . $nik . '/' . $datePath . '/' . $filename;
    }
}
