@extends('layouts.app')

@section('title', 'Perubahan posisi')

@section('content')
@php
$movementLabel = function ($movement) {
if ($movement->movement_type === \App\Models\EmployeeMovement::TYPE_MUTATION) {
$oldOrg = collect([
optional($movement->oldDepartemen)->departemen,
optional($movement->oldDivisi)->nama_divisi,
])->filter()->implode(' / ') ?: '-';
$newOrg = collect([
optional($movement->newDepartemen)->departemen,
optional($movement->newDivisi)->nama_divisi,
])->filter()->implode(' / ') ?: '-';

return $oldOrg . ' -> ' . $newOrg;
}

$oldPosition = collect([$movement->old_posisi, $movement->old_jabatan])->filter()->implode(' / ') ?: '-';
$newPosition = collect([$movement->new_posisi, $movement->new_jabatan])->filter()->implode(' / ') ?: '-';

return $oldPosition . ' -> ' . $newPosition;
};

$approvalVisible = function ($movement) {
return in_array($movement->status, [
\App\Models\EmployeeMovement::STATUS_PENDING_HOD,
\App\Models\EmployeeMovement::STATUS_PENDING_HRD,
\App\Models\EmployeeMovement::STATUS_SCHEDULED,
\App\Models\EmployeeMovement::STATUS_APPROVED,
\App\Models\EmployeeMovement::STATUS_APPLY_FAILED,
\App\Models\EmployeeMovement::STATUS_REJECTED,
], true);
};
@endphp

<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4 gap-2">
            <div>
                <h4 class="fw-bold mb-1">Perubahan posisi</h4>
                <small class="text-muted">Ajukan promosi, demosi, dan mutasi dengan approval HOD lalu HRD sebelum master karyawan berubah.</small>
            </div>
            @if($canCreateMovement)
            <div class="ms-md-auto">
                <a href="{{ route('employee-movements.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Buat Pengajuan
                </a>
            </div>
            @endif
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <form method="GET" action="{{ route('employee-movements.index') }}" class="row g-3 align-items-end">
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">Cari</label>
                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="NIK, nama, atau no referensi">
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Jenis</label>
                        <select name="movement_type" class="form-select">
                            <option value="">Semua jenis</option>
                            @foreach($typeOptions as $value => $label)
                            <option value="{{ $value }}" {{ ($filters['movement_type'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Semua status</option>
                            @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}" {{ ($filters['status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Dari Tanggal</label>
                        <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Sampai Tanggal</label>
                        <input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
                    </div>
                    <div class="col-lg-1 col-md-6">
                        <label class="form-label">Tampil</label>
                        <select name="per_page" class="form-select">
                            @foreach($perPageOptions as $option)
                            <option value="{{ $option }}" {{ (int) ($filters['per_page'] ?? 20) === $option ? 'selected' : '' }}>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="{{ route('employee-movements.index') }}" class="btn btn-light border">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 130px;">Tanggal Efektif</th>
                                <th style="width: 130px;">Jenis</th>
                                <th>Karyawan</th>
                                <th>Perubahan</th>
                                <th style="width: 160px;">Status</th>
                                <th style="width: 150px;">Approval HOD</th>
                                <th style="width: 150px;">Approval HRD</th>
                                <th style="width: 170px;">Diajukan Oleh</th>
                                <th style="width: 190px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($movements as $movement)
                            <tr>
                                <td>
                                    <strong>{{ optional($movement->effective_date)->format('d M Y') ?: '-' }}</strong>
                                    <small class="d-block text-muted">Dibuat {{ optional($movement->created_at)->format('d M Y H:i') ?: '-' }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $movement->type_badge_class }}">{{ $movement->type_label }}</span>
                                    <small class="d-block text-muted mt-1">{{ $movement->reference_number ?: 'Tanpa referensi' }}</small>
                                </td>
                                <td>
                                    <strong>{{ optional($movement->employee)->nama_karyawan ?: '-' }}</strong>
                                    <small class="d-block text-muted">{{ $movement->employee_nik }}</small>
                                </td>
                                <td>
                                    <div>{{ $movementLabel($movement) }}</div>
                                    <small class="d-block text-muted mt-1">{{ $movement->reason }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $movement->status_badge_class }}">{{ $movement->status_label }}</span>
                                    @if($movement->applied_at)
                                    <small class="d-block text-muted mt-1">Applied {{ optional($movement->applied_at)->format('d M Y H:i') }}</small>
                                    @endif
                                    @if($movement->application_error)
                                    <small class="d-block text-danger mt-1">{{ $movement->application_error }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($approvalVisible($movement))
                                    <span class="badge bg-{{ $movement->hod_status_badge_class }}">{{ $movement->hod_status_label }}</span>
                                    <small class="d-block text-muted mt-1">{{ optional($movement->hodProcessor)->name ?: '-' }}</small>
                                    @if($movement->hod_rejection_reason)
                                    <small class="d-block text-danger">{{ $movement->hod_rejection_reason }}</small>
                                    @endif
                                    @else
                                    <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($approvalVisible($movement))
                                    <span class="badge bg-{{ $movement->hrd_status_badge_class }}">{{ $movement->hrd_status_label }}</span>
                                    <small class="d-block text-muted mt-1">{{ optional($movement->hrdProcessor)->name ?: '-' }}</small>
                                    @if($movement->hrd_rejection_reason)
                                    <small class="d-block text-danger">{{ $movement->hrd_rejection_reason }}</small>
                                    @endif
                                    @else
                                    <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ optional($movement->creator)->name ?: '-' }}</div>
                                    <small class="d-block text-muted">{{ optional($movement->created_at)->format('d M Y H:i') ?: '-' }}</small>
                                </td>
                                <td>
                                    @if($movement->isPendingHod() && $canProcessHod($movement))
                                    <form action="{{ route('employee-movements.hod.process', $movement) }}" method="POST" data-approval-confirm-message="Setujui pengajuan ini di level HOD?">
                                        @csrf
                                        <div class="d-flex gap-2">
                                            <button name="action" value="1" class="btn btn-success btn-sm">
                                                <i class="fas fa-check me-1"></i> Approve
                                            </button>
                                            <button type="button" name="action" value="2" class="btn btn-danger btn-sm js-approval-reject" data-bs-toggle="modal" data-bs-target="#approvalRejectReasonModal">
                                                <i class="fas fa-times me-1"></i> Reject
                                            </button>
                                        </div>
                                    </form>
                                    @elseif($movement->isPendingHrd() && $canProcessHrd($movement))
                                    <form action="{{ route('employee-movements.hrd.process', $movement) }}" method="POST" data-approval-confirm-message="Setujui dan terapkan perubahan ini ke master karyawan?">
                                        @csrf
                                        <div class="d-flex gap-2">
                                            <button name="action" value="1" class="btn btn-success btn-sm">
                                                <i class="fas fa-check me-1"></i> Approve
                                            </button>
                                            <button type="button" name="action" value="2" class="btn btn-danger btn-sm js-approval-reject" data-bs-toggle="modal" data-bs-target="#approvalRejectReasonModal">
                                                <i class="fas fa-times me-1"></i> Reject
                                            </button>
                                        </div>
                                    </form>
                                    @else
                                    <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">Belum ada pengajuan Perubahan posisi.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $movements->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection