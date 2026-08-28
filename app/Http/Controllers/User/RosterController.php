<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Roster\RosterRequest;
use App\Models\ApprovalDelegation;
use App\Models\Employee;
use App\Models\PeriodeKerjaRoster;
use App\Models\Roster;
use App\Models\RosterOffRequest;
use App\Models\RosterSchedule;
use App\Services\Approvals\ApprovalDelegationService;
use App\Services\Notifications\ApprovalNotificationService;
use App\Services\Presensi\AttendancePeriodLockService;
use App\Services\Storage\SensitiveFileStorageService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RosterController extends Controller
{
    //
    public function index()
    {
        $title = 'Delete Data!';
        $text = "Are you sure you want to delete?";
        confirmDelete($title, $text);

        $cutis = Roster::with('employee', 'periodeKerjaRoster')->where('nik_karyawan', Auth::user()->nik_karyawan)->get();

        return view('user.roster.index', compact('cutis'));
    }

    public function show($id)
    {
        $this->findUserRoster($id);

        return redirect()->route('roster.index');
    }

    public function attachment($id)
    {
        $roster = $this->findUserRoster($id);

        return $this->serveRosterAttachment($roster);
    }

    public function create()
    {
        $schedule = null;
        $requestedScheduleId = request()->query('roster_schedule');
        $scheduleId = is_scalar($requestedScheduleId) && ctype_digit((string) $requestedScheduleId)
            ? (int) $requestedScheduleId
            : null;
        if ($scheduleId) {
            $schedule = RosterSchedule::query()
                ->whereKey($scheduleId)
                ->where('employee_nik', Auth::user()->nik_karyawan)
                ->where('is_active', true)
                ->whereDate('off_start', '>=', Carbon::today()->toDateString())
                ->first();
            abort_unless($schedule, 404);
            abort_if(
                $this->hasActiveScheduleApplication($schedule->id),
                409,
                'Jadwal roster ini sudah memiliki pengajuan aktif.'
            );
        }

        return view('user.roster.create', compact('schedule'));
    }

    public function store(RosterRequest $request)
    {
        $validated = $request->validated();

        $periodLockMessage = app(AttendancePeriodLockService::class)->guardRange(
            $validated['periode_awal'],
            $validated['periode_akhir'],
            'Pengajuan roster'
        );

        if ($periodLockMessage) {
            toast()->warning('Peringatan', $periodLockMessage);
            return back()->withInput();
        }

        $statusPengajuanHod = 0;
        $statusPengajuanHrd = 0;
        $nikKaryawan = Auth::user()->nik_karyawan;
        $storedFilePath = null;
        $rosterNumberLockAcquired = false;

        try {
            DB::beginTransaction();

            $rosterNumberLockAcquired = $this->acquireRosterNumberLock((int) now()->year);
            $nomor_surat = $this->generateRosterNumber();
            $employee = Employee::query()
                ->where('nik', $nikKaryawan)
                ->lockForUpdate()
                ->firstOrFail();
            $schedule = null;
            $scheduleId = $validated['roster_schedule_id'] ?? null;
            if ($scheduleId !== null) {
                $schedule = RosterSchedule::query()
                    ->whereKey($scheduleId)
                    ->where('employee_nik', $nikKaryawan)
                    ->where('is_active', true)
                    ->whereDate('off_start', '>=', Carbon::today()->toDateString())
                    ->lockForUpdate()
                    ->first();
                if (!$schedule || $this->hasActiveScheduleApplication($schedule->id, true)) {
                    throw new \RuntimeException('Jadwal roster tidak tersedia untuk diajukan.');
                }
            }
            $delegationService = app(ApprovalDelegationService::class);
            $delegations = $delegationService->activeDelegationsForEmployee(
                $employee,
                ApprovalDelegation::MODULE_ROSTER,
                Auth::user()
            );

            $file_name = null;

            if ($request->hasFile('berkas_cuti')) {

                $upload = $request->file('berkas_cuti');
                $file_name = 'roster_' . now()->format('YmdHis') . '_' . Str::lower(Str::random(8)) . '.' . strtolower($upload->getClientOriginalExtension());

                $storedFilePath = app(SensitiveFileStorageService::class)->storeUploadedFileAs($upload, 'cuti-roster/' . $nikKaryawan, $file_name);
                $file_name = basename($storedFilePath);
            }

            $roster = Roster::create(array_merge([
                'nomor_surat' => $nomor_surat,
                'nik_karyawan' => $nikKaryawan,
                'roster_schedule_id' => $schedule?->id,
                'email' => $validated['email'],
                'no_telp' => $validated['no_telp'],
                'tanggal_pengajuan' => now(),

                // CUTI
                'tgl_mulai_cuti' => $request->tgl_mulai_cuti_roster,
                'tgl_mulai_cuti_berakhir' => $request->tgl_berakhir_cuti_roster,

                'tgl_mulai_cuti_tahunan' => $request->tgl_mulai_cuti_tahunan,
                'tgl_mulai_cuti_tahunan_berakhir' => $request->tgl_berakhir_cuti_tahunan,

                'tgl_mulai_off' => $request->tgl_mulai_off,
                'tgl_mulai_off_berakhir' => $request->tgl_berakhir_off,

                // INSENTIF
                'tgl_awal_kerja' => $request->tgl_awal_kerja,
                'tgl_akhir_kerja' => $request->tgl_akhir_kerja,

                // KEBERANGKATAN
                'tgl_keberangkatan' => $request->tanggal_keberangkatan,
                'jam_keberangkatan' => $request->jam_keberangkatan,
                'kota_awal_keberangkatan' => $request->kota_awal_keberangkatan,
                'kota_tujuan_keberangkatan' => $request->kota_tujuan_keberangkatan,
                'catatan_penting_keberangkatan' => $request->catatan_penting_keberangkatan,

                // KEPULANGAN
                'tgl_kepulangan' => $request->tanggal_kepulangan,
                'jam_kepulangan' => $request->jam_kepulangan,
                'kota_awal_kepulangan' => $request->kota_awal_kepulangan,
                'kota_tujuan_kepulangan' => $request->kota_tujuan_kepulangan,
                'catatan_penting_kepulangan' => $request->catatan_penting_kepulangan,

                'file' => $file_name,
                'status_pengajuan' => $statusPengajuanHod, // 0 = Menunggu, 1 = Disetujui, 2 = Ditolak
                'status_pengajuan_hrd' => $statusPengajuanHrd // 0 = Menunggu, 1 = Disetujui, 2 = Ditolak
            ], $delegationService->submissionPayload('cuti_roster', $delegations)));

            if ($schedule) {
                $schedule->update([
                    'realization_type' => $validated['tipe_rencana'] === '1'
                        ? RosterSchedule::REALIZATION_CUTI
                        : RosterSchedule::REALIZATION_INSENTIF,
                    'updated_by' => (string) Auth::id(),
                ]);
            }

            $delegationService->createAssignments($roster, $delegations, ApprovalDelegation::MODULE_ROSTER);

            $weeklyRoster = $this->applyApprovedOffToWeeklyStatus(
                $nikKaryawan,
                $request->periode_awal,
                $request->periode_akhir,
                $this->weeklyRosterPayload($request)
            );

            PeriodeKerjaRoster::create(array_merge([
                'cuti_roster_id' => $roster->id,
                'periode_awal' => $request->periode_awal,
                'periode_akhir' => $request->periode_akhir,
                'tipe_rencana' => $request->tipe_rencana,
                'alasan' => $request->alasan,
            ], $weeklyRoster));


            DB::commit();
        } catch (\Throwable $e) {

            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            if ($storedFilePath) {
                app(SensitiveFileStorageService::class)->delete($storedFilePath, ['cuti-roster/']);
            }

            report($e);

            toast()->error('Error', 'Pengajuan roster gagal disimpan. Periksa kembali data dan lampiran, lalu coba lagi.');
            return back()->withInput();
        } finally {
            if ($rosterNumberLockAcquired) {
                $this->releaseRosterNumberLock((int) now()->year);
            }
        }

        app(ApprovalNotificationService::class)->notifyRosterSubmitted($roster->fresh(['employee', 'periodeKerjaRoster']));

        toast()->success('Success', 'Cuti Roster created successfully');
        return back();
    }

    public function edit($id)
    {
        $roster = $this->findUserRoster($id);

        if (!$this->canManageRoster($roster)) {
            toast()->warning('Peringatan', 'Pengajuan roster yang sudah diproses tidak dapat diedit');
            return redirect()->route('roster.index');
        }

        return view('user.roster.edit', compact('roster'));
    }

    public function update(RosterRequest $request, $id)
    {
        $validated = $request->validated();

        $roster = $this->findUserRoster($id);

        if (!$this->canManageRoster($roster)) {
            toast()->warning('Peringatan', 'Pengajuan roster yang sudah diproses tidak dapat diubah');
            return redirect()->route('roster.index');
        }

        $existingPeriodLockMessage = app(AttendancePeriodLockService::class)->guardRoster(
            $roster,
            'Perubahan pengajuan roster'
        );

        if ($existingPeriodLockMessage) {
            toast()->warning('Peringatan', $existingPeriodLockMessage);
            return redirect()->route('roster.index');
        }

        $periodLockMessage = app(AttendancePeriodLockService::class)->guardRange(
            $validated['periode_awal'],
            $validated['periode_akhir'],
            'Perubahan pengajuan roster'
        );

        if ($periodLockMessage) {
            toast()->warning('Peringatan', $periodLockMessage);
            return back()->withInput();
        }

        $nikKaryawan = Auth::user()->nik_karyawan;
        $newStoredFilePath = null;
        $oldFilePath = $roster->file
            ? 'cuti-roster/' . $nikKaryawan . '/' . basename($roster->file)
            : null;

        try {
            DB::beginTransaction();

            $file_name = $roster->file; // default pakai file lama

            if ($request->hasFile('berkas_cuti')) {

                $upload = $request->file('berkas_cuti');
                $file_name = 'roster_' . now()->format('YmdHis') . '_' . Str::lower(Str::random(8)) . '.' . strtolower($upload->getClientOriginalExtension());

                $newStoredFilePath = app(SensitiveFileStorageService::class)->storeUploadedFileAs($upload, 'cuti-roster/' . $nikKaryawan, $file_name);
                $file_name = basename($newStoredFilePath);
            }

            $roster->update([

                'nik_karyawan' => $nikKaryawan,
                'email' => $validated['email'],
                'no_telp' => $validated['no_telp'],

                // CUTI
                'tgl_mulai_cuti' => $request->tgl_mulai_cuti_roster,
                'tgl_mulai_cuti_berakhir' => $request->tgl_berakhir_cuti_roster,

                'tgl_mulai_cuti_tahunan' => $request->tgl_mulai_cuti_tahunan,
                'tgl_mulai_cuti_tahunan_berakhir' => $request->tgl_berakhir_cuti_tahunan,

                'tgl_mulai_off' => $request->tgl_mulai_off,
                'tgl_mulai_off_berakhir' => $request->tgl_berakhir_off,

                // INSENTIF
                'tgl_awal_kerja' => $request->tgl_awal_kerja,
                'tgl_akhir_kerja' => $request->tgl_akhir_kerja,

                // KEBERANGKATAN
                'tgl_keberangkatan' => $request->tanggal_keberangkatan,
                'jam_keberangkatan' => $request->jam_keberangkatan,
                'kota_awal_keberangkatan' => $request->kota_awal_keberangkatan,
                'kota_tujuan_keberangkatan' => $request->kota_tujuan_keberangkatan,
                'catatan_penting_keberangkatan' => $request->catatan_penting_keberangkatan,

                // KEPULANGAN
                'tgl_kepulangan' => $request->tanggal_kepulangan,
                'jam_kepulangan' => $request->jam_kepulangan,
                'kota_awal_kepulangan' => $request->kota_awal_kepulangan,
                'kota_tujuan_kepulangan' => $request->kota_tujuan_kepulangan,
                'catatan_penting_kepulangan' => $request->catatan_penting_kepulangan,

                'file' => $file_name,
            ]);

            $weeklyRoster = $this->applyApprovedOffToWeeklyStatus(
                $nikKaryawan,
                $request->periode_awal,
                $request->periode_akhir,
                $this->weeklyRosterPayload($request)
            );

            PeriodeKerjaRoster::updateOrCreate(
                ['cuti_roster_id' => $roster->id],
                array_merge([
                    'periode_awal' => $request->periode_awal,
                    'periode_akhir' => $request->periode_akhir,
                    'tipe_rencana' => $request->tipe_rencana,
                    'alasan' => $request->alasan,
                ], $weeklyRoster)
            );

            DB::commit();
        } catch (\Throwable $e) {

            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            if ($newStoredFilePath) {
                app(SensitiveFileStorageService::class)->delete($newStoredFilePath, ['cuti-roster/']);
            }

            report($e);

            toast()->error('Error', 'Pengajuan roster gagal diperbarui. Periksa kembali data dan lampiran, lalu coba lagi.');
            return back()->withInput();
        }

        if ($newStoredFilePath && $oldFilePath) {
            app(SensitiveFileStorageService::class)->delete($oldFilePath, ['cuti-roster/']);
        }

        toast()->success('Success', 'Cuti Roster updated successfully');
        return redirect()->route('roster.index');
    }

    public function destroy($id)
    {
        $roster = $this->findUserRoster($id);

        if (!$this->canManageRoster($roster)) {
            toast()->warning('Peringatan', 'Pengajuan roster yang sudah diproses tidak dapat dihapus');
            return redirect()->route('roster.index');
        }

        $periodLockMessage = app(AttendancePeriodLockService::class)->guardRoster(
            $roster,
            'Penghapusan pengajuan roster'
        );

        if ($periodLockMessage) {
            toast()->warning('Peringatan', $periodLockMessage);
            return redirect()->route('roster.index');
        }

        $filePath = $roster->file
            ? 'cuti-roster/' . $roster->nik_karyawan . '/' . basename($roster->file)
            : null;

        try {
            DB::transaction(function () use ($roster) {
                $roster->periodeKerjaRoster()->delete();
                $roster->delete();
            });
        } catch (\Throwable $e) {
            report($e);

            toast()->error('Error', 'Pengajuan roster gagal dihapus. Silakan coba lagi.');
            return redirect()->route('roster.index');
        }

        if ($filePath) {
            app(SensitiveFileStorageService::class)->delete($filePath, ['cuti-roster/']);
        }

        toast()->success('Success', 'Pengajuan roster berhasil dihapus');
        return redirect()->route('roster.index');
    }

    private function generateRosterNumber(): string
    {
        $bulan = bulan_romawi(now()->format('m'));
        $tahun = now()->format('Y');
        $jml_cuti = no_urut_surat($this->nextRosterNumberSequence($tahun));

        return '02-' . $jml_cuti . '/BR/HRD-VDNI/' . $bulan . '/' . $tahun;
    }

    private function hasActiveScheduleApplication(int $scheduleId, bool $lock = false): bool
    {
        $query = Roster::query()
            ->where('roster_schedule_id', $scheduleId)
            ->where(function ($query) {
                $query->where(function ($statusQuery) {
                    $statusQuery->whereNull('status_pengajuan')
                        ->orWhere('status_pengajuan', '!=', 2);
                })->where(function ($statusQuery) {
                    $statusQuery->whereNull('status_pengajuan_hrd')
                        ->orWhere('status_pengajuan_hrd', '!=', 2);
                });
            });

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->exists();
    }

    private function nextRosterNumberSequence(string $tahun): int
    {
        $pattern = '02-%/BR/HRD-VDNI/%/' . $tahun;

        if (DB::getDriverName() === 'mysql') {
            $maxSequence = Roster::where('nomor_surat', 'like', $pattern)
                ->lockForUpdate()
                ->selectRaw('MAX(CAST(SUBSTRING(nomor_surat, 4, 4) AS UNSIGNED)) as max_sequence')
                ->value('max_sequence');

            return ((int) $maxSequence) + 1;
        }

        $maxSequence = Roster::where('nomor_surat', 'like', $pattern)
            ->lockForUpdate()
            ->pluck('nomor_surat')
            ->reduce(function (int $max, ?string $nomorSurat) use ($tahun) {
                $regex = '/^02-(\d{4})\/BR\/HRD-VDNI\/[^\/]+\/' . preg_quote($tahun, '/') . '$/';

                if (!$nomorSurat || !preg_match($regex, $nomorSurat, $matches)) {
                    return $max;
                }

                return max($max, (int) $matches[1]);
            }, 0);

        return $maxSequence + 1;
    }

    private function acquireRosterNumberLock(int $year): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        $result = DB::selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$this->rosterNumberLockName($year)]);

        if ((int) ($result->acquired ?? 0) !== 1) {
            throw new \RuntimeException('Nomor surat roster sedang diproses. Silakan coba beberapa detik lagi.');
        }

        return true;
    }

    private function releaseRosterNumberLock(int $year): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        try {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$this->rosterNumberLockName($year)]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function rosterNumberLockName(int $year): string
    {
        return 'cuti_roster_nomor_surat_' . $year;
    }

    private function weeklyRosterPayload($request): array
    {
        $weeks = [
            1 => ['status' => 'satu', 'date' => 'tanggal_satu'],
            2 => ['status' => 'dua', 'date' => 'tanggal_dua'],
            3 => ['status' => 'tiga', 'date' => 'tanggal_tiga'],
            4 => ['status' => 'empat', 'date' => 'tanggal_empat'],
            5 => ['status' => 'lima', 'date' => 'tanggal_lima'],
        ];

        $payload = [];

        foreach ($weeks as $number => $fields) {
            $payload[$fields['status']] = $request->input(
                $fields['status'],
                $request->input('hari_' . $number)
            );
            $payload[$fields['date']] = $request->input(
                $fields['date'],
                $request->input('tanggal_' . $number)
            );
        }

        return $payload;
    }

    private function applyApprovedOffToWeeklyStatus(string $nikKaryawan, ?string $periodeAwal, ?string $periodeAkhir, array $weeklyRoster): array
    {
        if (!$nikKaryawan || !$periodeAwal || !$periodeAkhir) {
            return $weeklyRoster;
        }

        $start = Carbon::parse($periodeAwal)->toDateString();
        $end = Carbon::parse($periodeAkhir)->toDateString();

        $offDateValues = RosterOffRequest::query()
            ->effectiveForAttendance()
            ->where('nik_karyawan', $nikKaryawan)
            ->whereBetween('tanggal_off', [$start, $end])
            ->pluck('tanggal_off')
            ->map(fn($date) => Carbon::parse($date)->toDateString())
            ->values();
        $offDates = $offDateValues->flip();

        foreach (['satu', 'dua', 'tiga', 'empat', 'lima'] as $field) {
            $dateField = 'tanggal_' . $field;
            $date = $weeklyRoster[$dateField] ?? null;

            if ($date && $offDates->has(Carbon::parse($date)->toDateString())) {
                $weeklyRoster[$field] = 'OFF';
            }
        }

        $existingDates = collect(['satu', 'dua', 'tiga', 'empat', 'lima'])
            ->map(fn($field) => $weeklyRoster['tanggal_' . $field] ?? null)
            ->filter()
            ->map(fn($date) => Carbon::parse($date)->toDateString())
            ->flip();

        foreach ($offDateValues as $offDate) {
            if ($existingDates->has($offDate)) {
                continue;
            }

            foreach (['satu', 'dua', 'tiga', 'empat', 'lima'] as $field) {
                $dateField = 'tanggal_' . $field;

                if (blank($weeklyRoster[$dateField] ?? null)) {
                    $weeklyRoster[$dateField] = $offDate;
                    $weeklyRoster[$field] = 'OFF';
                    $existingDates->put($offDate, true);
                    break;
                }
            }
        }

        return $weeklyRoster;
    }

    private function findUserRoster($id): Roster
    {
        return Roster::with('periodeKerjaRoster')
            ->where('nik_karyawan', Auth::user()->nik_karyawan)
            ->findOrFail($id);
    }

    private function canManageRoster(Roster $roster): bool
    {
        return (int) $roster->status_pengajuan === 0
            && (int) $roster->status_pengajuan_hrd === 0
            && ($roster->delegate_status === null || (int) $roster->delegate_status === 0);
    }

    private function serveRosterAttachment(Roster $roster)
    {
        abort_if(blank($roster->file), 404, 'Lampiran roster belum tersedia.');

        $filename = basename($roster->file);
        $absolutePath = app(SensitiveFileStorageService::class)->resolvePath(
            'cuti-roster/' . $roster->nik_karyawan . '/' . $filename,
            ['cuti-roster/']
        );

        abort_unless($absolutePath && File::isFile($absolutePath), 404, 'Lampiran roster tidak ditemukan.');

        return response()->file($absolutePath, [
            'Content-Type' => File::mimeType($absolutePath) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
