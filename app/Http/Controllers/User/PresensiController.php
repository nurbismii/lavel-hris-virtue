<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Presensi\PresensiRequest;
use App\Models\LogPresensi;
use App\Models\LokasiAbsen;
use App\Models\Presensi;
use App\Services\Presensi\AttendanceSecurityService;
use App\Services\Presensi\AttendanceDateResolverService;
use App\Services\Presensi\AttendanceFulfillmentService;
use App\Services\Presensi\ServerSideFaceVerificationService;
use App\Services\Presensi\ShiftAssignmentService;
use App\Services\Presensi\AttendanceStatusService;
use App\Services\Presensi\OvertimeOrderService;
use App\Services\Presensi\WorkScheduleService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class PresensiController extends Controller
{
    private const FACE_DISTANCE_THRESHOLD = 0.5;

    public function index()
    {
        $title = 'Delete Data!';
        $text = "Are you sure you want to delete?";
        confirmDelete($title, $text);

         // Liveness challenge untuk presensi hari ini
        $faceChallenge = [
            'id' => (string) Str::uuid(),
            'action' => 'blink',
            'label' => 'Kedipkan mata sekali',
            'issued_at' => now()->toDateTimeString(),
            'expires_at' => now()->addSeconds(60)->toDateTimeString(),
        ];

        session([
            'presensi_face_challenge' => $faceChallenge,
        ]);

        $user = Auth::user();
        $karyawan = $user->employee;
        $karyawan->loadMissing('workPattern');
        $now = Carbon::now();
        $attendanceDateResolver = app(AttendanceDateResolverService::class);
        $activeAttendanceContext = $attendanceDateResolver->resolve($karyawan, $now);
        $activeAttendanceDateString = $activeAttendanceContext['date'];
        $activeAttendanceDate = Carbon::parse($activeAttendanceDateString);

        $lokasi = LokasiAbsen::where('divisi_id', $karyawan->divisi_id)->first();
        app(AttendanceStatusService::class)->syncStatusForDate($user->nik_karyawan, $activeAttendanceDateString);
        $overtimeService = app(OvertimeOrderService::class);
        $workScheduleService = app(WorkScheduleService::class);
        $attendanceFulfillmentService = app(AttendanceFulfillmentService::class);
        $activeOvertimeOrder = $overtimeService->getAcceptedOrderForDate($user->nik_karyawan, $activeAttendanceDateString);

        $absensiHariIni = Presensi::where('nik_karyawan', Auth::user()->nik_karyawan)
            ->whereDate('tanggal', $activeAttendanceDateString)
            ->first();
        $statusPresensiHariIni = optional($absensiHariIni)->status_presensi;
        $nextAttendanceType = $this->resolveNextAttendanceType($absensiHariIni, $statusPresensiHariIni);
        $attendanceChallenge = null;

        if ($lokasi && filled($karyawan->face_reference_path) && $nextAttendanceType) {
            $attendanceChallenge = app(AttendanceSecurityService::class)
                ->issueChallenge($user, request(), $activeAttendanceDateString, $nextAttendanceType);
        }

        $today = Carbon::today();

        if ($today->day >= 16) {
            $start = Carbon::create($today->year, $today->month, 16);
            $end = (clone $start)->addMonth()->day(15);
        } else {
            $start = Carbon::create($today->year, $today->month, 16)->subMonth();
            $end = Carbon::create($today->year, $today->month, 15);
        }

        $shiftAssignmentService = app(ShiftAssignmentService::class);
        $shiftAssignments = $shiftAssignmentService->getAssignmentsForEmployees([$user->nik_karyawan], $start, $end);
        $shiftAssignmentsByDate = $shiftAssignments->keyBy(fn($assignment) => Carbon::parse($assignment->shift_date)->toDateString());
        $currentShift = $activeAttendanceContext['shift'];
        $currentScheduleSource = $activeAttendanceContext['schedule_source'];
        $currentScheduleData = $activeAttendanceContext['schedule_data'];

        $presensiRecords = Presensi::where('nik_karyawan', $user->nik_karyawan)
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->orderBy('tanggal', 'desc')
            ->get();

        $existingDates = $presensiRecords
            ->filter(function ($row) {
                return filled($row->status_presensi)
                    || filled($row->jam_masuk)
                    || filled($row->jam_istirahat)
                    || filled($row->jam_kembali_istirahat)
                    || filled($row->jam_pulang);
            })
            ->pluck('tanggal')
            ->map(fn($tanggal) => Carbon::parse($tanggal)->toDateString())
            ->all();

        $virtualOffRows = collect($workScheduleService->buildVirtualOffRows(
            $user->nik_karyawan,
            $start,
            $end,
            collect([$karyawan]),
            $existingDates
        ));

        $virtualAlphaRows = collect($overtimeService->buildAcceptedAlphaVirtualRows(
            $user->nik_karyawan,
            $start,
            $end,
            $existingDates
        ));

        $presensiRecords = $presensiRecords
            ->keyBy(fn($row) => Carbon::parse($row->tanggal)->toDateString())
            ->merge($virtualOffRows->keyBy(fn($row) => Carbon::parse($row->tanggal)->toDateString()))
            ->merge($virtualAlphaRows->keyBy(fn($row) => Carbon::parse($row->tanggal)->toDateString()))
            ->map(function ($row) use ($attendanceFulfillmentService, $karyawan, $shiftAssignmentsByDate) {
                $dateString = Carbon::parse($row->tanggal)->toDateString();
                $resolvedShift = optional($shiftAssignmentsByDate->get($dateString))->shift;
                $scheduleSource = $resolvedShift ?: $karyawan->workPattern;

                $row->resolved_shift = $resolvedShift;
                $row->attendance_fulfillment = $attendanceFulfillmentService->evaluate($row, $scheduleSource, $dateString);

                return $row;
            })
            ->sortByDesc(fn($row) => Carbon::parse($row->tanggal)->timestamp)
            ->values();

        $todayFulfillment = $attendanceFulfillmentService->evaluate($absensiHariIni, $currentScheduleSource, $activeAttendanceDateString);

        return view('user.presensi.index', [
            'faceChallenge' => $faceChallenge,
            'presensi' => $presensiRecords,
            'absensiHariIni' => $absensiHariIni,
            'lokasi' => $lokasi,
            'cutoffStart' => $start,
            'cutoffEnd' => $end,
            'activeOvertimeOrder' => $activeOvertimeOrder,
            'activeAttendanceDate' => $activeAttendanceDate,
            'isCrossDayAttendance' => $activeAttendanceContext['is_cross_day'],
            'todayFulfillment' => $todayFulfillment,
            'workPattern' => $karyawan->workPattern,
            'currentShift' => $currentShift,
            'currentScheduleSource' => $currentScheduleSource,
            'currentScheduleData' => $currentScheduleData,
            'nextAttendanceType' => $nextAttendanceType,
            'attendanceChallenge' => $attendanceChallenge,
        ]);
    }

    public function store(
        PresensiRequest $request,
        $type,
        AttendanceSecurityService $securityService,
        ServerSideFaceVerificationService $serverFaceVerificationService
    ) {
        $user = Auth::user();
        $karyawan = $user->employee;
        $karyawan->loadMissing('workPattern');
        $now = Carbon::now();
        $attendanceContext = app(AttendanceDateResolverService::class)->resolve($karyawan, $now);
        $attendanceDate = $attendanceContext['date'];

        if (
            $type === 'masuk'
            && $attendanceContext['is_cross_day']
            && optional($attendanceContext['presensi'])->jam_masuk
            && !optional($attendanceContext['presensi'])->jam_pulang
        ) {
            return $this->failPresensi('Selesaikan presensi pulang shift sebelumnya terlebih dahulu.', 'warning');
        }

        $statusHariIni = app(AttendanceStatusService::class)->syncStatusForDate($user->nik_karyawan, $attendanceDate);

        if ($statusHariIni) {
            return $this->failPresensi('Presensi tanggal ' . formatDateIndonesia($attendanceDate) . ' berstatus ' . $statusHariIni . '. Tidak perlu absen jam.', 'warning');
        }

        $request->validated();

        $lokasi = LokasiAbsen::where('divisi_id', $karyawan->divisi_id)->first();

        if (!$lokasi) {
            return $this->failPresensi('Lokasi presensi belum diatur.');
        }

        if (empty($karyawan->face_reference_path)) {
            return $this->failPresensi('Foto referensi wajah belum didaftarkan oleh admin.');
        }

        if ($request->accuracy < 5 || $request->accuracy > 60) {
            return $this->failPresensi('GPS tidak valid. Tunggu akurasi lokasi membaik lalu coba lagi.');
        }

        if ($request->speed > 50) {
            return $this->failPresensi('Pergerakan tidak wajar. Presensi ditolak untuk keamanan lokasi.');
        }

        $distance = $this->calculateDistance(
            $request->lat_user,
            $request->long_user,
            $lokasi->lat,
            $lokasi->long
        );

        if ($distance > $lokasi->radius) {
            return $this->failPresensi('Anda berada di luar radius presensi!');
        }

        $securityService->validateRecentGpsEvidence($request, $user, $lokasi);

        $challenge = $securityService->consumeChallenge($request, $user, $attendanceDate, $type);
        $faceResult = $securityService->validateFacePayload($request, $challenge);

        $selfieFile = $request->hasFile('selfie_capture')
            ? $request->file('selfie_capture')
            : $this->makeFaceSelfieFromBase64($request->input('selfie_capture_data'));

        if (!$selfieFile) {
            return $this->failPresensi('Selfie kamera tidak valid. Silakan ulangi verifikasi wajah.');
        }

        $selfieAudit = $securityService->validateSelfieImage($selfieFile, $user, $karyawan->face_reference_path);
        $serverVerification = $serverFaceVerificationService->verify(
            $selfieFile,
            $user,
            $karyawan->face_reference_path,
            $request,
            $challenge,
            $faceResult,
            $selfieAudit
        );

        if (($serverVerification['status'] ?? null) === Presensi::STATUS_ABSEN_REJECTED) {
            return $this->failPresensi($serverVerification['message'] ?? 'Server-side liveness atau face verification ditolak.');
        }

        $storedFaceMeta = $securityService->buildStoredMeta(
            $request,
            $challenge,
            $faceResult,
            $selfieAudit,
            $serverVerification
        );

        $securityScore = 100;

        if ($request->accuracy > 40) {
            $securityScore -= 20;
        }

        if ($request->speed && $request->speed > 40) {
            $securityScore -= 30;
        }

        if (($serverVerification['status'] ?? null) === Presensi::STATUS_ABSEN_PENDING_REVIEW) {
            $securityScore -= 35;
        }

        $lastPresensi = Presensi::where('nik_karyawan', $user->nik_karyawan)
            ->whereDate('tanggal', '<', $attendanceDate)
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
        $selfiePath = null;

        try {
            DB::transaction(function () use (
                $request,
                $type,
                $user,
                $now,
                $attendanceDate,
                $selfieFile,
                $selfieAudit,
                $storedFaceMeta,
                $challenge,
                $serverVerification,
                $securityScore,
                $isSuspicious,
                &$selfiePath
            ) {
                $absensi = Presensi::where('nik_karyawan', $user->nik_karyawan)
                    ->whereDate('tanggal', $attendanceDate)
                    ->lockForUpdate()
                    ->first();

                if (!$absensi) {
                    $absensi = new Presensi([
                        'nik_karyawan' => $user->nik_karyawan,
                        'tanggal' => $attendanceDate,
                    ]);
                }

                switch ($type) {
                    case 'masuk':
                        if ($absensi->jam_masuk) {
                            $this->failPresensiValidation('Anda sudah absen masuk.');
                        }

                        $absensi->jam_masuk = $now;
                        break;

                    case 'istirahat':
                        if (!$absensi->jam_masuk) {
                            $this->failPresensiValidation('Silakan absen masuk dulu.');
                        }

                        if ($absensi->jam_istirahat) {
                            $this->failPresensiValidation('Kamu sudah absen istirahat.');
                        }

                        $absensi->jam_istirahat = $now;
                        break;

                    case 'kembali':
                        if (!$absensi->jam_istirahat) {
                            $this->failPresensiValidation('Silakan mulai istirahat dulu.');
                        }

                        if ($absensi->jam_kembali_istirahat) {
                            $this->failPresensiValidation('Kamu sudah kembali dari istirahat.');
                        }

                        $absensi->jam_kembali_istirahat = $now;
                        break;

                    case 'pulang':
                        if (!$absensi->jam_kembali_istirahat) {
                            $this->failPresensiValidation('Silakan kembali dari istirahat dulu.');
                        }

                        if ($absensi->jam_pulang) {
                            $this->failPresensiValidation('Kamu sudah presensi pulang.');
                        }

                        $absensi->jam_pulang = $now;
                        break;

                    default:
                        $this->failPresensiValidation('Tipe presensi tidak valid.');
                }

                $selfiePath = $this->storeFaceSelfie($selfieFile, $user->nik_karyawan, $type);
                $absensi->ip_address = $request->ip();
                $absensi->user_agent = $request->userAgent();
                $absensi->device_info = $request->device_info;
                $absensi->security_score = $securityScore;
                $absensi->is_suspicious = $isSuspicious;
                $absensi->status_absen = $serverVerification['status'] ?? Presensi::STATUS_ABSEN_PENDING_REVIEW;
                $absensi->face_selfie_path = $selfiePath;
                $absensi->face_selfie_hash = $selfieAudit['hash'];
                $serverVerified = ($serverVerification['status'] ?? null) === Presensi::STATUS_ABSEN_VERIFIED;
                $absensi->face_verified = $serverVerified;
                $absensi->face_verification_distance = $this->verificationDistance($serverVerification, $request->face_distance);
                $absensi->face_verified_at = $serverVerified ? now() : null;
                $absensi->face_verification_method = $serverVerification['method'] ?? 'server-side-passive-review';
                $absensi->face_verification_meta = $storedFaceMeta;
                $absensi->presensi_challenge_id = $challenge['id'];
                $absensi->save();
            });
        } catch (Throwable $exception) {
            if ($selfiePath) {
                $this->deleteStoredSelfie($selfiePath);
            }

            throw $exception;
        }

        if (($serverVerification['status'] ?? null) === Presensi::STATUS_ABSEN_PENDING_REVIEW) {
            toast()->warning('Menunggu Review', 'Presensi dicatat, tetapi perlu review server-side face verification.');
        } else {
            toast()->success('Success', 'Presensi berhasil dicatat dan terverifikasi server.');
        }

        return back();
    }

    private function failPresensi(string $message, string $level = 'error')
    {
        if ($level === 'warning') {
            toast()->warning('Peringatan', $message);
        } else {
            toast()->error('Error', $message);
        }

        return back()->with('error', $message);
    }

    private function failPresensiValidation(string $message): void
    {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'presensi' => $message,
        ]);
    }

    private function verificationDistance(array $serverVerification, $clientDistance)
    {
        $provider = $serverVerification['provider'] ?? null;

        if (is_array($provider) && isset($provider['distance']) && is_numeric($provider['distance'])) {
            return $provider['distance'];
        }

        return $clientDistance;
    }

    private function resolveNextAttendanceType(?Presensi $absensi, ?string $statusPresensi): ?string
    {
        if ($statusPresensi) {
            return null;
        }

        if (!$absensi || !$absensi->jam_masuk) {
            return 'masuk';
        }

        if (!$absensi->jam_istirahat) {
            return 'istirahat';
        }

        if (!$absensi->jam_kembali_istirahat) {
            return 'kembali';
        }

        if (!$absensi->jam_pulang) {
            return 'pulang';
        }

        return null;
    }

    public function logGps(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'long' => 'required|numeric',
            'accuracy' => 'required|numeric|min:0|max:60',
            'speed' => 'nullable|numeric|min:0|max:80',
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

    public function faceReference(Request $request)
    {
        $employee = $request->user()->employee;
        $relativePath = optional($employee)->face_reference_path;

        abort_if(blank($relativePath), 404, 'Foto referensi wajah belum tersedia.');

        $normalizedPath = str_replace('\\', '/', ltrim($relativePath, '/'));
        $expectedDirectory = 'face-reference/' . $request->user()->nik_karyawan . '/';

        abort_if(
            str_contains($normalizedPath, '..') || !Str::startsWith($normalizedPath, $expectedDirectory),
            404
        );

        $absolutePath = public_path($normalizedPath);

        abort_unless(File::isFile($absolutePath), 404, 'Foto referensi wajah tidak ditemukan.');

        return response()->file($absolutePath, [
            'Content-Type' => File::mimeType($absolutePath) ?: 'image/jpeg',
            'Content-Disposition' => 'inline; filename="face-reference"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $filename = $type . '_' . now()->format('His') . '_' . Str::lower(Str::random(12)) . '.' . $extension;
        $file->move($directory, $filename);

        return 'presensi-selfie/' . $nik . '/' . $datePath . '/' . $filename;
    }

    private function deleteStoredSelfie(string $path): void
    {
        $normalizedPath = str_replace('\\', '/', ltrim($path, '/'));

        if (str_contains($normalizedPath, '..') || !Str::startsWith($normalizedPath, 'presensi-selfie/')) {
            return;
        }

        $absolutePath = public_path($normalizedPath);

        if (File::isFile($absolutePath)) {
            File::delete($absolutePath);
        }
    }

    private function makeFaceSelfieFromBase64(?string $base64Image): ?UploadedFile
    {
        if (!$base64Image) {
            return null;
        }

        if (!preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,/', $base64Image, $matches)) {
            return null;
        }

        $binaryImage = base64_decode(substr($base64Image, strpos($base64Image, ',') + 1), true);

        if ($binaryImage === false || strlen($binaryImage) > (4 * 1024 * 1024)) {
            return null;
        }

        $extension = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
        $tempPath = tempnam(sys_get_temp_dir(), 'presensi_selfie_');

        if ($tempPath === false) {
            return null;
        }

        $imagePath = $tempPath . '.' . $extension;

        if (!@rename($tempPath, $imagePath)) {
            $imagePath = $tempPath;
        }

        File::put($imagePath, $binaryImage);

        return new UploadedFile(
            $imagePath,
            'selfie-live.' . $extension,
            'image/' . $extension,
            null,
            true
        );
    }
}
