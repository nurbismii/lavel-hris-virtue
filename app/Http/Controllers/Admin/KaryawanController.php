<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\KaryawanRequest\UpdateKaryawanRequest;
use App\Imports\ImportEmployee;
use App\Jobs\DeleteImportedFile;
use App\Models\Departemen;
use App\Models\Divisi;
use App\Models\Employee;
use App\Models\Perusahaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
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

        $validatedData = $request->safe()->except('face_reference');

        if ($request->hasFile('face_reference')) {
            $faceDirectory = public_path('face-reference/' . $employee->nik);

            if (!File::exists($faceDirectory)) {
                File::makeDirectory($faceDirectory, 0755, true);
            }

            if (!empty($employee->face_reference_path)) {
                $oldFacePath = public_path($employee->face_reference_path);

                if (File::exists($oldFacePath)) {
                    File::delete($oldFacePath);
                }
            }

            $file = $request->file('face_reference');
            $filename = 'reference_' . now()->format('YmdHis') . '.' . $file->getClientOriginalExtension();
            $file->move($faceDirectory, $filename);

            $validatedData['face_reference_path'] = 'face-reference/' . $employee->nik . '/' . $filename;
        }

        $employee->update($validatedData);

        toast('Data karyawan berhasil diperbarui!', 'success');
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
