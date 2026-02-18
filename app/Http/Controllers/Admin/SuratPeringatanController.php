<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\ImportResign;
use App\Imports\ImportSuratPeringatan;
use App\Jobs\DeleteImportedFile;
use App\Models\Resign;
use App\Models\SuratPeringatan;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class SuratPeringatanController extends Controller
{
    public function index(Request $request)
    {
        $title = 'Delete Data!';
        $text = "Are you sure you want to delete?";
        confirmDelete($title, $text);

        if ($request->ajax()) {

            $suratPeringatanService = app()->make(\App\Services\SuratPeringatan\SuratPeringatanService::class);
            return $suratPeringatanService->getDataSuratPeringatan($request);
        }

        return view('admin.surat-peringatan.index');
    }

    public function edit($id)
    {
        $suratPeringatan = SuratPeringatan::with('employee')->where('id', $id)->first();

        return view('admin.surat-peringatan.edit', [
            'suratPeringatan' => $suratPeringatan
        ]);
    }

    public function update(Request $request, $id)
    {
        SuratPeringatan::where('id', $id)->update([
            'tgl_mulai' => $request->tgl_mulai,
            'tgl_berakhir' => $request->tgl_berakhir,
            'level_sp' => $request->level_sp,
            'keterangan' => $request->keterangan,
            'updated_at' => now()
        ]);

        toast()->success('Success', 'Data surat peringatan updated succesfully');
        return redirect()->route('resign.index');
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv'
        ]);

        $filePath = $request->file('file')->store('imports');

        Excel::queueImport(new ImportSuratPeringatan, storage_path('app/' . $filePath))->chain([
            new DeleteImportedFile($filePath)
        ]);

        toast()->success('Success', 'Import is in progress...');
        return back();
    }

    public function destroy($id)
    {
        SuratPeringatan::where('id', $id)->delete();

        return response()->json([
            'success' => true
        ]);
    }
}
