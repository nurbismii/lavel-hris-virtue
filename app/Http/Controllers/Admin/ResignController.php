<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\ImportResign;
use App\Jobs\DeleteImportedFile;
use App\Models\Employee;
use App\Models\Resign;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ResignController extends Controller
{
    public function index(Request $request)
    {
        $title = 'Delete Data!';
        $text = "Are you sure you want to delete?";
        confirmDelete($title, $text);

        if ($request->ajax()) {

            $resignService = app()->make(\App\Services\Resign\ResignService::class);
            return $resignService->getDataResign($request);
        }

        return view('admin.resign.index');
    }

    public function edit($id)
    {
        $resign = Resign::with('employee')->where('id', $id)->first();

        return view('admin.resign.edit', [
            'resign' => $resign
        ]);
    }

    public function update(Request $request, $id)
    {
        Resign::where('id', $id)->update([
            'tanggal_keluar' => $request->tanggal_keluar,
            'tipe' => $request->tipe
        ]);

        toast()->success('Success', 'Data resign updated succesfully');
        return redirect()->route('resign.index');
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv'
        ]);

        $filePath = $request->file('file')->store('imports');

        Excel::queueImport(new ImportResign, storage_path('app/' . $filePath))->chain([
            new DeleteImportedFile($filePath)
        ]);

        toast()->success('Success', 'Import is in progress.');

        return back();
    }

    public function destroy($id)
    {
        Resign::where('id', $id)->delete();

        return response()->json([
            'success' => true
        ]);
    }

    public function search(Request $request)
    {
        $q = trim($request->get('q'));

        $resign = Employee::with(
            'departemen',
            'divisi',
            'provinsi',
            'kabupaten',
            'kecamatan',
            'kelurahan'
        )
            ->where('status_resign', '!=', 'AKTIF')
            ->whereIn('area_kerja', ['VDNI', 'VDNIP'])

            // FILTER hanya jika ada keyword
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('nik', 'LIKE', "%{$q}%")
                        ->orWhere('nama_karyawan', 'LIKE', "%{$q}%");
                });
            })

            ->select(
                'nik',
                'nama_karyawan',
                'departemen_id',
                'divisi_id',
                'posisi',
                'provinsi_id',
                'kabupaten_id',
                'kecamatan_id',
                'kelurahan_id',
                'area_kerja'
            )
            ->orderBy('nik', 'desc')
            ->paginate(12)
            ->withQueryString();

        return view('search.index', compact('resign'));
    }
}
