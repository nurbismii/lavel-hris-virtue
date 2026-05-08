<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceCorrection\StoreAttendanceCorrectionRequest;
use App\Models\AttendanceCorrection;
use App\Models\Presensi;
use App\Services\AttendanceCorrection\AttendanceCorrectionService;
use App\Services\Storage\SensitiveFileStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AttendanceCorrectionController extends Controller
{
    public function index(Request $request)
    {
        $isTableReady = Schema::hasTable('attendance_corrections');
        $corrections = null;

        if ($isTableReady) {
            $corrections = AttendanceCorrection::query()
                ->with(['employee', 'hodProcessor:id,name', 'hrdProcessor:id,name'])
                ->where('nik_karyawan', (string) $request->user()->nik_karyawan)
                ->latest('tanggal')
                ->latest('id')
                ->paginate(20)
                ->withQueryString();
        }

        return view('user.attendance-corrections.index', [
            'corrections' => $corrections,
            'isTableReady' => $isTableReady,
        ]);
    }

    public function create(Request $request)
    {
        if (blank($request->user()->nik_karyawan)) {
            toast()->warning('Peringatan', 'Akun Anda belum terhubung dengan NIK karyawan.');
            return redirect()->route('attendance-corrections.index');
        }

        $recentPresensi = Presensi::query()
            ->where('nik_karyawan', $request->user()->nik_karyawan)
            ->latest('tanggal')
            ->limit(10)
            ->get();

        return view('user.attendance-corrections.create', [
            'recentPresensi' => $recentPresensi,
            'statusOptions' => AttendanceCorrection::statusPresensiOptions(),
        ]);
    }

    public function store(StoreAttendanceCorrectionRequest $request, AttendanceCorrectionService $service)
    {
        if (!Schema::hasTable('attendance_corrections')) {
            toast()->warning('Peringatan', 'Fitur koreksi presensi belum aktif. Jalankan migrasi database terlebih dahulu.');
            return back()->withInput();
        }

        $result = $service->submit(
            $request->user(),
            $request->validated(),
            $request->file('attachment')
        );

        if (!$result['status']) {
            toast()->warning('Peringatan', $result['message']);
            return back()->withInput();
        }

        toast()->success('Berhasil', $result['message']);
        return redirect()->route('attendance-corrections.index');
    }

    public function attachment(
        Request $request,
        AttendanceCorrection $attendanceCorrection,
        SensitiveFileStorageService $storage
    ) {
        abort_unless($attendanceCorrection->attachment_path, 404);
        abort_unless($this->canViewAttachment($request, $attendanceCorrection), 403);

        $path = $storage->resolvePath($attendanceCorrection->attachment_path, ['attendance-corrections/']);
        abort_unless($path, 404);

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
        ]);
    }

    private function canViewAttachment(Request $request, AttendanceCorrection $attendanceCorrection): bool
    {
        $user = $request->user();

        if ((string) $attendanceCorrection->created_by === (string) $user->id
            || (string) $attendanceCorrection->nik_karyawan === (string) $user->nik_karyawan) {
            return true;
        }

        if (!$user->hasMenuAccess(['approval_hod', 'approval_hr'])) {
            return false;
        }

        return $user->applyEmployeeRelationScope(
            AttendanceCorrection::query()->whereKey($attendanceCorrection->getKey())
        )->exists();
    }
}
