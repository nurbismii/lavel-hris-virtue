@extends('layouts.app')

@section('title', 'Kontrak Elektronik')

@push('styles')
<style>
    .electronic-contracts-page .form-control,
    .electronic-contracts-page .form-select {
        min-height: 42px;
        border-radius: 8px;
        font-size: 0.875rem;
        line-height: 1.5;
    }

    .electronic-contracts-page textarea.form-control {
        min-height: 120px;
    }

    .electronic-contracts-page input[type="file"].form-control {
        padding-top: 0.43rem;
        padding-bottom: 0.43rem;
    }

    .electronic-contracts-page .btn {
        min-height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .electronic-contracts-page .contract-select-cell {
        width: 44px;
        text-align: center;
        vertical-align: middle;
    }

    .electronic-contracts-page .bulk-action-bar {
        border: 1px solid #e9edf3;
        border-radius: 8px;
        background: #f8fafc;
        padding: 0.75rem 1rem;
    }
</style>
@endpush

@section('content')
<div class="container-fluid electronic-contracts-page">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4 gap-2">
            <div>
                <h4 class="fw-bold mb-1">Kontrak Elektronik</h4>
                <small class="text-muted">Generate kontrak, pantau tanda tangan elektronik/manual, dan buka arsip PDF final.</small>
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

        @if(session('bulk_generate_nik_result'))
            @php
                $bulkResult = session('bulk_generate_nik_result');
                $bulkSuccessSummary = collect($bulkResult['successes'] ?? [])
                    ->take(8)
                    ->map(function ($item) {
                        return ($item['name'] ?: 'Kontrak #' . $item['contract_id']) . ' -> ' . $item['employee_nik'];
                    })
                    ->join(', ');
                $bulkFailureSummary = collect($bulkResult['failures'] ?? [])
                    ->take(8)
                    ->map(function ($item) {
                        return ($item['name'] ?: 'Kontrak #' . $item['contract_id']) . ': ' . $item['message'];
                    })
                    ->join(' | ');
            @endphp
            <div class="alert {{ $bulkResult['failed_count'] > 0 ? 'alert-warning' : 'alert-success' }} shadow-sm">
                <div class="fw-semibold mb-1">
                    Generate NIK massal: {{ number_format($bulkResult['success_count']) }} berhasil, {{ number_format($bulkResult['failed_count']) }} gagal/dilewati.
                </div>
                @if(!empty($bulkResult['successes']))
                    <div class="small">
                        Berhasil:
                        {{ $bulkSuccessSummary }}
                        @if(count($bulkResult['successes']) > 8)
                            , dan {{ count($bulkResult['successes']) - 8 }} lainnya
                        @endif
                    </div>
                @endif
                @if(!empty($bulkResult['failures']))
                    <div class="small mt-1">
                        Perlu dicek:
                        {{ $bulkFailureSummary }}
                        @if(count($bulkResult['failures']) > 8)
                            | dan {{ count($bulkResult['failures']) - 8 }} lainnya
                        @endif
                    </div>
                @endif
            </div>
        @endif

        <div class="d-flex flex-wrap gap-2 mb-3">
            @foreach($quickFilterOptions as $value => $label)
                @php
                    $isActiveQuickFilter = ($filters['quick_filter'] ?? 'all') === $value;
                    $quickFilterQuery = array_merge(request()->except(['quick_filter', 'page']), ['quick_filter' => $value]);
                @endphp
                <a
                    href="{{ route('electronic-contracts.index', $quickFilterQuery) }}"
                    class="btn btn-sm {{ $isActiveQuickFilter ? 'btn-primary' : 'btn-outline-secondary' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <form action="{{ route('electronic-contracts.import-pkwt-vhire') }}" method="POST" enctype="multipart/form-data" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-lg-4">
                        <label class="form-label">Import PKWT 1 untuk V-Hire</label>
                        <input type="file" name="file" class="form-control @error('file') is-invalid @enderror" accept=".xlsx,.xls">
                        @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label">Metode Tanda Tangan</label>
                        <select name="signing_method" class="form-select @error('signing_method') is-invalid @enderror">
                            @foreach($signingMethodOptions as $value => $label)
                                <option value="{{ $value }}" {{ old('signing_method', \App\Models\EmployeeContract::SIGNING_METHOD_ELECTRONIC) === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('signing_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-5 d-flex flex-wrap gap-2">
                        <a href="{{ route('electronic-contracts.template-import-pkwt-vhire') }}" class="btn btn-outline-primary">
                            <i class="fas fa-download me-1"></i> Download Template
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-file-import me-1"></i> Import & Kirim ke V-Hire
                        </button>
                        <a href="{{ route('import-histories.index', ['import_type' => \App\Models\ImportHistory::TYPE_PKWT_ONE_CONTRACT]) }}" class="btn btn-outline-secondary">
                            Riwayat Import
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <input type="hidden" name="quick_filter" value="{{ $filters['quick_filter'] ?? 'all' }}">
                    <div class="col-md-3">
                        <label class="form-label">Cari</label>
                        <input type="text" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="NIK, nama, nomor kontrak">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tipe</label>
                        <select name="contract_type" class="form-select">
                            <option value="">Semua Tipe</option>
                            @foreach($typeOptions as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['contract_type'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Semua Status</option>
                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tampilkan</label>
                        <select name="per_page" class="form-select">
                            @foreach($perPageOptions as $option)
                                <option value="{{ $option }}" {{ (int) ($filters['per_page'] ?? 20) === $option ? 'selected' : '' }}>{{ $option }} data</option>
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
                <form action="{{ route('electronic-contracts.bulk-generate-nik-activation') }}" method="POST" id="bulkGenerateNikForm">
                    @csrf
                    <div class="bulk-action-bar d-flex flex-wrap align-items-center gap-2 mb-3">
                        <button type="submit" class="btn btn-success" id="bulkGenerateNikButton" disabled>
                            <i class="fas fa-id-card me-1"></i> Generate NIK Terpilih
                        </button>
                        <span class="small text-muted" id="bulkGenerateNikCounter">0 kontrak dipilih</span>
                        @error('contract_ids')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="contract-select-cell">
                                        <input type="checkbox" class="form-check-input" id="selectAllGenerateNik">
                                    </th>
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
                                    @php
                                        $canBulkGenerateNik = $contract->contract_type === \App\Models\ContractTemplate::TYPE_PKWT_1
                                            && $contract->signature_status === \App\Models\EmployeeContract::SIGNATURE_STATUS_SIGNED
                                            && blank($contract->nik)
                                            && blank($contract->employee_nik)
                                            && filled($contract->onboarding_candidate_id);
                                    @endphp
                                    <tr>
                                        <td class="contract-select-cell">
                                            <input
                                                type="checkbox"
                                                name="contract_ids[]"
                                                value="{{ $contract->id }}"
                                                class="form-check-input js-bulk-generate-nik"
                                                {{ $canBulkGenerateNik ? '' : 'disabled' }}>
                                        </td>
                                        <td>
                                            <div class="fw-semibold">{{ $contract->display_employee_name }}</div>
                                            <small class="text-muted">
                                                {{ $contract->nik ?: ('Candidate: ' . ($contract->candidate_code ?: '-')) }}
                                            </small>
                                            @if($contract->vhire_candidate_id || $contract->onboarding_candidate_id)
                                                <div><span class="badge bg-info">V-Hire</span></div>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $contract->type_label }}
                                            <div class="small text-muted">{{ $contract->signing_method_label }}</div>
                                        </td>
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
                                                    'rejected' => 'danger',
                                                ][$contract->status] ?? 'secondary';
                                            @endphp
                                            <span class="badge bg-{{ $badge }}">{{ $contract->status_label }}</span>
                                            <div class="small text-muted">{{ $contract->signature_status_label }}</div>
                                        </td>
                                        <td>{{ optional($contract->created_at)->format('d M Y H:i') }}</td>
                                        <td class="text-nowrap">
                                            <a href="{{ route('electronic-contracts.show', $contract) }}" class="btn btn-sm btn-primary">Detail</a>
                                            <a href="{{ route('electronic-contracts.pdf', $contract) }}" target="_blank" class="btn btn-sm btn-outline-secondary">PDF</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">Belum ada kontrak elektronik.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </form>

                <div class="mt-3">
                    {{ $contracts->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('bulkGenerateNikForm');
        const selectAll = document.getElementById('selectAllGenerateNik');
        const button = document.getElementById('bulkGenerateNikButton');
        const counter = document.getElementById('bulkGenerateNikCounter');
        const checkboxes = Array.from(document.querySelectorAll('.js-bulk-generate-nik'));
        const selectableCheckboxes = checkboxes.filter((checkbox) => !checkbox.disabled);

        function updateBulkState() {
            const checkedCount = selectableCheckboxes.filter((checkbox) => checkbox.checked).length;
            const totalSelectable = selectableCheckboxes.length;

            button.disabled = checkedCount === 0;
            counter.textContent = checkedCount + ' kontrak dipilih';

            if (!selectAll) {
                return;
            }

            selectAll.disabled = totalSelectable === 0;
            selectAll.checked = totalSelectable > 0 && checkedCount === totalSelectable;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < totalSelectable;
        }

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                selectableCheckboxes.forEach((checkbox) => {
                    checkbox.checked = selectAll.checked;
                });
                updateBulkState();
            });
        }

        checkboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', updateBulkState);
        });

        if (form) {
            form.addEventListener('submit', function (event) {
                const checkedCount = selectableCheckboxes.filter((checkbox) => checkbox.checked).length;

                if (checkedCount === 0) {
                    event.preventDefault();
                    window.AppDialog.alert(
                        'Tidak ada kontrak dipilih',
                        'Pilih minimal satu kontrak PKWT 1 yang sudah ditandatangani dan belum memiliki NIK.',
                        'warning'
                    );
                    return;
                }

                event.preventDefault();
                window.AppDialog.confirm({
                    title: 'Generate NIK massal?',
                    text: 'Generate NIK untuk ' + checkedCount + ' kontrak terpilih.',
                    icon: 'warning',
                    confirmButtonText: 'Ya, generate',
                    cancelButtonText: 'Batal'
                }).then(function (confirmed) {
                    if (!confirmed) {
                        return;
                    }

                    form.submit();
                });
            });
        }

        updateBulkState();
    });
</script>
@endpush
