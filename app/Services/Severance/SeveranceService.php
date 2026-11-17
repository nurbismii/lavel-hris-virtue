<?php

namespace App\Services\Severance;

use App\Jobs\ProcessSeveranceFile;
use App\Models\Employee;
use App\Models\ImportHistory;
use App\Models\SeveranceRequest;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use App\Services\Storage\SensitiveFileStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class SeveranceService
{
    public function canManage(User $actor, $createdBy): bool
    {
        return $actor->hasMenuAccess('severance_staff')
            && ($actor->hasRole('Super Admin') || (string) $createdBy === (string) $actor->id);
    }

    public function authorizeBatch(User $actor, ImportHistory $batch): void
    {
        abort_unless($batch->module === 'severance', 404);
        abort_unless($actor->hasMenuAccess(['severance_staff', 'severance_payroll', 'severance_hi']), 403);
        abort_unless($actor->hasRole('Super Admin') || (string) $batch->created_by === (string) $actor->id
            || SeveranceRequest::visibleTo($actor)->where('import_history_id', $batch->id)->exists(), 403);
    }

    public function authorizeEntry(User $actor, SeveranceRequest $entry): void
    {
        abort_unless($actor->hasMenuAccess(['severance_staff', 'severance_payroll', 'severance_hi'])
            && SeveranceRequest::visibleTo($actor)->whereKey($entry->id)->exists(), 403);
    }

    public function upload(UploadedFile $file, array $settings, User $actor): ImportHistory
    {
        abort_unless($actor->hasMenuAccess('severance_staff'), 403);
        $this->requireQueue();
        if (!Schema::hasTable('severance_requests')) {
            $this->invalid('Penyimpanan pesangon belum disiapkan. Minta admin menerapkan migrasi.');
        }
        $key = hash('sha256', 'import|' . $actor->id . '|' . hash_file('sha256', $file->getRealPath()) . '|' . json_encode($settings));
        if ($existing = ImportHistory::where('severance_process_key', $key)->first()) {
            return $existing;
        }
        $storage = app(SensitiveFileStorageService::class);
        $path = $storage->storeUploadedFileAs($file, 'severance/' . Str::uuid(), 'source.xlsx');
        $history = null;
        try {
            $history = ImportHistory::firstOrCreate(['severance_process_key' => $key], [
                'import_id' => (string) Str::uuid(),
                'import_type' => ImportHistory::TYPE_SEVERANCE,
                'module' => 'severance',
                'source' => 'excel',
                'status' => 'queued',
                'disk' => 'local',
                'file_name' => 'pengajuan-pesangon.xlsx',
                'file_path' => $path,
                'created_by' => (string) $actor->id,
                'file_size' => $file->getSize(),
                'summary' => $settings,
            ]);
            if ($history->wasRecentlyCreated) {
                ProcessSeveranceFile::dispatch($history->id);
            } else {
                $storage->deletePrivate($path, ['severance/']);
            }
        } catch (Throwable $exception) {
            $storage->deletePrivate($path, ['severance/']);
            if ($history && $history->wasRecentlyCreated) {
                $history->update([
                    'status' => 'failed',
                    'severance_process_key' => null,
                    'finished_at' => now(),
                    'error_message' => 'File gagal masuk antrean. Periksa worker lalu unggah kembali.'
                ]);
            }
            throw $exception;
        }
        return $history;
    }

    public function import(ImportHistory $batch): void
    {
        $actor = User::find($batch->created_by);
        if (!$actor || !$actor->hasMenuAccess('severance_staff')) {
            $this->invalid('Akses pembuat pengajuan sudah berubah. Unggah ulang melalui staf HI yang berwenang.');
        }
        $storage = app(SensitiveFileStorageService::class);
        $path = $storage->resolvePrivatePath($batch->file_path, ['severance/']);
        if (!$path) {
            $this->invalid('File sumber tidak tersedia. Unggah ulang file pengajuan.');
        }
        $batch->items()->delete();
        $total = 0;
        $failed = 0;
        foreach (app(SeveranceWorkbook::class)->chunks($path) as $rows) {
            $employees = Employee::whereIn('nik', array_column($rows, 'nik'))->get(['nik', 'nama_karyawan', 'entry_date'])->keyBy('nik');
            foreach ($rows as $number => $row) {
                $total++;
                try {
                    if ($row['error']) {
                        $this->invalid($row['error']);
                    }
                    if ($row['duplicate']) {
                        $this->invalid('NIK berulang di file. Satu karyawan hanya boleh muncul sekali per batch.');
                    }
                    Validator::make($row, [
                        'nik' => ['required', 'string', 'max:100'],
                        'reason' => ['nullable', 'string', 'max:2000'],
                        'article' => ['nullable', Rule::in(array_keys(config('severance.articles')))],
                        'return_fare' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'regex:/^\d+(\.\d{1,2})?$/']
                    ])->validate();
                    $employee = $employees->get($row['nik']);
                    if (!$employee || !$employee->entry_date || $employee->entry_date->format('Y-m-d') > $batch->summary['termination_date']) {
                        $this->invalid('NIK/tanggal masuk tidak ditemukan atau tanggal masuk melewati akhir kerja. Perbaiki data HRIS atau tanggal batch.');
                    }
                    DB::transaction(function () use ($batch, $number, $row, $employee) {
                        $existing = SeveranceRequest::where('employee_nik', $row['nik'])->where('termination_date', $batch->summary['termination_date'])->first();
                        if ($existing && ((int) $existing->import_history_id !== (int) $batch->id || (int) $existing->row_number !== $number)) {
                            $this->invalid('Sudah ada pengajuan untuk NIK dan tanggal akhir kerja ini. Lanjutkan pengajuan sebelumnya.');
                        }
                        SeveranceRequest::firstOrCreate(['import_history_id' => $batch->id, 'row_number' => $number], [
                            'employee_nik' => $row['nik'],
                            'employee_name' => $employee->nama_karyawan,
                            'source_metadata' => $row['metadata'],
                            'entry_date' => $employee->entry_date->format('Y-m-d'),
                            'calculation_start' => $employee->entry_date->format('Y-m-d'),
                            'termination_date' => $batch->summary['termination_date'],
                            'article' => $row['article'] ?: null,
                            'reason' => $row['reason'] ?: null,
                            'return_fare' => $row['return_fare'] === '' ? 0 : $row['return_fare'],
                            'created_by' => $batch->created_by,
                            'status' => 'draft',
                            'decisions' => [],
                        ]);
                    });
                } catch (ValidationException $exception) {
                    $failed++;
                    $batch->items()->updateOrCreate(['category' => 'failed', 'row_number' => $number], [
                        'message' => Str::limit(collect($exception->errors())->flatten()->first(), 500, ''),
                    ]);
                }
            }
            $batch->update(['total_rows' => $total, 'success_count' => $total - $failed, 'failed_count' => $failed]);
        }
        if (!$total) {
            $this->invalid('File tidak berisi pengajuan. Isi NIK mulai baris 8.');
        }
        $batch->update(['status' => $failed ? 'completed_with_errors' : 'completed', 'finished_at' => now()]);
        $storage->deletePrivate($batch->file_path, ['severance/']);
        $this->audit($actor, $batch, 'imported');
    }

    public function update(SeveranceRequest $entry, User $actor, array $data): void
    {
        DB::transaction(function () use ($entry, $actor, $data) {
            $entry = $this->locked($entry, $data['revision']);
            $this->authorizeStaff($entry, $actor);
            abort_unless($entry->status === 'draft', 409, 'Pengajuan sudah dikirim. Minta Supervisor mengembalikan untuk revisi.');
            if ($data['calculation_start'] < $entry->entry_date->format('Y-m-d')) {
                $this->invalid('Awal perhitungan tidak boleh mendahului tanggal masuk HRIS.');
            }
            if (SeveranceRequest::where('employee_nik', $entry->employee_nik)->where('termination_date', $data['termination_date'])->where('id', '!=', $entry->id)->exists()) {
                $this->invalid('Pengajuan untuk NIK dan tanggal akhir kerja tersebut sudah ada.');
            }
            $entry->fill(array_intersect_key($data, array_flip(['article', 'reason', 'calculation_start', 'termination_date', 'return_fare'])));
            $this->record($entry, $actor, 'edited');
        });
    }

    public function submitBatch(ImportHistory $batch, User $actor): int
    {
        $this->authorizeBatch($actor, $batch);
        abort_unless($this->canManage($actor, $batch->created_by), 403);
        abort_unless(in_array($batch->status, ['completed', 'completed_with_errors'], true), 409, 'Tunggu impor selesai.');
        return DB::transaction(function () use ($batch, $actor) {
            $drafts = SeveranceRequest::where('import_history_id', $batch->id)->where('status', 'draft')->lockForUpdate()->get(['id', 'article', 'reason']);
            if ($drafts->contains(function ($entry) {
                return !$entry->article || !trim((string) $entry->reason);
            })) {
                $this->invalid('Lengkapi pasal dan alasan seluruh draf sebelum mengirim ke Supervisor Payroll.');
            }
            $count = SeveranceRequest::whereIn('id', $drafts->pluck('id'))->update(['status' => 'payroll_review', 'revision' => DB::raw('revision + 1'), 'updated_at' => now()]);
            if ($count) {
                $this->audit($actor, $batch, 'submitted');
            }
            return $count;
        });
    }

    public function approvePayroll(SeveranceRequest $entry, User $actor, array $data): void
    {
        $this->authorizeEntry($actor, $entry);
        abort_unless($entry->canApprovePayroll($actor), 403, 'Supervisor Payroll harus berbeda dari pembuat pengajuan.');
        abort_unless($entry->status === 'payroll_review', 409, 'Tahap pengajuan sudah berubah. Muat ulang halaman.');
        $salary = app(SeveranceSalarySource::class)->resolve($entry);
        abort_unless(hash_equals($salary['hash'], $data['source_hash']), 409, 'Data V Payslip berubah. Muat ulang dan periksa kembali nominalnya.');
        DB::transaction(function () use ($entry, $actor, $data, $salary) {
            $entry = $this->locked($entry, $data['revision']);
            abort_unless($entry->status === 'payroll_review', 409);
            $entry->fill([
                'salary_snapshot' => $salary,
                'payroll_approved_by' => (string) $actor->id,
                'payroll_approved_at' => now(),
                'status' => 'hi_review'
            ]);
            $this->record($entry, $actor, 'payroll_approved');
        });
    }

    public function hi(SeveranceRequest $entry, User $actor, array $data): array
    {
        return DB::transaction(function () use ($entry, $actor, $data) {
            $entry = $this->locked($entry, $data['revision']);
            $this->authorizeEntry($actor, $entry);
            abort_unless($entry->canApproveHi($actor), 403, 'Supervisor HI harus berbeda dari staf pengaju dan Supervisor Payroll.');
            abort_unless($entry->status === 'hi_review' && $entry->payroll_approved_at && $entry->salary_snapshot, 409, 'Pengajuan belum berada pada tahap Supervisor HI.');
            $result = app(SeveranceCalculator::class)->calculate([
                'article' => $entry->article,
                'calculation_start' => $entry->calculation_start->format('Y-m-d'),
                'termination_date' => $entry->termination_date->format('Y-m-d'),
                'salary' => $entry->salary_snapshot,
                'return_fare' => $entry->return_fare,
                'leave_days' => $data['leave_days'],
            ]);
            $hash = hash('sha256', json_encode([$entry->id, $entry->revision, $result]));
            if ($data['intent'] === 'approve') {
                abort_unless(hash_equals($hash, $data['preview_hash']), 409, 'Pratinjau sudah berubah. Tampilkan dan periksa ulang hasil sebelum menyetujui.');
                $entry->fill([
                    'status' => 'approved',
                    'leave_days' => $data['leave_days'],
                    'calculation' => $result,
                    'calculation_hash' => hash('sha256', json_encode($result)),
                    'hi_approved_by' => (string) $actor->id,
                    'hi_approved_at' => now()
                ]);
                $this->record($entry, $actor, 'hi_approved');
            }
            return ['calculation' => $result, 'preview_hash' => $hash];
        });
    }

    public function reject(SeveranceRequest $entry, User $actor, array $data): void
    {
        DB::transaction(function () use ($entry, $actor, $data) {
            $entry = $this->locked($entry, $data['revision']);
            $this->authorizeEntry($actor, $entry);
            abort_unless(($entry->status === 'payroll_review' && $entry->canApprovePayroll($actor))
                || ($entry->status === 'hi_review' && $entry->canApproveHi($actor)), 403);
            $entry->fill([
                'status' => 'draft',
                'salary_snapshot' => null,
                'payroll_approved_by' => null,
                'payroll_approved_at' => null,
                'leave_days' => null,
                'hi_approved_by' => null,
                'hi_approved_at' => null,
                'calculation' => null,
                'calculation_hash' => null
            ]);
            $this->record($entry, $actor, 'returned', $data['note']);
        });
    }

    public function requireQueue(): void
    {
        $driver = config('queue.connections.' . config('severance.connection') . '.driver');
        if (in_array($driver, [null, 'sync', 'null'], true)) {
            $this->invalid('Antrean pesangon belum aktif. Minta admin menyiapkan koneksi database/Redis dan worker.');
        }
    }

    public function retry(ImportHistory $history, User $actor): void
    {
        abort_unless(in_array($history->module, ['severance', 'severance_export'], true)
            && $this->canManage($actor, $history->created_by), 403);
        $this->requireQueue();
        DB::transaction(function () use ($history) {
            $history = ImportHistory::lockForUpdate()->findOrFail($history->id);
            abort_unless($history->status === 'failed', 409, 'Proses sudah berjalan atau selesai.');
            $history->update(['status' => 'queued', 'error_message' => null, 'finished_at' => null]);
        });
        try {
            ProcessSeveranceFile::dispatch($history->id);
        } catch (Throwable $exception) {
            $history->update(['status' => 'failed', 'error_message' => 'Proses belum masuk antrean. Coba lagi setelah worker tersedia.']);
            throw $exception;
        }
    }

    private function authorizeStaff(SeveranceRequest $entry, User $actor): void
    {
        abort_unless($this->canManage($actor, $entry->created_by), 403);
    }

    private function locked(SeveranceRequest $entry, int $revision): SeveranceRequest
    {
        $entry = SeveranceRequest::lockForUpdate()->findOrFail($entry->id);
        abort_unless($entry->revision === $revision, 409, 'Pengajuan berubah atau tindakan sudah diproses. Muat ulang halaman sebelum melanjutkan.');
        return $entry;
    }

    private function record(SeveranceRequest $entry, User $actor, string $action, ?string $note = null): void
    {
        $entry->decisions = array_merge($entry->decisions ?? [], [[
            'action' => $action,
            'actor' => $actor->name,
            'actor_id' => (string) $actor->id,
            'at' => now()->toIso8601String(),
            'note' => $note,
        ]]);
        $entry->revision++;
        $entry->save();
        $this->audit($actor, $entry, $action);
    }

    public function audit(User $actor, $model, string $action): void
    {
        app(AuditTrailService::class)->record([
            'actor' => $actor,
            'event' => 'severance.' . $action,
            'module' => 'severance',
            'auditable_type' => get_class($model),
            'auditable_id' => (string) $model->id,
            'metadata' => ['status' => $model->status]
        ]);
    }

    private function invalid(string $message): void
    {
        throw ValidationException::withMessages(['severance' => $message]);
    }
}
