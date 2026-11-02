<?php

namespace App\Services\Salary;

use App\Models\Employee;
use App\Models\EmployeeSalaryHistory;
use App\Models\ImportHistory;
use App\Models\ImportHistoryItem;
use App\Models\AttendancePeriodLock;
use Illuminate\Support\Facades\Schema;
use App\Services\Storage\SensitiveFileStorageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use RuntimeException;

class SalaryImportService
{
    public function preview(ImportHistory $history): void
    {
        $storage = app(SensitiveFileStorageService::class);
        $path = $storage->resolvePrivatePath((string) $history->file_path, ['salary-imports/']);
        if (!$path || !hash_equals((string) $history->file_checksum, hash_file('sha256', $path))) {
            throw new RuntimeException('File pembaruan gaji tidak tersedia atau berubah. Unggah ulang file.');
        }
        $history->items()->delete();
        $history->update(['total_rows' => 0, 'failed_count' => 0, 'success_count' => 0, 'error_message' => null]);
        $seen = [];
        foreach (app(SalaryWorkbookReader::class)->chunks($path) as $chunk) {
            $employees = Employee::query()->whereIn('nik', array_column($chunk, 'nik'))
                ->pluck('nama_karyawan', 'nik');
            $valid = [];
            $errors = [];
            $lockedEnd = $this->latestLockedEnd();
            foreach ($chunk as $number => $row) {
                $row['nik'] = trim((string) $row['nik']);
                if (is_numeric($row['berlaku_mulai']) && (float) $row['berlaku_mulai'] >= 36526 && (float) $row['berlaku_mulai'] <= 73415) {
                    $row['berlaku_mulai'] = Date::excelToDateTimeObject((float) $row['berlaku_mulai'])->format('Y-m-d');
                }
                foreach (['alasan', 'referensi'] as $key) {
                    $row[$key] = trim((string) $row[$key]);
                }
                $validator = Validator::make($row, [
                    'nik' => ['required', 'string', 'max:100'],
                    'gaji_pokok' => ['required', 'numeric', 'min:0.01', 'max:999999999', 'regex:/^\d+(\.\d{1,2})?$/'],
                    'tunjangan_tetap' => ['required', 'numeric', 'min:0', 'max:999999999', 'regex:/^\d+(\.\d{1,2})?$/'],
                    'tunjangan_tidak_tetap' => ['required', 'numeric', 'min:0', 'max:999999999', 'regex:/^\d+(\.\d{1,2})?$/'],
                    'berlaku_mulai' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
                    'alasan' => ['required', 'string', 'max:255', 'not_regex:/^[=+@]/'],
                    'referensi' => ['required', 'string', 'max:150', 'not_regex:/^[=+@]/'],
                ], [
                    'required' => ':attribute wajib diisi (isi 0 jika tidak ada tunjangan).',
                    'numeric' => ':attribute harus berupa angka tanpa pemisah ribuan.',
                    'regex' => ':attribute harus berupa angka dengan maksimal 2 desimal.',
                    'date_format' => 'Tanggal berlaku harus berformat YYYY-MM-DD atau berupa tanggal Excel.',
                    'not_regex' => ':attribute tidak boleh berupa rumus Excel.',
                ]);
                $messages = $validator->errors()->all();
                if (!$employees->has($row['nik'])) {
                    $messages[] = 'NIK tidak ditemukan dalam data karyawan.';
                }
                $key = $row['nik'] . '|' . (string) $row['berlaku_mulai'];
                if (isset($seen[$key])) {
                    $messages[] = 'NIK dan tanggal efektif berulang dalam file.';
                }
                $seen[$key] = true;
                if (!$messages) {
                    if ($lockedEnd && $row['berlaku_mulai'] <= $lockedEnd) {
                        $messages[] = 'Tanggal efektif berdampak pada periode presensi terkunci. Selesaikan proses buka ulang periode oleh HR sebelum import.';
                    }
                }
                $chunk[$number] = $row;
                if ($messages) {
                    $errors[$number] = implode(' ', $messages);
                } else {
                    $valid[$number] = $row;
                }
            }
            $previous = app(EmployeeSalaryService::class)->forRows($valid);
            $items = [];
            foreach ($chunk as $number => $row) {
                $old = $previous[$number] ?? null;
                $row['previous_id'] = $old ? $old->id : null;
                $row['previous_basic_salary'] = $old ? $old->basic_salary : null;
                $items[] = [
                    'import_history_id' => $history->id,
                    'category' => isset($errors[$number]) ? ImportHistoryItem::CATEGORY_FAILED : ImportHistoryItem::CATEGORY_UPDATED,
                    'row_number' => $number, 'nik' => mb_substr($row['nik'], 0, 100),
                    'employee_name' => $employees->get($row['nik']),
                    'message' => mb_substr($errors[$number] ?? ($old && $old->effective_from->toDateString() === $row['berlaku_mulai']
                        ? 'Revisi tanggal efektif yang sama; versi lama tetap disimpan.' : 'Siap dikonfirmasi.'), 0, 500),
                    'payload' => json_encode($row), 'created_at' => now(), 'updated_at' => now(),
                ];
            }
            DB::transaction(function () use ($history, $items, $errors) {
                ImportHistoryItem::insert($items);
                ImportHistory::whereKey($history->id)->increment('total_rows', count($items));
                ImportHistory::whereKey($history->id)->increment('failed_count', count($errors));
            });
        }
        $history->refresh();
        $valid = $history->total_rows > 0 && $history->failed_count === 0;
        $history->update([
            'status' => $valid ? ImportHistory::STATUS_AWAITING_CONFIRMATION : ImportHistory::STATUS_VALIDATION_FAILED,
            'error_message' => $valid ? null : ($history->total_rows ? 'Ada baris tidak valid. Perbaiki file dan unggah ulang; belum ada gaji yang diterapkan.' : 'File tidak memiliki baris data.'),
            'finished_at' => now(),
            'salary_upload_key' => $valid ? $history->salary_upload_key : null,
            'expires_at' => now()->addDay(),
        ]);
        $storage->deletePrivate((string) $history->file_path, ['salary-imports/']);
    }

    public function commit(ImportHistory $history): void
    {
        if (in_array($history->status, [ImportHistory::STATUS_COMPLETED, ImportHistory::STATUS_COMPLETED_WITH_ERRORS], true)) {
            return;
        }
        if (!$history->confirmed_by || !$history->confirmed_at || ($history->summary['phase'] ?? null) !== 'commit') {
            throw new RuntimeException('Pembaruan gaji belum dikonfirmasi oleh HR.');
        }
        $actor = \App\Models\User::find($history->confirmed_by);
        if (!$actor || !$actor->hasRole(['Super Admin', 'HR']) || !$actor->hasMenuAccess('slip_gaji_admin')) {
            throw new RuntimeException('Akses penyetuju sudah tidak berlaku. Unggah ulang melalui HR yang berwenang.');
        }
        $history->items()->orderBy('id')->chunkById(200, function ($items) use ($history) {
            DB::transaction(function () use ($items, $history) {
                $employees = Employee::query()->whereIn('nik', $items->pluck('nik'))->orderBy('nik')
                    ->lockForUpdate()->pluck('nik')->all();
                $rows = [];
                foreach ($items as $item) {
                    $rows[$item->id] = $item->payload;
                }
                $current = app(EmployeeSalaryService::class)->forRows($rows);
                $applied = array_fill_keys(EmployeeSalaryHistory::where('import_history_id', $history->id)
                    ->whereIn('row_number', $items->pluck('row_number'))->pluck('row_number')->all(), true);
                $lockedEnd = $this->latestLockedEnd();
                $inserts = [];
                $appliedItems = [];
                foreach ($items as $item) {
                    if (isset($applied[$item->row_number])) {
                        $appliedItems[] = $item->id;
                        continue;
                    }
                    $row = $item->payload;
                    $old = $current[$item->id] ?? null;
                    $changed = ($old ? $old->id : null) !== $row['previous_id']
                        && (!$old || (int) $old->import_history_id !== (int) $history->id);
                    $locked = $lockedEnd && $row['berlaku_mulai'] <= $lockedEnd;
                    if (!in_array($item->nik, $employees, true) || $changed || $locked) {
                        $item->update(['category' => ImportHistoryItem::CATEGORY_FAILED,
                            'message' => 'Data upah/karyawan atau penguncian periode berubah setelah pratinjau. Unggah ulang baris ini untuk ditinjau kembali.']);
                        continue;
                    }
                    $inserts[] = [
                        'employee_nik' => $item->nik, 'effective_from' => $row['berlaku_mulai'],
                        'basic_salary' => $row['gaji_pokok'], 'fixed_allowance' => $row['tunjangan_tetap'],
                        'non_fixed_allowance' => $row['tunjangan_tidak_tetap'],
                        'reason' => $row['alasan'], 'reference' => $row['referensi'],
                        'import_history_id' => $history->id, 'row_number' => $item->row_number,
                        'approved_by' => $history->confirmed_by, 'approved_at' => $history->confirmed_at,
                        'created_at' => now(), 'updated_at' => now(),
                    ];
                    $appliedItems[] = $item->id;
                }
                if ($inserts) {
                    EmployeeSalaryHistory::insert($inserts);
                }
                if ($appliedItems) {
                    ImportHistoryItem::whereIn('id', $appliedItems)->update([
                        'category' => ImportHistoryItem::CATEGORY_UPDATED, 'message' => 'Diterapkan; versi upah tersimpan.',
                    ]);
                }
                $this->updateCounts($history);
            }, 3);
        });
        $this->updateCounts($history);
        $history->refresh();
        $history->update([
            'status' => $history->failed_count ? ($history->success_count ? ImportHistory::STATUS_COMPLETED_WITH_ERRORS : ImportHistory::STATUS_FAILED) : ImportHistory::STATUS_COMPLETED,
            'error_message' => $history->failed_count ? 'Sebagian atau seluruh baris belum diterapkan. Lihat detail dan unggah ulang hanya baris yang gagal.' : null,
            'finished_at' => now(),
            'salary_upload_key' => $history->failed_count ? null : $history->salary_upload_key,
        ]);
    }

    private function updateCounts(ImportHistory $history): void
    {
        $success = EmployeeSalaryHistory::where('import_history_id', $history->id)->count();
        $history->update(['success_count' => $success, 'inserted_count' => $success,
            'failed_count' => $history->items()->where('category', ImportHistoryItem::CATEGORY_FAILED)->count()]);
    }

    private function latestLockedEnd(): ?string
    {
        return Schema::hasTable('attendance_period_locks')
            ? AttendancePeriodLock::where('status', AttendancePeriodLock::STATUS_LOCKED)->max('end_date') : null;
    }
}
