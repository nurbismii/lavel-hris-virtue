<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Presensi;
use App\Models\User;
use App\Services\Presensi\AttendanceDateResolverService;
use App\Services\Presensi\AttendanceFulfillmentService;
use App\Services\Presensi\AttendanceStatusService;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $user = User::with([
            'role',
            'employee.departemen',
            'employee.divisi.departemen',
            'employee.workPattern',
        ])->where('id', auth()->user()->id)->first();
        $attendanceSummary = $this->buildAttendanceSummary($user);

        return view('user.dashboard', compact('user', 'attendanceSummary'));
    }

    private function buildAttendanceSummary(?User $user): ?array
    {
        $employee = optional($user)->employee;

        if (!$user || !$employee || !$user->nik_karyawan) {
            return null;
        }

        $now = now();
        $attendanceContext = app(AttendanceDateResolverService::class)->resolve($employee, $now);
        $attendanceDate = $attendanceContext['date'];
        $statusPresensi = app(AttendanceStatusService::class)->syncStatusForDate($user->nik_karyawan, $attendanceDate);
        $presensi = Presensi::where('nik_karyawan', $user->nik_karyawan)
            ->whereDate('tanggal', $attendanceDate)
            ->first();
        $scheduleSource = $attendanceContext['shift'] ?: $employee->workPattern;
        $fulfillment = app(AttendanceFulfillmentService::class)->evaluate($presensi, $scheduleSource, $attendanceDate);
        $nextAction = $this->resolveNextAttendanceAction($presensi, $statusPresensi);

        return [
            'server_now_iso' => $now->toIso8601String(),
            'date_text' => Carbon::parse($attendanceDate)->translatedFormat('l, d F Y'),
            'attendance_date' => $attendanceDate,
            'is_cross_day' => (bool) ($attendanceContext['is_cross_day'] ?? false),
            'shift_label' => $attendanceContext['shift']
                ? $attendanceContext['shift']->code . ' - ' . $attendanceContext['shift']->name
                : 'AUTO / mengikuti pola kerja',
            'work_time_range' => $attendanceContext['schedule_data']['work_time_range_text'] ?? 'Belum diatur',
            'break_time_range' => $attendanceContext['schedule_data']['break_time_range_text'] ?? 'Tidak diatur',
            'expected_work_duration' => $attendanceContext['schedule_data']['expected_work_duration_text'] ?? 'Belum diatur',
            'status_presensi' => $statusPresensi,
            'fulfillment' => $fulfillment,
            'next_action' => $nextAction,
            'times' => [
                [
                    'key' => 'masuk',
                    'label' => 'Masuk',
                    'icon' => 'fas fa-sign-in-alt',
                    'time' => $this->formatAttendanceClock(optional($presensi)->jam_masuk, $attendanceDate),
                    'filled' => filled(optional($presensi)->jam_masuk),
                    'active' => $nextAction['key'] === 'masuk',
                ],
                [
                    'key' => 'istirahat',
                    'label' => 'Istirahat',
                    'icon' => 'fas fa-sign-out-alt',
                    'time' => $this->formatAttendanceClock(optional($presensi)->jam_istirahat, $attendanceDate),
                    'filled' => filled(optional($presensi)->jam_istirahat),
                    'active' => $nextAction['key'] === 'istirahat',
                ],
                [
                    'key' => 'kembali',
                    'label' => 'Kembali',
                    'icon' => 'fas fa-undo-alt',
                    'time' => $this->formatAttendanceClock(optional($presensi)->jam_kembali_istirahat, $attendanceDate),
                    'filled' => filled(optional($presensi)->jam_kembali_istirahat),
                    'active' => $nextAction['key'] === 'kembali',
                ],
                [
                    'key' => 'pulang',
                    'label' => 'Pulang',
                    'icon' => 'fas fa-sign-out-alt',
                    'time' => $this->formatAttendanceClock(optional($presensi)->jam_pulang, $attendanceDate),
                    'filled' => filled(optional($presensi)->jam_pulang),
                    'active' => $nextAction['key'] === 'pulang',
                ],
            ],
        ];
    }

    private function resolveNextAttendanceAction(?Presensi $presensi, ?string $statusPresensi): array
    {
        if ($statusPresensi) {
            return [
                'key' => 'status',
                'label' => $statusPresensi,
                'description' => 'Tanggal presensi ini tercatat sebagai status khusus.',
                'tone' => 'secondary',
            ];
        }

        if (!$presensi || !$presensi->jam_masuk) {
            return [
                'key' => 'masuk',
                'label' => 'Absen Masuk',
                'description' => 'Langkah berikutnya adalah presensi masuk.',
                'tone' => 'primary',
            ];
        }

        if (!$presensi->jam_istirahat) {
            return [
                'key' => 'istirahat',
                'label' => 'Mulai Istirahat',
                'description' => 'Presensi masuk sudah tercatat.',
                'tone' => 'warning',
            ];
        }

        if (!$presensi->jam_kembali_istirahat) {
            return [
                'key' => 'kembali',
                'label' => 'Kembali Istirahat',
                'description' => 'Lengkapi presensi kembali dari istirahat.',
                'tone' => 'info',
            ];
        }

        if (!$presensi->jam_pulang) {
            return [
                'key' => 'pulang',
                'label' => 'Absen Pulang',
                'description' => 'Tutup presensi tanggal aktif dengan absen pulang.',
                'tone' => 'danger',
            ];
        }

        return [
            'key' => 'done',
            'label' => 'Presensi Lengkap',
            'description' => 'Semua waktu presensi tanggal aktif sudah tercatat.',
            'tone' => 'success',
        ];
    }

    private function formatAttendanceClock($value, string $attendanceDate, string $empty = '--:--'): string
    {
        if (!$value) {
            return $empty;
        }

        $clock = Carbon::parse($value);
        $suffix = $clock->toDateString() > Carbon::parse($attendanceDate)->toDateString() ? ' +1' : '';

        return $clock->format('H:i') . $suffix;
    }
}
