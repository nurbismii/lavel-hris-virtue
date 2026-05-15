@extends('layouts.app')

@section('title', 'Detail Kontrak Elektronik')

@push('styles')
<style>
    .contract-preview-shell {
        background: #f3f4f6;
        border-radius: 10px;
        padding: 18px;
    }

    .contract-preview-page {
        background: #fff;
        border: 1px solid #e5e7eb;
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
        margin: 0 auto;
        max-width: 850px;
        min-height: 980px;
        padding: 42px;
    }

    .contract-preview-page table {
        width: 100%;
        border-collapse: collapse;
    }

    .contract-preview-page td,
    .contract-preview-page th {
        border: 1px solid #d1d5db;
        padding: 6px;
    }

    .contract-preview-page .contract-signature-slot {
        display: block;
        height: 86px;
        line-height: normal;
        margin: 4px 0;
        text-align: center;
    }

    .contract-preview-page .contract-signature-box {
        border: 0 !important;
        border-collapse: collapse;
        height: 86px;
        margin: 0;
        width: 100%;
    }

    .contract-preview-page .contract-signature-box td {
        border: 0 !important;
        height: 86px;
        padding: 0 !important;
        text-align: center;
        vertical-align: middle;
    }

    .contract-preview-page .contract-signature-image {
        height: 76px;
        max-width: 220px;
        vertical-align: middle;
    }

    .first-party-signature-preview {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 12px;
        text-align: center;
    }

    .first-party-signature-preview img {
        max-height: 90px;
        max-width: 100%;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4 gap-2">
            <div>
                <h4 class="fw-bold mb-1">Detail Kontrak Elektronik</h4>
                <small class="text-muted">{{ $contract->display_number }} - {{ optional($contract->employee)->nama_karyawan ?? $contract->nik }}</small>
            </div>
            <div class="ms-md-auto d-flex flex-wrap gap-2">
                <a href="{{ route('electronic-contracts.index') }}" class="btn btn-light">Kembali</a>
                <a href="{{ route('electronic-contracts.preview', $contract) }}" target="_blank" class="btn btn-outline-secondary">Preview HTML</a>
                <a href="{{ route('electronic-contracts.pdf', $contract) }}" target="_blank" class="btn btn-primary">Buka PDF</a>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h5 class="mb-3">Informasi Kontrak</h5>
                        @php
                            $badge = [
                                'ready' => 'warning',
                                'signed' => 'success',
                                'cancelled' => 'secondary',
                            ][$contract->status] ?? 'secondary';
                        @endphp
                        <dl class="row mb-0">
                            <dt class="col-5">Status</dt>
                            <dd class="col-7"><span class="badge bg-{{ $badge }}">{{ $contract->status_label }}</span></dd>
                            <dt class="col-5">Tipe</dt>
                            <dd class="col-7">{{ $contract->type_label }}</dd>
                            <dt class="col-5">NIK</dt>
                            <dd class="col-7">{{ $contract->nik }}</dd>
                            <dt class="col-5">Nama</dt>
                            <dd class="col-7">{{ optional($contract->employee)->nama_karyawan ?? '-' }}</dd>
                            <dt class="col-5">No PKWT</dt>
                            <dd class="col-7">{{ $contract->pkwt_number }}</dd>
                            <dt class="col-5">No Adendum</dt>
                            <dd class="col-7">{{ $contract->addendum_number ?: '-' }}</dd>
                            <dt class="col-5">Periode</dt>
                            <dd class="col-7">{{ optional($contract->contract_start_date)->format('d M Y') ?: '-' }} s/d {{ optional($contract->contract_end_date)->format('d M Y') ?: '-' }}</dd>
                            <dt class="col-5">Gaji</dt>
                            <dd class="col-7">Rp {{ number_format((float) $contract->salary, 0, ',', '.') }}</dd>
                            <dt class="col-5">Uang Makan</dt>
                            <dd class="col-7">Rp {{ number_format((float) $contract->meal_allowance, 0, ',', '.') }}</dd>
                        </dl>
                    </div>
                </div>

                @if($contract->signature)
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h5 class="mb-3">Tanda Tangan Pihak Kedua</h5>
                            <dl class="row mb-0">
                                <dt class="col-5">Waktu</dt>
                                <dd class="col-7">{{ optional($contract->signature->signed_at)->format('d M Y H:i') }}</dd>
                                <dt class="col-5">IP</dt>
                                <dd class="col-7">{{ $contract->signature->ip_address ?: '-' }}</dd>
                                <dt class="col-5">Hash PDF</dt>
                                <dd class="col-7 small text-break">{{ $contract->pdf_hash ?: '-' }}</dd>
                            </dl>
                        </div>
                    </div>
                @endif

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h5 class="mb-2">Tanda Tangan Pihak Pertama</h5>
                        <p class="small text-muted mb-3">Tanda tangan ini memakai master Pihak Pertama dan otomatis digunakan untuk semua tipe kontrak.</p>

                        @if($firstPartySignaturePreview)
                            <div class="first-party-signature-preview mb-3">
                                <img src="{{ $firstPartySignaturePreview }}" alt="Tanda tangan Pihak Pertama">
                                <div class="small text-muted mt-2">
                                    Disimpan {{ optional(optional($firstPartySignature)->signed_at)->format('d M Y H:i') ?: optional($contract->first_party_signed_at)->format('d M Y H:i') ?: '-' }}
                                </div>
                            </div>
                        @else
                            <div class="alert alert-warning small py-2">
                                Tanda tangan master Pihak Pertama belum disimpan.
                            </div>
                        @endif

                        @if($canManageFirstPartySignature)
                            <a href="{{ route('electronic-contracts.first-party-signature.edit') }}" class="btn btn-outline-primary w-100">
                                Kelola Tanda Tangan Master
                            </a>
                        @endif
                    </div>
                </div>

                @if($contract->status === \App\Models\EmployeeContract::STATUS_READY)
                    <form action="{{ route('electronic-contracts.cancel', $contract) }}" method="POST" onsubmit="return confirm('Batalkan kontrak ini?')">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger w-100">Batalkan Kontrak</button>
                    </form>
                @endif
            </div>

            <div class="col-lg-8">
                <div class="contract-preview-shell">
                    <div class="contract-preview-page">
                        {!! $html !!}

                        @if($contract->signature)
                            <hr>
                            <div class="small text-muted">
                                Dokumen ini telah ditandatangani secara elektronik pada
                                {{ optional($contract->signature->signed_at)->format('d M Y H:i') }}.
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card border-0 shadow-sm mt-3">
                    <div class="card-body">
                        <h5 class="mb-3">Audit Log Terakhir</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Waktu</th>
                                        <th>Event</th>
                                        <th>Aktor</th>
                                        <th>IP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($auditLogs as $log)
                                        <tr>
                                            <td>{{ optional($log->created_at)->format('d M Y H:i:s') }}</td>
                                            <td>{{ $log->event }}</td>
                                            <td>{{ $log->actor_name ?: '-' }}</td>
                                            <td>{{ $log->ip_address ?: '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3">Belum ada audit log.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
