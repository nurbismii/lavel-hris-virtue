<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Izin\IzinRequest;
use App\Models\Cuti;
use App\Services\Notifications\ApprovalNotificationService;
use App\Services\Storage\SensitiveFileStorageService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class IzinController extends Controller
{
    public function index()
    {
        $title = 'Delete Data!';
        $text = "Are you sure you want to delete?";
        confirmDelete($title, $text);

        $data = Cuti::where('nik_karyawan', Auth::user()->nik_karyawan)
            ->whereIn('tipe', ['PAID', 'UNPAID'])
            ->latest('id')
            ->get();

        return view('user.izin.index', compact('data'));
    }

    public function create()
    {
        return view('user.izin.create');
    }

    public function show($id)
    {
        $this->findUserIzin($id);

        return redirect()->route('izin.index');
    }

    public function proof($id)
    {
        $izin = $this->findUserIzin($id);

        abort_if(blank($izin->foto) || $izin->foto === '-', 404, 'Bukti izin belum tersedia.');

        return $this->serveStoredIzinProof($izin->foto);
    }

    public function edit($id)
    {
        $izin = $this->findUserIzin($id);

        if (!$this->canManageIzin($izin)) {
            toast()->warning('Warning', 'Pengajuan izin yang sudah diproses tidak dapat diedit');
            return redirect()->route('izin.index');
        }

        return view('user.izin.edit', compact('izin'));
    }

    public function store(IzinRequest $request)
    {
        $STATUS_HOD = 0;
        $STATUS_HR = 0;
        $STATUS_PEMOHON = 1;

        $data = $request->validated();
        $tanggalMulai = Carbon::parse($data['tanggal_mulai']);
        $tanggalBerakhir = Carbon::parse($data['tanggal_berakhir']);

        $jumlahHari = $tanggalMulai->diffInDays($tanggalBerakhir) + 1;

        $fotoPath = '-';

        try {
            $fotoPath = $this->storeFotoIzin($request, Auth::user()->nik_karyawan);

            $izin = DB::transaction(function () use (
                $data,
                $tanggalMulai,
                $tanggalBerakhir,
                $jumlahHari,
                $fotoPath,
                $STATUS_PEMOHON,
                $STATUS_HOD,
                $STATUS_HR
            ) {
                return Cuti::create([
                    'nik_karyawan' => Auth::user()->nik_karyawan,
                    'tanggal' => now(),
                    'jumlah' => $jumlahHari,
                    'tanggal_mulai' => $tanggalMulai->toDateString(),
                    'tanggal_berakhir' => $tanggalBerakhir->toDateString(),
                    'keterangan' => $data['keterangan'] ?? null,
                    'tipe' => $data['tipe'],
                    'tipe_izin' => $this->resolveTipeIzin($data),
                    'status_pemohon' => $STATUS_PEMOHON, // 0 = Menunggu, 1 = Disetujui, 2 = Ditolak
                    'status_hod' => $STATUS_HOD, // 0 = Menunggu, 1 = Disetujui, 2 = Ditolak
                    'status_hrd' => $STATUS_HR, // 0 = Menunggu, 1 = Disetujui, 2 = Ditolak
                    'foto' => $fotoPath,
                ]);
            });
        } catch (\Throwable $exception) {
            $this->deleteFotoIzin($fotoPath);
            report($exception);

            toast()->error('Error', 'Pengajuan izin gagal disimpan. Periksa kembali data dan bukti izin, lalu coba lagi.');
            return back()->withInput();
        }

        app(ApprovalNotificationService::class)->notifyIzinSubmitted($izin->fresh(['employee']));

        toast()->success('Success', 'Pengajuan izin berhasil dikirim');
        return redirect()->route('izin.index');
    }

    public function update(IzinRequest $request, $id)
    {
        $izin = $this->findUserIzin($id);

        if (!$this->canManageIzin($izin)) {
            toast()->warning('Warning', 'Pengajuan izin yang sudah diproses tidak dapat diubah');
            return redirect()->route('izin.index');
        }

        $data = $request->validated();
        $tanggalMulai = Carbon::parse($data['tanggal_mulai']);
        $tanggalBerakhir = Carbon::parse($data['tanggal_berakhir']);
        $jumlahHari = $tanggalMulai->diffInDays($tanggalBerakhir) + 1;
        $oldFotoPath = $izin->foto;
        $hasNewFoto = $request->hasFile('foto');
        $fotoPath = $oldFotoPath ?: '-';

        try {
            $fotoPath = $this->storeFotoIzin($request, Auth::user()->nik_karyawan, $oldFotoPath);

            DB::transaction(function () use ($izin, $data, $tanggalMulai, $tanggalBerakhir, $jumlahHari, $fotoPath) {
                $izin->update([
                    'jumlah' => $jumlahHari,
                    'tanggal_mulai' => $tanggalMulai->toDateString(),
                    'tanggal_berakhir' => $tanggalBerakhir->toDateString(),
                    'keterangan' => $data['keterangan'] ?? null,
                    'tipe' => $data['tipe'],
                    'tipe_izin' => $this->resolveTipeIzin($data),
                    'foto' => $fotoPath,
                ]);
            });
        } catch (\Throwable $exception) {
            if ($hasNewFoto) {
                $this->deleteFotoIzin($fotoPath);
            }

            report($exception);

            toast()->error('Error', 'Pengajuan izin gagal diperbarui. Periksa kembali data dan bukti izin, lalu coba lagi.');
            return back()->withInput();
        }

        if ($hasNewFoto) {
            $this->deleteFotoIzin($oldFotoPath);
        }

        toast()->success('Success', 'Pengajuan izin berhasil diperbarui');
        return redirect()->route('izin.index');
    }

    public function destroy($id)
    {
        $izin = $this->findUserIzin($id);

        if (!$this->canManageIzin($izin)) {
            toast()->warning('Warning', 'Pengajuan izin yang sudah diproses tidak dapat dihapus');
            return redirect()->route('izin.index');
        }

        $fotoPath = $izin->foto;

        try {
            DB::transaction(function () use ($izin) {
                $izin->delete();
            });
        } catch (\Throwable $exception) {
            report($exception);

            toast()->error('Error', 'Pengajuan izin gagal dihapus. Silakan coba lagi.');
            return redirect()->route('izin.index');
        }

        $this->deleteFotoIzin($fotoPath);

        toast()->success('Success', 'Pengajuan izin berhasil dihapus');
        return redirect()->route('izin.index');
    }

    private function findUserIzin($id): Cuti
    {
        return Cuti::where('nik_karyawan', Auth::user()->nik_karyawan)
            ->whereIn('tipe', ['PAID', 'UNPAID'])
            ->findOrFail($id);
    }

    private function canManageIzin(Cuti $izin): bool
    {
        return (int) $izin->status_hod === 0 && (int) $izin->status_hrd === 0;
    }

    private function resolveTipeIzin(array $data): ?string
    {
        return ($data['tipe'] ?? null) === 'PAID' ? ($data['tipe_izin'] ?? null) : null;
    }

    private function storeFotoIzin(IzinRequest $request, string $nik, ?string $existingPath = null): string
    {
        if (!$request->hasFile('foto')) {
            return $existingPath ?: '-';
        }

        $file = $request->file('foto');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $filename = 'izin_' . now()->format('YmdHis') . '_' . Str::lower(Str::random(8)) . '.' . $extension;

        return app(SensitiveFileStorageService::class)->storeUploadedFileAs($file, 'izin/' . $nik, $filename);
    }

    private function deleteFotoIzin(?string $path): void
    {
        if (!$path || $path === '-') {
            return;
        }

        $normalizedPath = str_replace('\\', '/', ltrim($path, '/'));

        if (str_contains($normalizedPath, '..') || !Str::startsWith($normalizedPath, 'izin/')) {
            return;
        }

        app(SensitiveFileStorageService::class)->delete($normalizedPath, ['izin/']);
    }

    private function serveStoredIzinProof(string $path)
    {
        $normalizedPath = str_replace('\\', '/', ltrim($path, '/'));

        abort_if(str_contains($normalizedPath, '..') || !Str::startsWith($normalizedPath, 'izin/'), 404);

        $absolutePath = app(SensitiveFileStorageService::class)->resolvePath($normalizedPath, ['izin/']);

        abort_unless($absolutePath && File::isFile($absolutePath), 404, 'Bukti izin tidak ditemukan.');

        return response()->file($absolutePath, [
            'Content-Type' => File::mimeType($absolutePath) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . basename($normalizedPath) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
