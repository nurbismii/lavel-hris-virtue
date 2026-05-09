<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\ImportResign;
use App\Jobs\DeleteImportedFile;
use App\Models\Employee;
use App\Models\ImportHistory;
use App\Models\Resign;
use App\Services\ImportHistory\ImportHistoryService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

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

        $uploadedFile = $request->file('file');
        $history = null;

        try {
            $filePath = $uploadedFile->store('imports');
            $history = app(ImportHistoryService::class)->createQueued([
                'import_type' => ImportHistory::TYPE_RESIGN,
                'module' => 'resign',
                'source' => ImportHistory::SOURCE_EXCEL,
                'file_name' => $uploadedFile->getClientOriginalName(),
                'file_path' => $filePath,
                'disk' => config('filesystems.default'),
                'mime_type' => $uploadedFile->getClientMimeType(),
                'file_size' => $uploadedFile->getSize(),
                'created_by' => (string) $request->user()->id,
            ]);

            Excel::queueImport(new ImportResign(optional($history)->id), storage_path('app/' . $filePath))->chain([
                new DeleteImportedFile($filePath)
            ]);
        } catch (Throwable $exception) {
            app(ImportHistoryService::class)->markFailed(optional($history)->id, $exception);
            report($exception);

            toast()->error('Error', 'File import resign gagal dijadwalkan. Silakan unggah ulang file yang valid.');
            return back();
        }

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
}
