<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ImportHistoryItemsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Salary\UploadSalaryRequest;
use App\Jobs\ProcessSalaryImport;
use App\Models\EmployeeSalaryHistory;
use App\Models\ImportHistory;
use App\Models\ImportHistoryItem;
use App\Services\Audit\AuditTrailService;
use App\Services\Salary\SalaryWorkbookReader;
use App\Services\Storage\SensitiveFileStorageService;
use App\Support\SafeExceptionLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

class EmployeeSalaryController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeActor($request);
        $filters = $request->validate(['nik' => ['nullable', 'string', 'max:100']]);

        return response()->view('admin.employee-salaries.index', [
            'salaries' => EmployeeSalaryHistory::query()->with('employee:nik,nama_karyawan')
                ->when($filters['nik'] ?? null, function ($query, $nik) { $query->where('employee_nik', $nik); })
                ->orderByDesc('effective_from')->orderByDesc('id')->paginate(25, ['*'], 'salary_page')->withQueryString(),
            'imports' => ImportHistory::where('import_type', ImportHistory::TYPE_EMPLOYEE_SALARY)
                ->latest('id')->paginate(10, ['*'], 'import_page')->withQueryString(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(UploadSalaryRequest $request, SensitiveFileStorageService $storage)
    {
        $this->ensurePersistentQueue();
        $history = null;
        $path = null;
        try {
            $file = $request->file('file');
            $checksum = hash_file('sha256', $file->getRealPath());
            $key = hash('sha256', 'salary:' . $checksum);
            $existing = ImportHistory::where('salary_upload_key', $key)->first();
            if ($existing && $existing->status === ImportHistory::STATUS_AWAITING_CONFIRMATION && $existing->expires_at->isPast()) {
                $existing->update(['status' => ImportHistory::STATUS_EXPIRED, 'salary_upload_key' => null]);
                $existing = null;
            }
            if (!$existing) {
                $id = (string) Str::uuid();
                $path = $storage->storeUploadedFileAs($file, 'salary-imports/' . $id, 'source.xlsx');
                $history = ImportHistory::firstOrCreate(['salary_upload_key' => $key], [
                    'import_id' => $id, 'import_type' => ImportHistory::TYPE_EMPLOYEE_SALARY,
                    'module' => 'employee_salary', 'source' => ImportHistory::SOURCE_EXCEL,
                    'status' => ImportHistory::STATUS_QUEUED, 'file_name' => 'pembaruan-gaji.xlsx',
                    'file_path' => $path, 'file_checksum' => $checksum, 'disk' => 'local',
                    'file_size' => $file->getSize(), 'created_by' => (string) $request->user()->id,
                    'summary' => ['phase' => 'preview'], 'expires_at' => now()->addDay(),
                ]);
                if ($history->wasRecentlyCreated) {
                    ProcessSalaryImport::dispatch($history->id);
                    $this->audit($request, $history, 'uploaded');
                } else {
                    $storage->deletePrivate($path, ['salary-imports/']);
                }
            } else {
                $history = $existing;
            }
        } catch (Throwable $exception) {
            app(SafeExceptionLogger::class)->warning('salary_import.upload', $exception);
            if ($history && $history->wasRecentlyCreated) {
                $history->update(['status' => ImportHistory::STATUS_FAILED, 'salary_upload_key' => null,
                    'error_message' => 'File gagal masuk antrean. Hubungi admin untuk memeriksa koneksi antrean, lalu unggah ulang.', 'finished_at' => now()]);
            }
            if ($path) {
                $storage->deletePrivate($path, ['salary-imports/']);
            }
            return response()->json(['success' => false, 'message' => 'File gagal diterima. Periksa layanan antrean melalui admin, lalu unggah ulang.'], 500);
        }

        return response()->json(['success' => true,
            'message' => 'File diterima. Periksa status validasi dan tinjau perubahan sebelum konfirmasi.',
            'data' => ['redirect_url' => route('employee-salaries.show', $history)]], 202);
    }

    public function show(Request $request, ImportHistory $history)
    {
        $this->authorizeHistory($request, $history);
        $this->expire($history);
        return response()->view('admin.employee-salaries.show', [
            'history' => $history,
            'items' => $history->items()->orderBy('row_number')->paginate(50)->withQueryString(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function status(Request $request, ImportHistory $history)
    {
        $this->authorizeHistory($request, $history);
        $this->expire($history);
        return response()->json(['success' => true, 'message' => $history->status_label, 'data' => [
            'status' => $history->status, 'status_label' => $history->status_label,
            'phase' => $history->summary['phase'] ?? 'preview',
            'total' => $history->total_rows, 'success' => $history->success_count, 'failed' => $history->failed_count,
            'error_message' => $history->error_message,
            'running' => in_array($history->status, [ImportHistory::STATUS_QUEUED, ImportHistory::STATUS_PROCESSING], true),
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function confirm(Request $request, ImportHistory $history)
    {
        $this->authorizeHistory($request, $history);
        $this->ensurePersistentQueue();
        $request->validate(['reviewed' => ['accepted']]);
        $confirmed = DB::transaction(function () use ($request, $history) {
            $locked = ImportHistory::whereKey($history->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== ImportHistory::STATUS_AWAITING_CONFIRMATION || !$locked->expires_at || $locked->expires_at->isPast()
                || $locked->failed_count > 0 || $locked->total_rows < 1) {
                return false;
            }
            $locked->update(['confirmed_by' => (string) $request->user()->id, 'confirmed_at' => now(),
                'status' => ImportHistory::STATUS_QUEUED, 'summary' => ['phase' => 'commit'], 'finished_at' => null]);
            return true;
        });
        if (!$confirmed) {
            return response()->json(['success' => false, 'message' => 'Import sudah dikonfirmasi, tidak valid, atau kedaluwarsa. Muat ulang halaman untuk melihat statusnya.'], 409);
        }
        try {
            ProcessSalaryImport::dispatch($history->id, 'commit');
            $this->audit($request, $history->fresh(), 'confirmed');
        } catch (Throwable $exception) {
            app(SafeExceptionLogger::class)->warning('salary_import.dispatch_commit', $exception);
            $history->update(['status' => ImportHistory::STATUS_FAILED, 'salary_upload_key' => null,
                'finished_at' => now(), 'error_message' => 'Konfirmasi gagal masuk antrean. Unggah ulang setelah layanan antrean tersedia.']);
            return response()->json(['success' => false, 'message' => 'Konfirmasi gagal masuk antrean. Hubungi admin dan unggah ulang file.'], 500);
        }
        return response()->json(['success' => true, 'message' => 'Pembaruan gaji masuk antrean. Pantau hasil penerapannya pada halaman ini.']);
    }

    public function template(Request $request)
    {
        $this->authorizeActor($request);
        return response()->streamDownload(function () {
            $book = new Spreadsheet();
            $sheet = $book->getActiveSheet();
            $sheet->setTitle('Pembaruan Gaji');
            $sheet->fromArray(SalaryWorkbookReader::HEADINGS, null, 'A1');
            $sheet->getStyle('A1:G1')->getFont()->setBold(true);
            $sheet->getStyle('A:A')->getNumberFormat()->setFormatCode('@');
            foreach (range('A', 'G') as $column) {
                $sheet->getColumnDimension($column)->setWidth($column === 'A' ? 22 : 26);
            }
            $sheet->freezePane('A2');
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, 'template-pembaruan-gaji.xlsx', ['Cache-Control' => 'private, no-store',
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function failures(Request $request, ImportHistory $history)
    {
        $this->authorizeHistory($request, $history);
        abort_unless($history->failed_count > 0, 404);
        return Excel::download(new ImportHistoryItemsExport($history, ImportHistoryItem::CATEGORY_FAILED),
            'baris-gaji-gagal-' . $history->id . '.xlsx', null, ['Cache-Control' => 'private, no-store']);
    }

    private function authorizeActor(Request $request): void
    {
        abort_unless($request->user() && $request->user()->hasRole(['Super Admin', 'HR'])
            && $request->user()->hasMenuAccess('slip_gaji_admin'), 403);
    }

    private function ensurePersistentQueue(): void
    {
        $connection = (string) config('salary.import.connection', 'database');
        if (in_array(config('queue.connections.' . $connection . '.driver'), [null, 'sync', 'null'], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'queue' => 'Antrean pembaruan gaji belum dikonfigurasi. Minta admin mengaktifkan koneksi database/Redis atau antrean persisten lain sebelum melanjutkan.',
            ]);
        }
    }

    private function authorizeHistory(Request $request, ImportHistory $history): void
    {
        $this->authorizeActor($request);
        abort_unless($history->import_type === ImportHistory::TYPE_EMPLOYEE_SALARY, 404);
    }

    private function expire(ImportHistory $history): void
    {
        if ($history->status === ImportHistory::STATUS_AWAITING_CONFIRMATION && $history->expires_at && $history->expires_at->isPast()) {
            $history->update(['status' => ImportHistory::STATUS_EXPIRED, 'salary_upload_key' => null]);
        }
    }

    private function audit(Request $request, ImportHistory $history, string $action): void
    {
        app(AuditTrailService::class)->record(['event' => 'employee_salary.' . $action, 'module' => 'employee_salary',
            'reference_table' => 'import_histories', 'reference_id' => (string) $history->id,
            'actor' => $request->user(), 'metadata' => ['import_id' => $history->import_id, 'total_rows' => $history->total_rows]]);
    }
}
