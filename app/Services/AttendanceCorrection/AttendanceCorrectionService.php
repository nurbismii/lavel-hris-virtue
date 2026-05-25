<?php

namespace App\Services\AttendanceCorrection;

use App\Models\ApprovalDelegation;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\Presensi;
use App\Models\User;
use App\Services\Approvals\ApprovalDelegationService;
use App\Services\Audit\AuditTrailService;
use App\Services\Storage\SensitiveFileStorageService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AttendanceCorrectionService
{
    private const ACTIVE_STATUS = 'AKTIF';
    private const ALLOWED_AREAS = ['VDNI', 'VDNIP'];
    private const ATTENDANCE_TIME_COLUMNS = [
        'jam_masuk',
        'jam_istirahat',
        'jam_kembali_istirahat',
        'jam_pulang',
    ];

    private SensitiveFileStorageService $storage;
    private AuditTrailService $auditTrail;

    public function __construct(SensitiveFileStorageService $storage, AuditTrailService $auditTrail)
    {
        $this->storage = $storage;
        $this->auditTrail = $auditTrail;
    }

    public function submit(User $user, array $data, ?UploadedFile $attachment = null): array
    {
        $attachmentPath = null;

        try {
            return DB::transaction(function () use ($user, $data, $attachment, &$attachmentPath) {
                $employee = Employee::query()
                    ->where('nik', $user->nik_karyawan)
                    ->where('status_resign', self::ACTIVE_STATUS)
                    ->whereIn('area_kerja', self::ALLOWED_AREAS)
                    ->lockForUpdate()
                    ->first();

                if (!$employee) {
                    return [
                        'status' => false,
                        'message' => 'Pengajuan hanya bisa dibuat oleh karyawan aktif VDNI/VDNIP.',
                    ];
                }

                $tanggal = Carbon::parse($data['tanggal'])->toDateString();
                $businessValidation = $this->validateBusinessRequest($data, $attachment);

                if (!$businessValidation['status']) {
                    return $businessValidation;
                }

                $activeRequest = AttendanceCorrection::query()
                    ->where('nik_karyawan', $employee->nik)
                    ->whereDate('tanggal', $tanggal)
                    ->whereNull('applied_at')
                    ->when(Schema::hasColumn('attendance_corrections', 'delegate_status'), function ($query) {
                        $query->where(fn($delegateQuery) => $delegateQuery->whereNull('delegate_status')->orWhere('delegate_status', '!=', AttendanceCorrection::STATUS_REJECTED));
                    })
                    ->where('status_hod', '!=', AttendanceCorrection::STATUS_REJECTED)
                    ->where('status_hrd', '!=', AttendanceCorrection::STATUS_REJECTED)
                    ->lockForUpdate()
                    ->first(['id']);

                if ($activeRequest) {
                    return [
                        'status' => false,
                        'message' => 'Tanggal ini masih memiliki pengajuan aktif. Tunggu approval selesai atau ajukan tanggal lain.',
                    ];
                }

                $presensi = Presensi::query()
                    ->where('nik_karyawan', $employee->nik)
                    ->whereDate('tanggal', $tanggal)
                    ->lockForUpdate()
                    ->first();

                if ($attachment) {
                    $attachmentPath = $this->storeAttachment($attachment, $employee->nik);
                }

                $requestedValues = $this->requestedValues($tanggal, $data);
                $delegationService = app(ApprovalDelegationService::class);
                $delegations = $delegationService->activeDelegationsForEmployee(
                    $employee,
                    ApprovalDelegation::MODULE_ATTENDANCE_CORRECTION,
                    $user
                );

                $correction = AttendanceCorrection::create(array_merge($requestedValues, [
                    'nik_karyawan' => $employee->nik,
                    'presensi_id' => optional($presensi)->id,
                    'tanggal' => $tanggal,
                    'old_values' => $this->attendanceValues($presensi),
                    'reason' => $data['reason'],
                    'attachment_path' => $attachmentPath,
                    'created_by' => (string) $user->id,
                ], $delegationService->submissionPayload('attendance_corrections', $delegations)));

                $delegationService->createAssignments(
                    $correction,
                    $delegations,
                    ApprovalDelegation::MODULE_ATTENDANCE_CORRECTION
                );

                $this->recordAudit(
                    $correction,
                    'attendance_correction.submitted',
                    $user,
                    $correction->old_values ?: [],
                    $this->correctionValues($correction),
                    $data['reason']
                );

                return [
                    'status' => true,
                    'message' => 'Koreksi Presensi berhasil dikirim.',
                    'correction' => $correction,
                ];
            });
        } catch (\Throwable $exception) {
            if ($attachmentPath) {
                $this->storage->delete($attachmentPath, ['attendance-corrections/']);
            }

            throw $exception;
        }
    }

    public function processHod(AttendanceCorrection $correction, User $actor, int $action, ?string $note = null): array
    {
        return DB::transaction(function () use ($correction, $actor, $action, $note) {
            $correction = AttendanceCorrection::query()
                ->whereKey($correction->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $correction->status_hod !== AttendanceCorrection::STATUS_PENDING) {
                return [
                    'status' => false,
                    'message' => 'Pengajuan koreksi ini sudah diproses oleh HOD.',
                ];
            }

            $oldValues = $this->approvalValues($correction);

            $correction->update([
                'status_hod' => $action,
                'hod_processed_by' => (string) $actor->id,
                'hod_processed_at' => now(),
                'hod_rejection_reason' => $action === AttendanceCorrection::STATUS_REJECTED ? $note : null,
            ]);

            $correction = $correction->fresh(['employee', 'requester']);

            $this->recordAudit(
                $correction,
                'attendance_correction.hod.' . ($action === AttendanceCorrection::STATUS_APPROVED ? 'approved' : 'rejected'),
                $actor,
                $oldValues,
                $this->approvalValues($correction),
                $note
            );

            return [
                'status' => true,
                'message' => $action === AttendanceCorrection::STATUS_APPROVED
                    ? 'Koreksi Presensi disetujui oleh HOD dan menunggu HR.'
                    : 'Koreksi Presensi ditolak oleh HOD.',
                'correction' => $correction,
                'approval_status' => $action === AttendanceCorrection::STATUS_APPROVED ? 'Disetujui' : 'Ditolak',
            ];
        });
    }

    public function processHrd(AttendanceCorrection $correction, User $actor, int $action, ?string $note = null): array
    {
        return DB::transaction(function () use ($correction, $actor, $action, $note) {
            $correction = AttendanceCorrection::query()
                ->whereKey($correction->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $correction->status_hod !== AttendanceCorrection::STATUS_APPROVED) {
                return [
                    'status' => false,
                    'message' => 'Pengajuan koreksi belum disetujui HOD.',
                ];
            }

            if ((int) $correction->status_hrd !== AttendanceCorrection::STATUS_PENDING) {
                return [
                    'status' => false,
                    'message' => 'Pengajuan koreksi ini sudah diproses oleh HR.',
                ];
            }

            $oldApprovalValues = $this->approvalValues($correction);

            if ($action === AttendanceCorrection::STATUS_REJECTED) {
                $correction->update([
                    'status_hrd' => AttendanceCorrection::STATUS_REJECTED,
                    'hrd_processed_by' => (string) $actor->id,
                    'hrd_processed_at' => now(),
                    'hrd_rejection_reason' => $note,
                ]);

                $correction = $correction->fresh(['employee', 'requester']);

                $this->recordAudit(
                    $correction,
                    'attendance_correction.hrd.rejected',
                    $actor,
                    $oldApprovalValues,
                    $this->approvalValues($correction),
                    $note
                );

                return [
                    'status' => true,
                    'message' => 'Koreksi Presensi ditolak oleh HR.',
                    'correction' => $correction,
                    'approval_status' => 'Ditolak',
                ];
            }

            $presensi = $this->lockedPresensiForCorrection($correction);
            $oldAttendanceValues = $this->attendanceValues($presensi);

            $presensi = $this->applyCorrectionToPresensi($correction, $presensi);
            $appliedValues = $this->attendanceValues($presensi);

            $correction->update([
                'presensi_id' => $presensi->id,
                'status_hrd' => AttendanceCorrection::STATUS_APPROVED,
                'hrd_processed_by' => (string) $actor->id,
                'hrd_processed_at' => now(),
                'applied_values' => $appliedValues,
                'applied_by' => (string) $actor->id,
                'applied_at' => now(),
            ]);

            $correction = $correction->fresh(['employee', 'requester']);

            $this->recordAudit(
                $correction,
                'attendance_correction.hrd.approved',
                $actor,
                $oldApprovalValues,
                $this->approvalValues($correction),
                $note
            );

            $this->recordAudit(
                $correction,
                'attendance_correction.applied',
                $actor,
                $oldAttendanceValues,
                $appliedValues,
                $note,
                [
                    'presensi_id' => $presensi->id,
                    'tanggal' => optional($correction->tanggal)->toDateString(),
                ]
            );

            return [
                'status' => true,
                'message' => 'Koreksi Presensi disetujui HR dan data presensi sudah diperbarui.',
                'correction' => $correction,
                'approval_status' => 'Disetujui',
            ];
        });
    }

    private function storeAttachment(UploadedFile $file, string $nikKaryawan): string
    {
        $directory = 'attendance-corrections/' . $nikKaryawan . '/' . now()->format('Y/m');
        $filename = (string) Str::uuid() . '.' . strtolower($file->getClientOriginalExtension());

        return $this->storage->storeUploadedFileAs($file, $directory, $filename);
    }

    private function validateBusinessRequest(array $data, ?UploadedFile $attachment): array
    {
        $requestType = $data['request_type'] ?? AttendanceCorrection::REQUEST_TYPE_CORRECTION;

        if ($requestType !== AttendanceCorrection::REQUEST_TYPE_PARTIAL_PERMISSION) {
            $hasTimeChange = collect(self::ATTENDANCE_TIME_COLUMNS)
                ->contains(fn($column) => filled($data[$column] ?? null));

            if (!$hasTimeChange && blank($data['status_presensi'] ?? null)) {
                return [
                    'status' => false,
                    'message' => 'Isi minimal satu jam koreksi atau status presensi yang perlu diperbaiki.',
                ];
            }

            return ['status' => true];
        }

        $partialType = $data['partial_permission_type'] ?? null;

        if (!in_array($partialType, array_keys(AttendanceCorrection::partialPermissionOptions()), true)) {
            return [
                'status' => false,
                'message' => 'Kategori izin presensi parsial wajib dipilih.',
            ];
        }

        if (filled($data['status_presensi'] ?? null)) {
            return [
                'status' => false,
                'message' => 'Status harian khusus tidak digunakan untuk izin presensi parsial.',
            ];
        }

        if (
            $partialType === AttendanceCorrection::PARTIAL_HALF_DAY
            && !in_array($data['partial_permission_period'] ?? null, array_keys(AttendanceCorrection::halfDayPeriodOptions()), true)
        ) {
            return [
                'status' => false,
                'message' => 'Pilih setengah hari pagi atau setengah hari siang.',
            ];
        }

        if ($partialType === AttendanceCorrection::PARTIAL_SICK && !$attachment) {
            return [
                'status' => false,
                'message' => 'Izin sakit wajib melampirkan surat keterangan sakit.',
            ];
        }

        return ['status' => true];
    }

    private function requestedValues(string $tanggal, array $data): array
    {
        $requestType = $data['request_type'] ?? AttendanceCorrection::REQUEST_TYPE_CORRECTION;
        $values = [
            'request_type' => $requestType,
            'partial_permission_type' => $requestType === AttendanceCorrection::REQUEST_TYPE_PARTIAL_PERMISSION
                ? ($data['partial_permission_type'] ?? null)
                : null,
            'partial_permission_period' => $requestType === AttendanceCorrection::REQUEST_TYPE_PARTIAL_PERMISSION
                && ($data['partial_permission_type'] ?? null) === AttendanceCorrection::PARTIAL_HALF_DAY
                ? ($data['partial_permission_period'] ?? null)
                : null,
        ];

        foreach (self::ATTENDANCE_TIME_COLUMNS as $column) {
            $values['requested_' . $column] = filled($data[$column] ?? null)
                ? Carbon::createFromFormat('Y-m-d H:i', $tanggal . ' ' . $data[$column])->format('Y-m-d H:i:s')
                : null;
        }

        $statusInput = $requestType === AttendanceCorrection::REQUEST_TYPE_CORRECTION
            ? ($data['status_presensi'] ?? null)
            : null;
        $values['change_status_presensi'] = filled($statusInput);
        $values['requested_status_presensi'] = filled($statusInput)
            ? ($statusInput === '__clear__' ? null : $statusInput)
            : null;

        return $values;
    }

    private function lockedPresensiForCorrection(AttendanceCorrection $correction): ?Presensi
    {
        $query = Presensi::query()->lockForUpdate();

        if ($correction->presensi_id) {
            $presensi = (clone $query)->whereKey($correction->presensi_id)->first();

            if ($presensi) {
                return $presensi;
            }
        }

        return $query
            ->where('nik_karyawan', $correction->nik_karyawan)
            ->whereDate('tanggal', $correction->tanggal)
            ->first();
    }

    private function applyCorrectionToPresensi(AttendanceCorrection $correction, ?Presensi $presensi): Presensi
    {
        if (!$presensi) {
            $presensi = new Presensi([
                'nik_karyawan' => $correction->nik_karyawan,
                'tanggal' => $correction->tanggal,
            ]);
        }

        foreach (self::ATTENDANCE_TIME_COLUMNS as $column) {
            $attribute = 'requested_' . $column;

            if ($correction->{$attribute}) {
                $presensi->{$column} = $correction->{$attribute};
            }
        }

        if ($correction->change_status_presensi) {
            $presensi->status_presensi = $correction->requested_status_presensi;
        }

        if (($correction->request_type ?: AttendanceCorrection::REQUEST_TYPE_CORRECTION) === AttendanceCorrection::REQUEST_TYPE_PARTIAL_PERMISSION) {
            $presensi->partial_permission_type = $correction->partial_permission_type;
            $presensi->partial_permission_period = $correction->partial_permission_period;
            $presensi->partial_permission_note = Str::limit((string) $correction->reason, 500, '');
            $presensi->partial_permission_correction_id = $correction->id;
        }

        $presensi->save();

        return $presensi->fresh();
    }

    private function attendanceValues(?Presensi $presensi): array
    {
        if (!$presensi) {
            return [
                'presensi_id' => null,
                'jam_masuk' => null,
                'jam_istirahat' => null,
                'jam_kembali_istirahat' => null,
                'jam_pulang' => null,
                'status_presensi' => null,
                'partial_permission_type' => null,
                'partial_permission_period' => null,
                'partial_permission_note' => null,
                'partial_permission_correction_id' => null,
            ];
        }

        return [
            'presensi_id' => $presensi->id,
            'jam_masuk' => $this->formatDateTime($presensi->jam_masuk),
            'jam_istirahat' => $this->formatDateTime($presensi->jam_istirahat),
            'jam_kembali_istirahat' => $this->formatDateTime($presensi->jam_kembali_istirahat),
            'jam_pulang' => $this->formatDateTime($presensi->jam_pulang),
            'status_presensi' => $presensi->status_presensi,
            'partial_permission_type' => $presensi->partial_permission_type,
            'partial_permission_period' => $presensi->partial_permission_period,
            'partial_permission_note' => $presensi->partial_permission_note,
            'partial_permission_correction_id' => $presensi->partial_permission_correction_id,
        ];
    }

    private function correctionValues(AttendanceCorrection $correction): array
    {
        $values = [
            'tanggal' => optional($correction->tanggal)->toDateString(),
            'request_type' => $correction->request_type ?: AttendanceCorrection::REQUEST_TYPE_CORRECTION,
            'partial_permission_type' => $correction->partial_permission_type,
            'partial_permission_period' => $correction->partial_permission_period,
            'reason' => $correction->reason,
            'attachment' => $correction->attachment_path ? 'available' : null,
        ];

        foreach (self::ATTENDANCE_TIME_COLUMNS as $column) {
            $attribute = 'requested_' . $column;
            $values[$attribute] = $this->formatDateTime($correction->{$attribute});
        }

        if ($correction->change_status_presensi) {
            $values['requested_status_presensi'] = $correction->requested_status_presensi;
        }

        return $values;
    }

    private function approvalValues(AttendanceCorrection $correction): array
    {
        return [
            'delegate_status' => $correction->delegate_status,
            'delegate_processed_by' => $correction->delegate_processed_by,
            'delegate_processed_at' => $this->formatDateTime($correction->delegate_processed_at),
            'status_hod' => (int) $correction->status_hod,
            'status_hrd' => (int) $correction->status_hrd,
            'hod_processed_by' => $correction->hod_processed_by,
            'hod_processed_at' => $this->formatDateTime($correction->hod_processed_at),
            'hrd_processed_by' => $correction->hrd_processed_by,
            'hrd_processed_at' => $this->formatDateTime($correction->hrd_processed_at),
            'applied_by' => $correction->applied_by,
            'applied_at' => $this->formatDateTime($correction->applied_at),
        ];
    }

    private function recordAudit(
        AttendanceCorrection $correction,
        string $event,
        User $actor,
        array $oldValues,
        array $newValues,
        ?string $note = null,
        array $metadata = []
    ): void {
        $this->auditTrail->record([
            'event' => $event,
            'module' => 'attendance_correction',
            'auditable_type' => AttendanceCorrection::class,
            'auditable_id' => (string) $correction->id,
            'reference_table' => 'attendance_corrections',
            'reference_id' => (string) $correction->id,
            'employee_nik' => $correction->nik_karyawan,
            'actor' => $actor,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => array_merge([
                'presensi_id' => $correction->presensi_id,
                'tanggal' => optional($correction->tanggal)->toDateString(),
            ], $metadata),
            'note' => $note,
        ]);
    }

    private function formatDateTime($value): ?string
    {
        if (!$value) {
            return null;
        }

        return $value instanceof Carbon
            ? $value->format('Y-m-d H:i:s')
            : Carbon::parse($value)->format('Y-m-d H:i:s');
    }
}
