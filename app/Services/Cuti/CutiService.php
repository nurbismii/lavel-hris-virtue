<?php

namespace App\Services\Cuti;

use App\Models\Cuti;
use App\Models\Employee;
use App\Models\ApprovalDelegation;
use App\Services\Approvals\ApprovalDelegationService;
use App\Services\LeaveBalance\LeaveBalanceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CutiService
{
    public function storeCuti($request)
    {
        $STATUS_PEMOHON = 1;
        $STATUS_HOD = 0;
        $STATUS_HR = 0;

        $data = $request->validated();
        $user = $request->user();

        return DB::transaction(function () use ($data, $user, $STATUS_PEMOHON, $STATUS_HOD, $STATUS_HR) {
            $employee = Employee::query()
                ->where('nik', $user->nik_karyawan)
                ->lockForUpdate()
                ->first();

            if (!$employee) {
                return [
                    'status' => false,
                    'message' => 'Data karyawan tidak ditemukan.'
                ];
            }

            $startDate = Carbon::parse($data['tanggal_mulai'])->toDateString();
            $endDate = Carbon::parse($data['tanggal_berakhir'])->toDateString();
            $jumlahHari = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;

            if ($this->hasActiveOverlap($employee->nik, $startDate, $endDate)) {
                return [
                    'status' => false,
                    'message' => 'Rentang cuti tersebut sudah memiliki pengajuan yang masih aktif atau disetujui.'
                ];
            }

            $currentBalance = app(LeaveBalanceService::class)->currentBalance($employee);

            if ($jumlahHari > $currentBalance) {
                return [
                    'status' => false,
                    'message' => 'Sisa cuti tidak cukup'
                ];
            }

            $delegationService = app(ApprovalDelegationService::class);
            $delegations = $delegationService->activeDelegationsForEmployee(
                $employee,
                ApprovalDelegation::MODULE_CUTI,
                $user
            );

            $cuti = Cuti::create(array_merge([
                'nik_karyawan' => $employee->nik,
                'tanggal' => now()->toDateString(),
                'tanggal_mulai' => $startDate,
                'tanggal_berakhir' => $endDate,
                'jumlah' => $jumlahHari,
                'status_pemohon' => $STATUS_PEMOHON,
                'status_hod' => $STATUS_HOD,
                'status_hrd' => $STATUS_HR,
                'tipe' => 'CUTI',
                'keterangan' => $data['keterangan'] ?? null,
                'created_at' => now()
            ], $delegationService->submissionPayload('cuti_izin', $delegations)));

            $delegationService->createAssignments($cuti, $delegations, ApprovalDelegation::MODULE_CUTI);

            return [
                'status' => true,
                'message' => 'Pengajuan cuti berhasil dibuat',
                'cuti' => $cuti->fresh(['employee']),
            ];
        });
    }

    public function updateCuti($request, Cuti $cuti)
    {
        $data = $request->validated();

        return DB::transaction(function () use ($data, $cuti) {
            $employee = Employee::query()
                ->where('nik', $cuti->nik_karyawan)
                ->lockForUpdate()
                ->first();

            if (!$employee) {
                return [
                    'status' => false,
                    'message' => 'Data karyawan tidak ditemukan.'
                ];
            }

            $startDate = Carbon::parse($data['tanggal_mulai'])->toDateString();
            $endDate = Carbon::parse($data['tanggal_berakhir'])->toDateString();
            $jumlahHari = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;

            if ($this->hasActiveOverlap($employee->nik, $startDate, $endDate, $cuti->id)) {
                return [
                    'status' => false,
                    'message' => 'Rentang cuti tersebut sudah memiliki pengajuan yang masih aktif atau disetujui.'
                ];
            }

            $currentBalance = app(LeaveBalanceService::class)->currentBalance($employee);

            if ($jumlahHari > $currentBalance) {
                return [
                    'status' => false,
                    'message' => 'Sisa cuti tidak cukup'
                ];
            }

            $cuti->update([
                'tanggal_mulai' => $startDate,
                'tanggal_berakhir' => $endDate,
                'jumlah' => $jumlahHari,
                'tipe' => 'CUTI',
                'keterangan' => $data['keterangan'] ?? null,
                'updated_at' => now()
            ]);

            return [
                'status' => true,
                'message' => 'Pengajuan cuti berhasil diperbarui',
                'cuti' => $cuti->fresh(['employee']),
            ];
        });
    }

    private function hasActiveOverlap(string $nikKaryawan, string $startDate, string $endDate, ?int $ignoreId = null): bool
    {
        return Cuti::query()
            ->where('nik_karyawan', $nikKaryawan)
            ->where('tipe', 'CUTI')
            ->when($ignoreId, fn($query) => $query->where('id', '!=', $ignoreId))
            ->whereDate('tanggal_mulai', '<=', $endDate)
            ->whereDate('tanggal_berakhir', '>=', $startDate)
            ->when(Schema::hasColumn('cuti_izin', 'delegate_status'), function ($query) {
                $query->where(fn($delegateQuery) => $delegateQuery->whereNull('delegate_status')->orWhere('delegate_status', '!=', 2));
            })
            ->where(fn($query) => $query->whereNull('status_hod')->orWhere('status_hod', '!=', 2))
            ->where(fn($query) => $query->whereNull('status_hrd')->orWhere('status_hrd', '!=', 2))
            ->exists();
    }
}
