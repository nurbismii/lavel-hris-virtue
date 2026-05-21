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
                <h4 class="fw-bold mb-1">Detail Kontrak</h4>
                <small class="text-muted">{{ $contract->display_number }} - {{ $contract->display_employee_name }}</small>
            </div>
            <div class="ms-md-auto d-flex flex-wrap gap-2">
                <a href="{{ route('electronic-contracts.index') }}" class="btn btn-light">Kembali</a>
                <a href="{{ route('electronic-contracts.preview', $contract) }}" target="_blank" class="btn btn-outline-secondary">Preview HTML</a>
                <a href="{{ route('electronic-contracts.pdf', $contract) }}" target="_blank" class="btn btn-primary">Buka PDF</a>
            </div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger shadow-sm">
                <div class="fw-semibold mb-1">Proses gagal.</div>
                <div class="small">{{ $errors->first() }}</div>
            </div>
        @endif

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
                                'rejected' => 'danger',
                            ][$contract->status] ?? 'secondary';
                        @endphp
                        <dl class="row mb-0">
                            <dt class="col-5">Status</dt>
                            <dd class="col-7"><span class="badge bg-{{ $badge }}">{{ $contract->status_label }}</span></dd>
                            <dt class="col-5">Tanda Tangan</dt>
                            <dd class="col-7">{{ $contract->signing_method_label }} / {{ $contract->signature_status_label }}</dd>
                            <dt class="col-5">Tipe</dt>
                            <dd class="col-7">{{ $contract->type_label }}</dd>
                            <dt class="col-5">NIK</dt>
                            <dd class="col-7">{{ $contract->nik ?: '-' }}</dd>
                            <dt class="col-5">Nama</dt>
                            <dd class="col-7">{{ $contract->display_employee_name }}</dd>
                            @if($contract->vhire_candidate_id || $contract->onboarding_candidate_id)
                                <dt class="col-5">Candidate</dt>
                                <dd class="col-7">{{ $contract->candidate_code ?: '-' }}</dd>
                                <dt class="col-5">No KTP</dt>
                                <dd class="col-7">{{ $contract->masked_no_ktp }}</dd>
                            @endif
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
                        <h5 class="mb-2">Flow Manual</h5>
                        <p class="small text-muted mb-3">Gunakan bagian ini jika kontrak ditandatangani offline lalu arsip scan/PDF disimpan di HRIS.</p>

                        @if($contract->manual_signed_file_path)
                            <dl class="row small mb-3">
                                <dt class="col-5">Status Review</dt>
                                <dd class="col-7">{{ $contract->manual_verification_status_label }}</dd>
                                <dt class="col-5">Diunggah</dt>
                                <dd class="col-7">{{ optional($contract->manual_uploaded_at)->format('d M Y H:i') ?: '-' }}</dd>
                            </dl>
                            <a href="{{ route('electronic-contracts.manual-signed-file.show', $contract) }}" target="_blank" class="btn btn-outline-secondary w-100 mb-3">
                                Buka File Manual
                            </a>
                        @endif

                            <form
                                action="{{ route('electronic-contracts.manual-signed-file.store', $contract) }}"
                                method="POST"
                                enctype="multipart/form-data"
                                data-swal-confirm="File kontrak manual akan disimpan sebagai arsip resmi HRIS."
                                data-swal-title="Simpan arsip kontrak manual?"
                                data-swal-confirm-button="Ya, simpan">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small">File PDF/JPG/PNG</label>
                                <input type="file" name="manual_signed_file" class="form-control @error('manual_signed_file') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png">
                                @error('manual_signed_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Status Review</label>
                                <select name="manual_verification_status" class="form-select @error('manual_verification_status') is-invalid @enderror">
                                    @foreach($manualVerificationStatusOptions as $value => $label)
                                        <option value="{{ $value }}" {{ old('manual_verification_status', $contract->manual_verification_status ?: \App\Models\EmployeeContract::MANUAL_VERIFICATION_PENDING) === $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('manual_verification_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Catatan</label>
                                <textarea name="manual_note" rows="2" class="form-control @error('manual_note') is-invalid @enderror">{{ old('manual_note', $contract->manual_note) }}</textarea>
                                @error('manual_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <button type="submit" class="btn btn-outline-primary w-100">
                                Simpan Arsip Manual
                            </button>
                        </form>
                    </div>
                </div>

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

                @if($contract->vhire_candidate_id || $contract->onboarding_candidate_id)
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h5 class="mb-2">Integrasi V-Hire</h5>
                            <dl class="row small mb-3">
                                <dt class="col-5">Visible V-Hire</dt>
                                <dd class="col-7">{{ $contract->visible_in_vhire ? 'Ya' : 'Tidak' }}</dd>
                                <dt class="col-5">Sync Kontrak</dt>
                                <dd class="col-7">{{ optional($contract->vhire_contract_synced_at)->format('d M Y H:i') ?: 'Belum sukses' }}</dd>
                                <dt class="col-5">Sync Aktivasi</dt>
                                <dd class="col-7">{{ optional($contract->vhire_activation_synced_at)->format('d M Y H:i') ?: 'Belum sukses' }}</dd>
                            </dl>

                            <form action="{{ route('electronic-contracts.retry-vhire-sync', $contract) }}" method="POST" class="mb-3">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    Retry Sync Kontrak ke V-Hire
                                </button>
                            </form>

                            @if(!$contract->nik)
                                @php
                                    $canGenerateEmployeeNik = $contract->contract_type === \App\Models\ContractTemplate::TYPE_PKWT_1
                                        && $contract->signature_status === \App\Models\EmployeeContract::SIGNATURE_STATUS_SIGNED;
                                @endphp

                                @if($canGenerateEmployeeNik)
                                    <form
                                        action="{{ route('electronic-contracts.generate-nik-activation', $contract) }}"
                                        method="POST"
                                        class="mb-3"
                                        data-swal-confirm="Sistem akan membuat data employee, generate NIK dari sequence terbesar, dan mengaktivasi kandidat ke HRIS."
                                        data-swal-title="Generate NIK baru?"
                                        data-swal-confirm-button="Ya, generate">
                                        @csrf
                                        <label class="form-label small">Generate NIK Karyawan</label>
                                        <button class="btn btn-primary w-100" type="submit">
                                            Generate NIK &amp; Aktivasi
                                        </button>
                                    </form>
                                @endif

                                <form
                                    action="{{ route('electronic-contracts.activate-vhire-candidate', $contract) }}"
                                    method="POST"
                                    data-swal-confirm="Kandidat akan ditautkan ke NIK HRIS dan kontrak akan disembunyikan dari V-Hire."
                                    data-swal-title="Aktivasi kandidat?"
                                    data-swal-confirm-button="Ya, aktivasi">
                                    @csrf
                                    <label class="form-label small">Aktivasi ke NIK HRIS</label>
                                    <div class="input-group">
                                        <input type="text" name="employee_nik" class="form-control @error('employee_nik') is-invalid @enderror" value="{{ old('employee_nik') }}" placeholder="Masukkan NIK employee">
                                        <button class="btn btn-success" type="submit">
                                            Aktivasi
                                        </button>
                                        @error('employee_nik')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </form>
                            @endif

                            @if($latestVhireSyncLogs->isNotEmpty())
                                <div class="table-responsive mt-3">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Waktu</th>
                                                <th>Operasi</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($latestVhireSyncLogs as $syncLog)
                                                <tr>
                                                    <td>{{ optional($syncLog->created_at)->format('d M H:i') }}</td>
                                                    <td>{{ $syncLog->operation }}</td>
                                                    <td>
                                                        <span class="badge bg-{{ $syncLog->status === 'success' ? 'success' : ($syncLog->status === 'failed' ? 'danger' : 'secondary') }}">
                                                            {{ $syncLog->status }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                @if($contract->status === \App\Models\EmployeeContract::STATUS_READY)
                    <form
                        action="{{ route('electronic-contracts.cancel', $contract) }}"
                        method="POST"
                        data-swal-confirm="Kontrak akan dibatalkan dan statusnya berubah menjadi cancelled."
                        data-swal-title="Batalkan kontrak?"
                        data-swal-confirm-button="Ya, batalkan"
                        data-swal-danger="1">
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
