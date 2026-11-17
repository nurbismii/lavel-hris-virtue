<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Severance\SeveranceActionRequest;
use App\Http\Requests\Severance\UploadSeveranceRequest;
use App\Models\Employee;
use App\Models\ImportHistory;
use App\Models\SeveranceRequest;
use App\Services\Severance\SeveranceExport;
use App\Services\Severance\SeveranceSalarySource;
use App\Services\Severance\SeveranceService;
use App\Services\Storage\SensitiveFileStorageService;
use App\Support\SafeExceptionLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class SeveranceController extends Controller
{
    public function index(Request $request)
    {
        $actor = $request->user();
        $ready = Schema::hasTable('severance_requests');
        $batches = $ready ? ImportHistory::where('module', 'severance')->when(!$actor->hasRole('Super Admin'), function ($query) use ($actor) {
            $query->where(function ($visible) use ($actor) {
                $visible->where('created_by', (string) $actor->id)->orWhereIn('id', SeveranceRequest::visibleTo($actor)->select('import_history_id'));
            });
        })->latest('id')->paginate(15)->withQueryString() : null;
        $exports = $ready ? ImportHistory::where('module', 'severance_export')
            ->when(!$actor->hasRole('Super Admin'), function ($query) use ($actor) { $query->where('created_by', (string) $actor->id); })
            ->latest('id')->paginate(10, ['*'], 'exports_page')->withQueryString() : null;
        return response()->view('admin.severance.index', compact('ready', 'batches', 'exports'))->header('Cache-Control', 'private, no-store');
    }

    public function store(UploadSeveranceRequest $request, SeveranceService $service)
    {
        return $this->action(function () use ($request, $service) {
            $batch = $service->upload($request->file('file'), $request->safe()->except('file'), $request->user());
            return $this->success('File diterima dan masuk antrean. Tinjau draf setelah impor selesai.', ['redirect_url' => route('severance.batch', $batch)], 202);
        });
    }

    public function batch(Request $request, ImportHistory $history, SeveranceService $service)
    {
        $service->authorizeBatch($request->user(), $history);
        $filters = $request->validate(['status' => ['nullable', Rule::in(array_keys(SeveranceRequest::statuses()))]]);
        $query = SeveranceRequest::visibleTo($request->user())->where('import_history_id', $history->id);
        return response()->view('admin.severance.batch', [
            'history' => $history,
            'canManage' => $service->canManage($request->user(), $history->created_by),
            'counts' => (clone $query)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status'),
            'entries' => (clone $query)->when($filters['status'] ?? null, function ($query, $status) {
                $query->where('status', $status);
            })
                ->orderBy('row_number')->paginate(25)->withQueryString(),
            'failures' => $history->items()->where('category', 'failed')->orderBy('row_number')->paginate(25, ['*'], 'failures_page')->withQueryString(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, SeveranceRequest $entry, SeveranceService $service, SeveranceSalarySource $source)
    {
        $service->authorizeEntry($request->user(), $entry);
        $employee = Employee::withTrashed()->with(['departemen:id,departemen', 'divisi:id,nama_divisi'])
            ->where('nik', $entry->employee_nik)
            ->first(['nik', 'area_kerja', 'departemen_id', 'divisi_id', 'posisi', 'jabatan', 'status_karyawan', 'status_resign']);
        $tenure = $entry->entry_date->diff($entry->termination_date->copy()->addDay());
        $salary = $entry->salary_snapshot;
        $sourceError = null;
        $canManage = $service->canManage($request->user(), $entry->created_by);
        $canEdit = $canManage && $entry->status === 'draft';
        $canPayroll = $entry->status === 'payroll_review' && $entry->canApprovePayroll($request->user());
        $canHi = $entry->status === 'hi_review' && $entry->canApproveHi($request->user());
        if ($canPayroll) {
            try {
                $salary = $source->resolve($entry);
            } catch (ValidationException $exception) {
                $sourceError = collect($exception->errors())->flatten()->first();
            }
        }
        return response()->view('admin.severance.show', compact('entry', 'employee', 'tenure', 'salary', 'sourceError', 'canPayroll', 'canHi', 'canManage', 'canEdit'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function update(SeveranceActionRequest $request, SeveranceRequest $entry, SeveranceService $service)
    {
        return $this->action(function () use ($request, $entry, $service) {
            $service->update($entry, $request->user(), $request->validated());
            return $this->success('Draf berhasil disimpan.');
        });
    }

    public function submit(Request $request, ImportHistory $history, SeveranceService $service)
    {
        return $this->action(function () use ($request, $history, $service) {
            $count = $service->submitBatch($history, $request->user());
            return $this->success($count ? $count . ' pengajuan dikirim ke Supervisor Comben.' : 'Seluruh pengajuan sudah dikirim.');
        });
    }

    public function payroll(SeveranceActionRequest $request, SeveranceRequest $entry, SeveranceService $service)
    {
        return $this->action(function () use ($request, $entry, $service) {
            $service->approvePayroll($entry, $request->user(), $request->validated());
            return $this->success('Upah FULL disetujui dan terisi otomatis. Pengajuan diteruskan ke Supervisor HI.');
        });
    }

    public function hi(SeveranceActionRequest $request, SeveranceRequest $entry, SeveranceService $service)
    {
        return $this->action(function () use ($request, $entry, $service) {
            $data = $service->hi($entry, $request->user(), $request->validated());
            if ($request->input('intent') === 'preview') {
                $data['calculation'] = app(\App\Services\Severance\SeveranceResultFormatter::class)->format($data['calculation']);
            }
            return $this->success(
                $request->input('intent') === 'preview' ? 'Pratinjau siap diperiksa.' : 'Disetujui final. Staf HI dapat melihat, mencetak, dan mengexport hasil.',
                $request->input('intent') === 'preview' ? $data + ['preview' => true] : []
            );
        });
    }

    public function reject(SeveranceActionRequest $request, SeveranceRequest $entry, SeveranceService $service)
    {
        return $this->action(function () use ($request, $entry, $service) {
            $service->reject($entry, $request->user(), $request->validated());
            return $this->success('Dikembalikan ke staf HI. Setelah revisi, persetujuan Payroll dan HI harus diulang.');
        });
    }

    public function status(Request $request, ImportHistory $history, SeveranceService $service)
    {
        if ($history->module === 'severance_export') {
            $this->authorizeExport($request, $history);
        } else {
            $service->authorizeBatch($request->user(), $history);
        }
        return $this->success('Status proses.', [
            'status' => $history->status,
            'status_label' => $history->status_label,
            'running' => in_array($history->status, ['queued', 'processing'], true),
            'total' => $history->total_rows,
            'success' => $history->success_count,
            'failed' => $history->failed_count,
            'error_message' => $history->error_message
        ]);
    }

    public function retry(Request $request, ImportHistory $history, SeveranceService $service)
    {
        return $this->action(function () use ($request, $history, $service) {
            $service->retry($history, $request->user());
            return $this->success('Proses masuk antrean ulang. Pantau statusnya pada halaman ini.');
        });
    }

    public function export(Request $request, ImportHistory $history, SeveranceExport $export)
    {
        $data = $request->validate($this->selectionRules());
        return $this->action(function () use ($request, $history, $export, $data) {
            $process = $export->enqueue($history, $request->user(), $data['ids'] ?? [], !empty($data['all']));
            return $this->success(
                $process->status === 'completed' ? 'File export sudah tersedia di Riwayat Export.' : 'Export masuk antrean. Pantau Riwayat Export untuk mengunduh hasil.',
                ['redirect_url' => route('severance.index') . '#exports'],
                202
            );
        });
    }

    public function download(Request $request, ImportHistory $history, SensitiveFileStorageService $storage)
    {
        $this->authorizeExport($request, $history);
        abort_unless($history->status === 'completed', 409, 'File export belum tersedia.');
        $path = $storage->resolvePrivatePath((string) $history->file_path, ['severance/']);
        abort_unless($path, 404, 'File tidak tersedia. Hubungi admin.');
        return response()->download($path, $history->file_name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function print(Request $request, ImportHistory $history, SeveranceExport $export)
    {
        $data = $request->validate($this->selectionRules());
        $query = $export->selection($history, $request->user(), $data['ids'] ?? [], !empty($data['all']));
        abort_if($query->count() > 50, 422, 'Maksimal 50 pengajuan sekali cetak. Pilih sebagian atau gunakan export Excel.');
        return response()->view('admin.severance.print', ['entries' => $query->get(), 'history' => $history])->header('Cache-Control', 'private, no-store');
    }

    public function template(Request $request, SeveranceExport $export)
    {
        abort_unless($request->user()->hasMenuAccess('severance_staff'), 403);
        return response()->streamDownload(function () use ($export) {
            $book = $export->template();
            try {
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, 'template-pengajuan-pesangon.xlsx', [
            'Cache-Control' => 'private, no-store',
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ]);
    }

    private function selectionRules(): array
    {
        return [
            'ids' => ['nullable', 'array', 'max:' . config('severance.max_export_rows')],
            'ids.*' => ['integer', 'min:1', 'distinct'],
            'all' => ['nullable', 'boolean']
        ];
    }

    private function authorizeExport(Request $request, ImportHistory $history): void
    {
        abort_unless($history->module === 'severance_export', 404);
        abort_unless(app(SeveranceService::class)->canManage($request->user(), $history->created_by), 403);
    }

    private function success(string $message, array $data = [], int $status = 200)
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status)->header('Cache-Control', 'private, no-store');
    }

    private function action(callable $callback)
    {
        try {
            return $callback();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            if ($exception instanceof HttpExceptionInterface) {
                throw $exception;
            }
            app(SafeExceptionLogger::class)->warning('severance.action', $exception);
            return response()->json(['success' => false, 'message' => 'Permintaan belum berhasil. Periksa status pengajuan sebelum mencoba ulang atau hubungi admin.'], 500);
        }
    }
}
