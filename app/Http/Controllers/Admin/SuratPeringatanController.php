<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\ImportSuratPeringatan;
use App\Jobs\DeleteImportedFile;
use App\Models\ImportHistory;
use App\Models\SuratPeringatan;
use App\Services\ImportHistory\ImportHistoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

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
        $suratPeringatan = $this->editableReport($id);

        return view('admin.surat-peringatan.edit', [
            'suratPeringatan' => $suratPeringatan
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'tgl_mulai' => 'required|date_format:Y-m-d',
            'tgl_berakhir' => 'required|date_format:Y-m-d|after_or_equal:tgl_mulai',
            'level_sp' => 'required|in:SP1,SP2,SP3', 'keterangan' => 'required|string|max:4000',
        ]);
        $this->editableReport($id)->update([
            'tgl_mulai' => $request->tgl_mulai,
            'tgl_berakhir' => $request->tgl_berakhir,
            'level_sp' => $request->level_sp,
            'keterangan' => $request->keterangan,
            'updated_at' => now()
        ]);

        toast()->success('Success', 'Data surat peringatan updated succesfully');
        return redirect()->route('surat-peringatan.index');
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasRole(['Super Admin', 'HR']), 403);
        $request->validate([
            'file' => 'required|mimes:xlsx,csv'
        ]);

        $uploadedFile = $request->file('file');
        $history = null;

        try {
            $filePath = $uploadedFile->store('imports');
            $history = app(ImportHistoryService::class)->createQueued([
                'import_type' => ImportHistory::TYPE_SURAT_PERINGATAN,
                'module' => 'surat_peringatan',
                'source' => ImportHistory::SOURCE_EXCEL,
                'file_name' => $uploadedFile->getClientOriginalName(),
                'file_path' => $filePath,
                'disk' => config('filesystems.default'),
                'mime_type' => $uploadedFile->getClientMimeType(),
                'file_size' => $uploadedFile->getSize(),
                'created_by' => (string) $request->user()->id,
            ]);

            Excel::queueImport(new ImportSuratPeringatan(optional($history)->id), storage_path('app/' . $filePath))->chain([
                new DeleteImportedFile($filePath)
            ]);
        } catch (Throwable $exception) {
            app(ImportHistoryService::class)->markFailed(optional($history)->id, $exception);
            report($exception);

            toast()->error('Error', 'File import pelanggaran gagal dijadwalkan. Silakan unggah ulang file yang valid.');
            return back();
        }

        toast()->success('Success', 'Import is in progress...');
        return back();
    }

    public function destroy($id)
    {
        DB::transaction(function () use ($id) {
            app(\App\Services\SuratPeringatan\WarningLetterNumberService::class)->lock();
            $this->editableReport($id)->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Data pelanggaran berhasil dihapus.',
        ]);
    }

    private function editableReport($id): SuratPeringatan
    {
        $query = SuratPeringatan::with('employee');
        if (!auth()->user()->canAccessAllEmployees()) {
            $query->whereHas('employee', function ($employees) { auth()->user()->applyEmployeeScope($employees); });
        }
        $report = $query->findOrFail($id);
        abort_if($report->issuance()->exists(), 409, 'SP yang sudah diterbitkan tidak dapat diubah atau dihapus.');

        return $report;
    }
}
