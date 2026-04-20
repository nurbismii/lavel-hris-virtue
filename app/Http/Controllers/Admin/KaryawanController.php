<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\KaryawanRequest\UpdateKaryawanRequest;
use App\Imports\ImportEmployee;
use App\Jobs\ProcessEmployeeMediaZipUpload;
use App\Jobs\DeleteImportedFile;
use App\Models\Departemen;
use App\Models\Divisi;
use App\Models\Employee;
use App\Models\Perusahaan;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class KaryawanController extends Controller
{
    public function index(Request $request)
    {
        $title = 'Delete Data!';
        $text = "Are you sure you want to delete?";
        confirmDelete($title, $text);

        if ($request->ajax()) {

            $karyawanService = app()->make(\App\Services\Karyawan\KaryawanService::class);

            return $karyawanService->getDataKaryawan($request, $request->user());
        }

        $scopeQuery = $request->user()->applyEmployeeScope(Employee::query());
        $departemenIds = (clone $scopeQuery)->select('departemen_id')->distinct()->pluck('departemen_id')->filter();
        $divisiIds = (clone $scopeQuery)->select('divisi_id')->distinct()->pluck('divisi_id')->filter();
        $areaCodes = (clone $scopeQuery)->select('area_kerja')->distinct()->pluck('area_kerja')->filter();

        return view('admin.karyawan.index', [
            'departemens' => Departemen::with('perusahaan')->whereIn('id', $departemenIds)->orderBy('departemen')->get(),
            'divisis' => Divisi::whereIn('id', $divisiIds)->orderBy('nama_divisi')->get(),
            'areas' => Perusahaan::whereIn('kode_perusahaan', $areaCodes)->get(),
            'canManageMasterData' => $request->user()->canAccessAllEmployees(),
        ]);
    }

    public function edit($nik)
    {
        $employee = auth()->user()
            ->applyEmployeeScope(Employee::query())
            ->where('nik', $nik)
            ->with(['departemen', 'divisi'])
            ->firstOrFail();

        $scopeQuery = auth()->user()->applyEmployeeScope(Employee::query());
        $departemenIds = (clone $scopeQuery)->select('departemen_id')->distinct()->pluck('departemen_id')->filter();
        $divisiIds = (clone $scopeQuery)->select('divisi_id')->distinct()->pluck('divisi_id')->filter();
        $areaCodes = (clone $scopeQuery)->select('area_kerja')->distinct()->pluck('area_kerja')->filter();

        return view('admin.karyawan.edit', [
            'employee' => $employee,
            'departemens' => Departemen::with('perusahaan')->whereIn('id', $departemenIds)->orderBy('departemen')->get(),
            'divisis' => Divisi::whereIn('id', $divisiIds)->orderBy('nama_divisi')->get(),
            'areas' => Perusahaan::whereIn('kode_perusahaan', $areaCodes)->get()
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->canAccessAllEmployees(), 403, 'Akses tidak diizinkan.');

        $request->validate([
            'file' => 'required|mimes:xlsx'
        ]);

        try {
            $filePath = $request->file('file')->store('imports');

            Excel::queueImport(new ImportEmployee, storage_path('app/' . $filePath))->chain([
                new DeleteImportedFile($filePath)
            ]);

            toast()->success('Success', 'Your file is being processed in the background.');
            return back();
        } catch (\Throwable $e) {

            toast()->error('Error', 'File kamu rusak, buat file baru dan import ulang.');
            return back();
        }
    }

    public function update(UpdateKaryawanRequest $request, $nik)
    {
        $employee = $request->user()
            ->applyEmployeeScope(Employee::query())
            ->where('nik', $nik)
            ->firstOrFail();

        $validatedData = $request->safe()->except([
            'photo_file',
            'ktp_file',
            'kk_file',
            'sim_file',
            'sio_file',
            'face_reference',
        ]);
        $mediaService = app()->make(\App\Services\Karyawan\EmployeeMediaService::class);
        $fileInputs = [
            'photo_file' => 'photo',
            'ktp_file' => 'ktp',
            'kk_file' => 'kk',
            'sim_file' => 'sim',
            'sio_file' => 'sio',
            'face_reference' => 'face_reference',
        ];
        $replacedDocuments = 0;

        foreach ($fileInputs as $input => $type) {
            if (!$request->hasFile($input)) {
                continue;
            }

            $column = $mediaService->getColumnForType($type);
            $replacedExisting = false;
            $path = $mediaService->storeUploadedFile($employee, $request->file($input), $type, true, $replacedExisting);

            $validatedData[$column] = $path;
            $employee->{$column} = $path;
            $replacedDocuments += $replacedExisting ? 1 : 0;
        }

        $employee->update($validatedData);

        $message = $replacedDocuments > 0
            ? "Data karyawan berhasil diperbarui. {$replacedDocuments} file lama ditimpa dengan file baru."
            : 'Data karyawan berhasil diperbarui!';

        toast($message, 'success');
        return redirect()->route('karyawan.index');
    }

    public function bulkUploadDocuments(Request $request)
    {
        abort_unless($request->user()->canAccessAllEmployees(), 403, 'Akses tidak diizinkan.');

        $request->validate([
            'bulk_photo_zip' => 'nullable|file|mimes:zip|max:512000',
            'bulk_ktp_zip' => 'nullable|file|mimes:zip|max:512000',
            'bulk_kk_zip' => 'nullable|file|mimes:zip|max:512000',
            'bulk_sim_zip' => 'nullable|file|mimes:zip|max:512000',
            'bulk_sio_zip' => 'nullable|file|mimes:zip|max:512000',
        ]);

        $inputMap = [
            'bulk_photo_zip' => 'photo',
            'bulk_ktp_zip' => 'ktp',
            'bulk_kk_zip' => 'kk',
            'bulk_sim_zip' => 'sim',
            'bulk_sio_zip' => 'sio',
        ];
        $hasAnyUpload = collect(array_keys($inputMap))
            ->contains(fn($input) => $request->hasFile($input));

        if (!$hasAnyUpload) {
            return back()->withErrors([
                'bulk_documents_zip' => 'Pilih minimal satu file ZIP dokumen untuk diproses.',
            ]);
        }
        $queuedCount = 0;

        foreach ($inputMap as $input => $type) {
            if (!$request->hasFile($input)) {
                continue;
            }

            $filePath = $request->file($input)->store(
                'employee-zip-imports',
                config('filesystems.employee_import_disk', config('filesystems.default'))
            );
            ProcessEmployeeMediaZipUpload::dispatch($filePath, $type, $request->user()->id);
            $queuedCount++;
        }

        toast()->success('Success', "{$queuedCount} file ZIP dokumen sedang diproses di background. Cek notifikasi untuk hasil akhirnya.");
        return redirect()->route('karyawan.index');
    }

    public function destroy($nik)
    {
        abort_unless(auth()->user()->canAccessAllEmployees(), 403, 'Akses tidak diizinkan.');

        $employee = auth()->user()
            ->applyEmployeeScope(Employee::query())
            ->where('nik', $nik)
            ->firstOrFail();
        $employee->delete();

        toast('Data karyawan berhasil dihapus!', 'success');
        return redirect()->route('karyawan.index');
    }

    public function departemenByArea(Request $request)
    {
        $query = Departemen::whereHas('employee', function ($q) use ($request) {
            $q->where('area_kerja', $request->area);
            $request->user()->applyEmployeeScope($q);
        });

        return $query
            ->orderBy('departemen')
            ->get(['id', 'departemen']);
    }

    /**
     * DEPARTEMEN → DIVISI
     */
    public function divisiByDepartemen(Request $request)
    {
        $query = Divisi::where('departemen_id', $request->departemen);

        if ($request->user()->isDivisionScopedRole()) {
            $query->whereIn('id', $request->user()->scopedDivisionIds());
        }

        return $query
            ->orderBy('nama_divisi')
            ->get(['id', 'nama_divisi']);
    }
}
