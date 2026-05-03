<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Roster\RosterRequest;
use App\Models\PeriodeKerjaRoster;
use App\Models\Roster;
use App\Models\RosterOffRequest;
use App\Services\Notifications\ApprovalNotificationService;
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
        return view('user.roster.create');
    }

    public function store(RosterRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();

        $statusPengajuanHod = 0;
        $statusPengajuanHrd = 0;
        $nikKaryawan = Auth::user()->nik_karyawan;

        try {

            $bulan = bulan_romawi(now()->format('m'));
            $tahun = now()->format('Y');
            $jumlah = Roster::whereYear('created_at', now()->year)->count();
            $jml_cuti = no_urut_surat($jumlah + 1);

            $nomor_surat = '02-' . $jml_cuti . '/BR/HRD-VDNI/' . $bulan . '/' . $tahun;

            $file_name = null;

            if ($request->hasFile('berkas_cuti')) {

                $upload = $request->file('berkas_cuti');
                $file_name = 'roster_' . now()->format('YmdHis') . '_' . Str::lower(Str::random(8)) . '.' . strtolower($upload->getClientOriginalExtension());

                $path = public_path('cuti-roster/' . $nikKaryawan);

                if (!File::exists($path)) {
                    File::makeDirectory($path, 0755, true);
                }

                $upload->move($path, $file_name);
            }

            $roster = Roster::create([
                'nomor_surat' => $nomor_surat,
                'nik_karyawan' => $nikKaryawan,
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
            ]);

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

            app(ApprovalNotificationService::class)->notifyRosterSubmitted($roster->fresh(['employee', 'periodeKerjaRoster']));

            toast()->success('Success', 'Cuti Roster created successfully');
            return back();
        } catch (\Throwable $e) {

            DB::rollBack();

            toast()->error('Error', 'Something wrong' .  $e->getMessage());
            return back();
        }
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

        DB::beginTransaction();
        $nikKaryawan = Auth::user()->nik_karyawan;

        try {

            $file_name = $roster->file; // default pakai file lama

            if ($request->hasFile('berkas_cuti')) {

                $upload = $request->file('berkas_cuti');
                $file_name = 'roster_' . now()->format('YmdHis') . '_' . Str::lower(Str::random(8)) . '.' . strtolower($upload->getClientOriginalExtension());

                $path = public_path('cuti-roster/' . $nikKaryawan);

                if (!File::exists($path)) {
                    File::makeDirectory($path, 0755, true);
                }

                // Hapus file lama jika ada
                if ($roster->file && File::isFile($path . DIRECTORY_SEPARATOR . $roster->file)) {
                    File::delete($path . DIRECTORY_SEPARATOR . $roster->file);
                }

                $upload->move($path, $file_name);
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

            toast()->success('Success', 'Cuti Roster updated successfully');
            return redirect()->route('roster.index');
        } catch (\Throwable $e) {

            DB::rollBack();

            toast()->error('Error', 'Something wrong : ' . $e->getMessage());
            return back();
        }
    }

    public function destroy($id)
    {
        $roster = $this->findUserRoster($id);

        if (!$this->canManageRoster($roster)) {
            toast()->warning('Peringatan', 'Pengajuan roster yang sudah diproses tidak dapat dihapus');
            return redirect()->route('roster.index');
        }

        if ($roster->file) {
            $file_path = public_path('cuti-roster/' . $roster->nik_karyawan . '/' . $roster->file);
            if (File::isFile($file_path)) {
                File::delete($file_path);
            }
        }

        $roster->periodeKerjaRoster()->delete();
        $roster->delete();
        toast()->success('Success', 'Pengajuan roster berhasil dihapus');
        return redirect()->route('roster.index');
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
        return (int) $roster->status_pengajuan === 0 && (int) $roster->status_pengajuan_hrd === 0;
    }

    private function serveRosterAttachment(Roster $roster)
    {
        abort_if(blank($roster->file), 404, 'Lampiran roster belum tersedia.');

        $filename = basename($roster->file);
        $absolutePath = public_path('cuti-roster/' . $roster->nik_karyawan . '/' . $filename);

        abort_unless(File::isFile($absolutePath), 404, 'Lampiran roster tidak ditemukan.');

        return response()->file($absolutePath, [
            'Content-Type' => File::mimeType($absolutePath) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
