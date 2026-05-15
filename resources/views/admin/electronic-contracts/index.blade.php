@extends('layouts.app')

@section('title', 'Kontrak Elektronik')

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4 gap-2">
            <div>
                <h4 class="fw-bold mb-1">Kontrak Elektronik</h4>
                <small class="text-muted">Generate kontrak, pantau status tanda tangan, dan buka arsip PDF final.</small>
            </div>
            <div class="ms-md-auto d-flex flex-wrap gap-2">
                @if($canManageFirstPartySignature)
                    <a href="{{ route('electronic-contracts.first-party-signature.edit') }}" class="btn btn-outline-primary">
                        <i class="fas fa-signature me-1"></i> Tanda Tangan Pihak Pertama
                    </a>
                @endif
                <a href="{{ route('electronic-contracts.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Buat Kontrak
                </a>
            </div>
        </div>

        @if($canManageFirstPartySignature && !$firstPartySignature)
            <div class="alert alert-warning shadow-sm">
                Tanda tangan master Pihak Pertama belum disimpan. Simpan sekali agar otomatis muncul pada PKWT, Translator, dan Adendum.
            </div>
        @endif

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Cari</label>
                        <input type="text" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="NIK, nama, nomor kontrak">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipe</label>
                        <select name="contract_type" class="form-select">
                            <option value="">Semua Tipe</option>
                            @foreach($typeOptions as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['contract_type'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Semua Status</option>
                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                        <a href="{{ route('electronic-contracts.index') }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Karyawan</th>
                                <th>Tipe</th>
                                <th>Nomor</th>
                                <th>Periode</th>
                                <th>Status</th>
                                <th>Dibuat</th>
                                <th style="width: 180px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($contracts as $contract)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ optional($contract->employee)->nama_karyawan ?? '-' }}</div>
                                        <small class="text-muted">{{ $contract->nik }}</small>
                                    </td>
                                    <td>{{ $contract->type_label }}</td>
                                    <td>
                                        <div>{{ $contract->display_number }}</div>
                                        <small class="text-muted">PKWT: {{ $contract->pkwt_number }}</small>
                                    </td>
                                    <td>
                                        <div>{{ optional($contract->contract_start_date)->format('d M Y') ?: '-' }}</div>
                                        <small class="text-muted">s/d {{ optional($contract->contract_end_date)->format('d M Y') ?: '-' }}</small>
                                    </td>
                                    <td>
                                        @php
                                            $badge = [
                                                'ready' => 'warning',
                                                'signed' => 'success',
                                                'cancelled' => 'secondary',
                                            ][$contract->status] ?? 'secondary';
                                        @endphp
                                        <span class="badge bg-{{ $badge }}">{{ $contract->status_label }}</span>
                                    </td>
                                    <td>{{ optional($contract->created_at)->format('d M Y H:i') }}</td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('electronic-contracts.show', $contract) }}" class="btn btn-sm btn-primary">Detail</a>
                                        <a href="{{ route('electronic-contracts.pdf', $contract) }}" target="_blank" class="btn btn-sm btn-outline-secondary">PDF</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Belum ada kontrak elektronik.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $contracts->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
