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

            return $karyawanService->getDataKaryawan($request);
        }

        return view('admin.karyawan.index', [
            'departemens' => Departemen::with('perusahaan')->orderBy('departemen')->get(),
            'divisis' => Divisi::orderBy('nama_divisi')->get(),
            'areas' => Perusahaan::select('*')->get()
        ]);

        return view('admin.karyawan.index');
    }

    public function edit($nik)
    {
        $employee = Employee::where('nik', $nik)
            ->with(['departemen', 'divisi'])
            ->firstOrFail();

        return view('admin.karyawan.edit', [
            'employee' => $employee,
            'departemens' => Departemen::with('perusahaan')->orderBy('departemen')->get(),
            'divisis' => Divisi::orderBy('nama_divisi')->get(),
            'areas' => Perusahaan::select('*')->get()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx'
        ]);

        try {
            $filePath = $request->file('file')->store('imports');

            Excel::queueImport(new ImportEmployee, storage_path('app/' . $filePath))->chain([
                new DeleteImportedFile($filePath)
            ]);

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
        $employee = Employee::where('nik', $nik)->firstOrFail();

        $validatedData = $request->validated();

        $employee->update($validatedData);

        toast('Data karyawan berhasil diperbarui!', 'success');
        return redirect()->route('karyawan.index');
    }

    public function destroy($nik)
    {
        $employee = Employee::where('nik', $nik)->firstOrFail();
        $employee->delete();

        toast('Data karyawan berhasil dihapus!', 'success');
        return redirect()->route('karyawan.index');
    }

    public function departemenByArea(Request $request)
    {
        return Departemen::whereHas('employee', function ($q) use ($request) {
            $q->where('area_kerja', $request->area);
        })
            ->orderBy('departemen')
            ->get(['id', 'departemen']);
    }

    /**
     * DEPARTEMEN → DIVISI
     */
    public function divisiByDepartemen(Request $request)
    {
        return Divisi::where('departemen_id', $request->departemen)
            ->orderBy('nama_divisi')
            ->get(['id', 'nama_divisi']);
    }
}
