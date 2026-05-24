@extends('layouts.app')

@section('title', 'Perpanjangan Kontrak')

@push('styles')
<style>
    .contract-renewal-delegate-select {
        min-width: 0;
    }

    .contract-renewal-delegate-select .select2-container {
        width: 100% !important;
    }

    .contract-renewal-delegate-select .select2-container--default .select2-selection--single {
        min-height: 31px;
        border-color: #ced4da;
    }

    .contract-renewal-delegate-select .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 29px;
        font-size: 0.8125rem;
    }

    .contract-renewal-delegate-select .select2-container--default .select2-selection--single .select2-selection__arrow {
        min-height: 29px;
    }
</style>
@endpush

@section('content')
@php
    $currentUser = auth()->user();
    $canManageRenewalWorkflow = $canManageRenewalWorkflow ?? ($currentUser && $currentUser->hasRole(['Super Admin', 'HR', 'HOD', 'Admin Divisi']) && $currentUser->hasMenuAccess('contract_renewal'));
    $canImportHistory = $canManageRenewalWorkflow && $currentUser && $currentUser->hasRole(['Super Admin', 'HR']);
    $canApproveHod = $currentUser && $currentUser->hasRole(['Super Admin', 'HOD']);
    $canApproveHrd = $currentUser && $currentUser->hasRole(['Super Admin', 'HR']);
@endphp

<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h3 class="text-primary mb-1">Perpanjangan Kontrak</h3>
                <small class="text-muted">Pantau kontrak yang akan berakhir, lakukan penilaian HOD atau delegasikan penilaian, lalu terbitkan adendum elektronik setelah approval HRD.</small>
            </div>
            <form method="GET" action="{{ route('contract-renewals.index') }}" class="d-flex flex-wrap gap-2 justify-content-md-end">
                <select name="area" class="form-select form-select-sm js-contract-area-filter" style="width: 190px;">
                    <option value="">Semua area</option>
                    @foreach($filterOptions['areas'] as $areaOption)
                        <option value="{{ $areaOption['code'] }}" {{ ($filters['area'] ?? null) === $areaOption['code'] ? 'selected' : '' }}>
                            {{ $areaOption['label'] }}
                        </option>
                    @endforeach
                </select>
                <select name="departemen_id" class="form-select form-select-sm js-contract-department-filter" style="width: 220px;">
                    <option value="">Semua departemen</option>
                    @php
                        $groupedDepartments = [];
                        foreach ($filterOptions['departemens'] as $departemen) {
                            $groupedDepartments[optional($departemen->perusahaan)->nama_perusahaan ?? 'Lainnya'][] = $departemen;
                        }
                    @endphp
                    @foreach($groupedDepartments as $companyName => $departemenItems)
                        <optgroup label="{{ $companyName }}">
                            @foreach($departemenItems as $departemen)
                                <option
                                    value="{{ $departemen->id }}"
                                    data-area="{{ optional($departemen->perusahaan)->kode_perusahaan }}"
                                    {{ (string) ($filters['departemen_id'] ?? '') === (string) $departemen->id ? 'selected' : '' }}
                                >
                                    {{ $departemen->departemen }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <select name="divisi_id" class="form-select form-select-sm js-contract-division-filter" style="width: 220px;">
                    <option value="">Semua divisi</option>
                    @foreach($filterOptions['divisis'] as $divisi)
                        <option
                            value="{{ $divisi->id }}"
                            data-area="{{ optional(optional($divisi->departemen)->perusahaan)->kode_perusahaan }}"
                            data-departemen="{{ $divisi->departemen_id }}"
                            {{ (string) ($filters['divisi_id'] ?? '') === (string) $divisi->id ? 'selected' : '' }}
                        >
                            {{ $divisi->nama_divisi }}
                        </option>
                    @endforeach
                </select>
                <select name="days" class="form-select form-select-sm" style="width: 150px;">
                    @foreach([30, 45, 60, 90] as $option)
                        <option value="{{ $option }}" {{ (int) $days === $option ? 'selected' : '' }}>{{ $option }} hari</option>
                    @endforeach
                </select>
                <select name="status" class="form-select form-select-sm" style="width: 220px;">
                    <option value="">Semua status workflow</option>
                    @foreach($statusOptions as $key => $label)
                        <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary btn-sm">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>
                <a href="{{ route('contract-renewals.index') }}" class="btn btn-light border btn-sm">
                    Reset
                </a>
            </form>
        </div>

        @if($canImportHistory)
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-7">
                            <h5 class="fw-semibold mb-1">Import History PKWT dan Adendum</h5>
                            <p class="text-muted small mb-0">Unggah file Excel berisi history kontrak karyawan.</p>
                        </div>
                        <div class="col-lg-5">
                            <form method="POST" action="{{ route('contract-renewals.import-history') }}" enctype="multipart/form-data" class="d-flex gap-2" data-loading-text="Mengunggah...">
                                @csrf
                                <input type="file" name="file" class="form-control form-control-sm @error('file') is-invalid @enderror" accept=".xlsx,.xls" required>
                                <button class="btn btn-outline-primary btn-sm text-nowrap">
                                    <i class="fas fa-upload me-1"></i> Import
                                </button>
                            </form>
                            @error('file')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                    <div>
                        <h5 class="fw-semibold mb-1">Kontrak Akan Berakhir</h5>
                        <p class="text-muted small mb-0">Data diambil dari history kontrak terbaru setiap karyawan dan hanya menampilkan kontrak yang belum dibuat workflow perpanjangannya.</p>
                    </div>
                    <span class="badge bg-light text-dark border">{{ $upcomingHistories->total() }} data</span>
                </div>

                @if($canManageRenewalWorkflow && $upcomingHistories->count() > 0)
                    <form
                        method="POST"
                        action="{{ route('contract-renewals.bulk-store') }}"
                        class="border rounded p-3 mb-3"
                        data-bulk-contract-form
                        data-confirm-message="Proses kontrak yang dipilih secara kolektif?"
                        data-loading-text="Memproses..."
                    >
                        @csrf
                        <input type="hidden" name="area" value="{{ $filters['area'] ?? '' }}">
                        <input type="hidden" name="departemen_id" value="{{ $filters['departemen_id'] ?? '' }}">
                        <input type="hidden" name="divisi_id" value="{{ $filters['divisi_id'] ?? '' }}">
                        <input type="hidden" name="days" value="{{ $days }}">
                        <input type="hidden" name="status" value="{{ $status }}">
                        <div data-bulk-history-inputs></div>

                        <div class="row g-2 align-items-end">
                            <div class="col-lg-3 col-md-6">
                                <label class="form-label small mb-1">Aksi Kolektif</label>
                                <select name="bulk_action" class="form-select form-select-sm js-bulk-action" required>
                                    <option value="create_workflow">Buat workflow terpilih</option>
                                    @if($canApproveHod)
                                        <option value="hod_direct">Buat workflow + Nilai HOD</option>
                                    @endif
                                </select>
                            </div>
                            <div class="col-lg-2 col-md-6">
                                <label class="form-label small mb-1">Durasi HOD</label>
                                <select name="assessment_months" class="form-select form-select-sm js-bulk-hod-field">
                                    <option value="">Pilih bulan</option>
                                    @for($month = 1; $month <= 12; $month++)
                                        <option value="{{ $month }}">{{ $month }} bulan</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="col-lg-4 col-md-8">
                                <label class="form-label small mb-1">Catatan HOD</label>
                                <input type="text" name="assessment_note" class="form-control form-control-sm js-bulk-hod-field" placeholder="Opsional untuk penilaian HOD kolektif">
                            </div>
                            <div class="col-lg-3 col-md-4">
                                <button class="btn btn-primary btn-sm w-100">
                                    <i class="fas fa-tasks me-1"></i>
                                    Proses Terpilih
                                    <span class="badge bg-light text-dark ms-1 js-selected-contract-count">0</span>
                                </button>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2">Maksimal 100 kontrak per aksi. Pilihan mengikuti data pada halaman ini dan tetap divalidasi ulang di server.</small>
                    </form>
                @endif

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                @if($canManageRenewalWorkflow)
                                    <th style="width: 42px;">
                                        <input type="checkbox" class="form-check-input js-select-all-contracts" aria-label="Pilih semua kontrak pada halaman ini">
                                    </th>
                                @endif
                                <th>Karyawan</th>
                                <th>Kontrak Terakhir</th>
                                <th>History</th>
                                <th>Tanggal Akhir</th>
                                <th style="width: 170px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($upcomingHistories as $history)
                                @php $employee = optional($history->employee); @endphp
                                <tr>
                                    @if($canManageRenewalWorkflow)
                                        <td>
                                            <input type="checkbox" class="form-check-input js-contract-history-check" value="{{ $history->id }}" aria-label="Pilih kontrak {{ $history->nik }}">
                                        </td>
                                    @endif
                                    <td>
                                        <strong>{{ $employee->nama_karyawan ?: $history->employee_name ?: '-' }}</strong>
                                        <small class="d-block text-muted">{{ $history->nik }}</small>
                                        <small class="d-block text-muted">Area: {{ $employee->area_kerja ?: '-' }}</small>
                                        <small class="d-block text-muted">
                                            {{ optional($employee->departemen)->departemen ?? '-' }}
                                            /
                                            {{ optional($employee->divisi)->nama_divisi ?? '-' }}
                                        </small>
                                    </td>
                                    <td>
                                        <span>{{ $history->contract_number ?: '-' }}</span>
                                        <small class="d-block text-muted">Masuk: {{ optional($history->entry_date)->format('d M Y') ?: '-' }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">{{ $history->raw_history_type }}</span>
                                        <small class="d-block text-muted mt-1">Durasi: {{ $history->duration_label ?: '-' }}</small>
                                    </td>
                                    <td>
                                        <strong>{{ optional($history->contract_end_date)->format('d M Y') ?: '-' }}</strong>
                                    </td>
                                    <td>
                                        @if($canManageRenewalWorkflow)
                                            <form method="POST" action="{{ route('contract-renewals.store') }}" data-confirm-message="Buat workflow perpanjangan kontrak untuk karyawan ini?" data-loading-text="Membuat...">
                                                @csrf
                                                <input type="hidden" name="history_id" value="{{ $history->id }}">
                                                <button class="btn btn-primary btn-sm w-100">
                                                    <i class="fas fa-plus me-1"></i> Buat Workflow
                                                </button>
                                            </form>
                                        @else
                                            <small class="text-muted">Tidak ada aksi.</small>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canManageRenewalWorkflow ? 6 : 5 }}" class="text-center text-muted py-4">Tidak ada kontrak yang akan berakhir dalam filter hari ini, atau workflow-nya sudah dibuat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $upcomingHistories->links() }}
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                    <div>
                        <h5 class="fw-semibold mb-1">Workflow Perpanjangan</h5>
                        <p class="text-muted small mb-0">Urutan: HOD menilai langsung atau delegasi penilaian, approval HRD, lalu kontrak elektronik muncul di self service karyawan.</p>
                    </div>
                    <span class="badge bg-light text-dark border">{{ $renewals->total() }} workflow</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Karyawan</th>
                                <th>Status</th>
                                <th>Penilaian</th>
                                <th style="min-width: 260px;">Delegasi Penilaian</th>
                                <th style="min-width: 260px;">Penilaian / Approval HOD</th>
                                <th style="min-width: 240px;">Approval HRD</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($renewals as $renewal)
                                @php
                                    $employee = optional($renewal->employee);
                                    $delegate = optional($renewal->delegate);
                                    $newEndDate = $renewal->assessment_months && $renewal->current_contract_end_date
                                        ? $renewal->current_contract_end_date->copy()->addMonthsNoOverflow((int) $renewal->assessment_months)
                                        : null;
                                    $options = $delegateOptions[$renewal->id] ?? collect();
                                    $isAssignedDelegate = (string) $renewal->delegate_user_id === (string) optional($currentUser)->id;
                                    $assessor = optional($renewal->assessedBy);
                                    $canChooseAssessmentPath = in_array($renewal->status, [
                                        \App\Models\EmployeeContractRenewal::STATUS_PENDING_DELEGATION,
                                        \App\Models\EmployeeContractRenewal::STATUS_WAITING_DELEGATE_ASSESSMENT,
                                    ], true);
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $employee->nama_karyawan ?: '-' }}</strong>
                                        <small class="d-block text-muted">{{ $renewal->employee_nik }}</small>
                                        <small class="d-block text-muted">Area: {{ $employee->area_kerja ?: '-' }}</small>
                                        <small class="d-block text-muted">
                                            Akhir saat ini: {{ optional($renewal->current_contract_end_date)->format('d M Y') ?: '-' }}
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $renewal->status_badge_class }}">{{ $renewal->status_label }}</span>
                                        @if($renewal->generatedContract)
                                            <a href="{{ route('electronic-contracts.show', $renewal->generatedContract) }}" class="d-block small mt-1">
                                                {{ $renewal->generatedContract->display_number }}
                                            </a>
                                        @endif
                                    </td>
                                    <td>
                                        @if($renewal->assessment_months)
                                            <strong>{{ $renewal->assessment_months }} bulan</strong>
                                            <small class="d-block text-muted">Sampai {{ optional($newEndDate)->format('d M Y') ?: '-' }}</small>
                                            <small class="d-block text-muted">
                                                Penilai: {{ optional(optional($assessor)->employee)->nama_karyawan ?: $assessor->name ?: '-' }}
                                            </small>
                                            @if($renewal->assessment_note)
                                                <small class="d-block text-muted">{{ \Illuminate\Support\Str::limit($renewal->assessment_note, 90) }}</small>
                                            @endif
                                        @else
                                            <span class="text-muted">Belum dinilai</span>
                                        @endif
                                    </td>
                                    <td>
                                        <small class="d-block mb-2">Delegasi: {{ optional(optional($delegate)->employee)->nama_karyawan ?: $delegate->name ?: '-' }}</small>

                                        @if($canManageRenewalWorkflow && $canChooseAssessmentPath)
                                            <form method="POST" action="{{ route('contract-renewals.delegate', $renewal) }}" class="mb-2" data-loading-text="Menyimpan...">
                                                @csrf
                                                <div class="row g-2">
                                                    <div class="col-12 contract-renewal-delegate-select">
                                                        <select
                                                            name="delegate_user_id"
                                                            class="form-select js-contract-delegate-select"
                                                            data-placeholder="Cari nama atau NIK delegasi"
                                                            required
                                                        >
                                                            <option value="">Pilih delegasi</option>
                                                            @foreach($options as $candidate)
                                                                <option value="{{ $candidate->id }}" {{ (string) $renewal->delegate_user_id === (string) $candidate->id ? 'selected' : '' }}>
                                                                    {{ optional($candidate->employee)->nama_karyawan ?: $candidate->name }} - {{ $candidate->nik_karyawan }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-12">
                                                        <button class="btn btn-outline-primary btn-sm w-100">Delegasikan Penilaian</button>
                                                    </div>
                                                </div>
                                            </form>
                                        @endif

                                        @if($isAssignedDelegate && $renewal->status === \App\Models\EmployeeContractRenewal::STATUS_WAITING_DELEGATE_ASSESSMENT)
                                            <form method="POST" action="{{ route('contract-renewals.assessment', $renewal) }}" data-confirm-message="Kirim hasil penilaian perpanjangan kontrak ke HOD?" data-loading-text="Mengirim...">
                                                @csrf
                                                <div class="row g-2">
                                                    <div class="col-5">
                                                        <select name="assessment_months" class="form-select form-select-sm" required>
                                                            <option value="">Bulan</option>
                                                            @for($month = 1; $month <= 12; $month++)
                                                                <option value="{{ $month }}">{{ $month }}</option>
                                                            @endfor
                                                        </select>
                                                    </div>
                                                    <div class="col-7">
                                                        <input type="text" name="assessment_note" class="form-control form-control-sm" placeholder="Catatan delegasi">
                                                    </div>
                                                    <div class="col-12">
                                                        <button class="btn btn-success btn-sm w-100">Kirim Penilaian Delegasi</button>
                                                    </div>
                                                </div>
                                            </form>
                                        @elseif(!$canChooseAssessmentPath)
                                            <small class="text-muted">Tidak ada aksi delegasi.</small>
                                        @endif
                                    </td>
                                    <td>
                                        <small class="d-block text-muted mb-2">HOD: {{ $renewal->hod_status === 1 ? 'Disetujui' : ($renewal->hod_status === 2 ? 'Ditolak' : 'Menunggu') }}</small>

                                        @if($canApproveHod && $canChooseAssessmentPath)
                                            <form method="POST" action="{{ route('contract-renewals.assessment', $renewal) }}" data-confirm-message="Simpan penilaian dan approve HOD untuk pengajuan ini?" data-loading-text="Menyimpan...">
                                                @csrf
                                                <input type="hidden" name="assessment_mode" value="hod_direct">
                                                <div class="row g-2">
                                                    <div class="col-5">
                                                        <select name="assessment_months" class="form-select form-select-sm" required>
                                                            <option value="">Bulan</option>
                                                            @for($month = 1; $month <= 12; $month++)
                                                                <option value="{{ $month }}">{{ $month }}</option>
                                                            @endfor
                                                        </select>
                                                    </div>
                                                    <div class="col-7">
                                                        <input type="text" name="assessment_note" class="form-control form-control-sm" placeholder="Catatan HOD">
                                                    </div>
                                                    <div class="col-12">
                                                        <button class="btn btn-success btn-sm w-100">Nilai & Approve HOD</button>
                                                    </div>
                                                </div>
                                            </form>
                                        @endif

                                        @if($canApproveHod && $renewal->status === \App\Models\EmployeeContractRenewal::STATUS_WAITING_HOD_APPROVAL)
                                            <div class="d-flex flex-column gap-2">
                                                <form method="POST" action="{{ route('contract-renewals.hod.process', $renewal) }}" data-confirm-message="Setujui rekomendasi perpanjangan kontrak ini di level HOD?" data-loading-text="Memproses...">
                                                    @csrf
                                                    <input type="hidden" name="action" value="1">
                                                    <button class="btn btn-success btn-sm w-100">Approve HOD</button>
                                                </form>
                                                <form method="POST" action="{{ route('contract-renewals.hod.process', $renewal) }}" data-confirm-message="Tolak perpanjangan kontrak ini di level HOD?" data-loading-text="Memproses...">
                                                    @csrf
                                                    <input type="hidden" name="action" value="2">
                                                    <div class="input-group input-group-sm">
                                                        <input type="text" name="note" class="form-control" placeholder="Alasan tolak" required>
                                                        <button class="btn btn-outline-danger">Reject</button>
                                                    </div>
                                                </form>
                                            </div>
                                        @elseif(!$canApproveHod || (!$canChooseAssessmentPath && $renewal->status !== \App\Models\EmployeeContractRenewal::STATUS_WAITING_HOD_APPROVAL))
                                            <small class="text-muted">Tidak ada aksi HOD.</small>
                                        @endif
                                    </td>
                                    <td>
                                        <small class="d-block text-muted mb-2">HRD: {{ $renewal->hrd_status === 1 ? 'Disetujui' : ($renewal->hrd_status === 2 ? 'Ditolak' : 'Menunggu') }}</small>

                                        @if($canApproveHrd && $renewal->status === \App\Models\EmployeeContractRenewal::STATUS_WAITING_HRD_APPROVAL)
                                            <div class="d-flex flex-column gap-2">
                                                <form method="POST" action="{{ route('contract-renewals.hrd.process', $renewal) }}" data-confirm-message="Setujui dan buat adendum elektronik untuk karyawan ini?" data-loading-text="Membuat kontrak...">
                                                    @csrf
                                                    <input type="hidden" name="action" value="1">
                                                    <button class="btn btn-primary btn-sm w-100">Approve HRD & Buat Kontrak</button>
                                                </form>
                                                <form method="POST" action="{{ route('contract-renewals.hrd.process', $renewal) }}" data-confirm-message="Tolak perpanjangan kontrak ini di level HRD?" data-loading-text="Memproses...">
                                                    @csrf
                                                    <input type="hidden" name="action" value="2">
                                                    <div class="input-group input-group-sm">
                                                        <input type="text" name="note" class="form-control" placeholder="Alasan tolak" required>
                                                        <button class="btn btn-outline-danger">Reject</button>
                                                    </div>
                                                </form>
                                            </div>
                                        @else
                                            <small class="text-muted">Tidak ada aksi HRD.</small>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Belum ada workflow perpanjangan kontrak.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $renewals->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/js/plugin/select2/select2.full.min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        function setLoading(form) {
            const loadingText = form.dataset.loadingText || 'Memproses...';
            form.querySelectorAll('button[type="submit"], button:not([type])').forEach(function (button) {
                button.dataset.originalText = button.innerHTML;
                button.disabled = true;
                button.innerHTML = loadingText;
            });
        }

        function initDelegateSearch() {
            if (!window.jQuery || typeof window.jQuery.fn.select2 !== 'function') {
                return;
            }

            window.jQuery('.js-contract-delegate-select').each(function () {
                const select = window.jQuery(this);

                if (select.data('select2')) {
                    return;
                }

                select.select2({
                    width: '100%',
                    placeholder: this.dataset.placeholder || 'Cari nama atau NIK delegasi',
                    allowClear: true,
                    dropdownAutoWidth: true,
                    language: {
                        noResults: function () {
                            return 'Delegasi tidak ditemukan';
                        },
                        searching: function () {
                            return 'Mencari...';
                        }
                    },
                    matcher: function (params, data) {
                        if (window.jQuery.trim(params.term || '') === '') {
                            return data;
                        }

                        if (typeof data.text === 'undefined') {
                            return null;
                        }

                        const keyword = params.term.toLowerCase();
                        const text = data.text.toLowerCase();

                        return text.indexOf(keyword) > -1 ? data : null;
                    }
                });
            });
        }

        initDelegateSearch();

        const areaFilter = document.querySelector('.js-contract-area-filter');
        const departmentFilter = document.querySelector('.js-contract-department-filter');
        const divisionFilter = document.querySelector('.js-contract-division-filter');

        function syncOrganizationFilters() {
            if (!areaFilter || !departmentFilter || !divisionFilter) {
                return;
            }

            const selectedArea = areaFilter.value;
            let resetDepartment = false;

            Array.prototype.forEach.call(departmentFilter.options, function (option) {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }

                const visible = !selectedArea || option.dataset.area === selectedArea;
                option.hidden = !visible;

                if (!visible && option.selected) {
                    resetDepartment = true;
                }
            });

            if (resetDepartment) {
                departmentFilter.value = '';
            }

            const selectedDepartment = departmentFilter.value;
            let resetDivision = false;

            Array.prototype.forEach.call(divisionFilter.options, function (option) {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }

                const areaMatches = !selectedArea || option.dataset.area === selectedArea;
                const departmentMatches = !selectedDepartment || option.dataset.departemen === selectedDepartment;
                const visible = areaMatches && departmentMatches;

                option.hidden = !visible;

                if (!visible && option.selected) {
                    resetDivision = true;
                }
            });

            if (resetDivision) {
                divisionFilter.value = '';
            }
        }

        if (areaFilter && departmentFilter && divisionFilter) {
            areaFilter.addEventListener('change', syncOrganizationFilters);
            departmentFilter.addEventListener('change', syncOrganizationFilters);
            syncOrganizationFilters();
        }

        const selectAllContracts = document.querySelector('.js-select-all-contracts');
        const historyChecks = Array.prototype.slice.call(document.querySelectorAll('.js-contract-history-check'));
        const selectedContractCount = document.querySelector('.js-selected-contract-count');
        const bulkForm = document.querySelector('[data-bulk-contract-form]');
        const bulkAction = document.querySelector('.js-bulk-action');
        const bulkHodFields = Array.prototype.slice.call(document.querySelectorAll('.js-bulk-hod-field'));

        function selectedHistoryIds() {
            return historyChecks
                .filter(function (checkbox) {
                    return checkbox.checked;
                })
                .map(function (checkbox) {
                    return checkbox.value;
                });
        }

        function updateSelectedContractCount() {
            const totalSelected = selectedHistoryIds().length;

            if (selectedContractCount) {
                selectedContractCount.textContent = totalSelected;
            }

            if (selectAllContracts) {
                selectAllContracts.checked = historyChecks.length > 0 && totalSelected === historyChecks.length;
                selectAllContracts.indeterminate = totalSelected > 0 && totalSelected < historyChecks.length;
            }
        }

        function syncBulkActionFields() {
            if (!bulkAction) {
                return;
            }

            const isHodDirect = bulkAction.value === 'hod_direct';

            bulkHodFields.forEach(function (field) {
                field.disabled = !isHodDirect;

                if (field.name === 'assessment_months') {
                    field.required = isHodDirect;
                }
            });
        }

        if (selectAllContracts) {
            selectAllContracts.addEventListener('change', function () {
                historyChecks.forEach(function (checkbox) {
                    checkbox.checked = selectAllContracts.checked;
                });
                updateSelectedContractCount();
            });
        }

        historyChecks.forEach(function (checkbox) {
            checkbox.addEventListener('change', updateSelectedContractCount);
        });

        if (bulkAction) {
            bulkAction.addEventListener('change', syncBulkActionFields);
            syncBulkActionFields();
        }

        if (bulkForm) {
            bulkForm.addEventListener('submit', function (event) {
                if (bulkForm.dataset.submitted === '1') {
                    return;
                }

                const selectedIds = selectedHistoryIds();

                if (selectedIds.length === 0) {
                    event.preventDefault();

                    if (window.Swal) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Belum ada data dipilih',
                            text: 'Pilih minimal satu kontrak yang akan diproses.',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        window.alert('Pilih minimal satu kontrak yang akan diproses.');
                    }

                    return;
                }

                if (selectedIds.length > 100) {
                    event.preventDefault();

                    if (window.Swal) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Terlalu banyak data',
                            text: 'Maksimal 100 kontrak dapat diproses dalam satu kali aksi.',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        window.alert('Maksimal 100 kontrak dapat diproses dalam satu kali aksi.');
                    }

                    return;
                }

                const inputContainer = bulkForm.querySelector('[data-bulk-history-inputs]');
                inputContainer.innerHTML = '';

                selectedIds.forEach(function (historyId) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'history_ids[]';
                    input.value = historyId;
                    inputContainer.appendChild(input);
                });

                bulkForm.dataset.confirmMessage = 'Proses ' + selectedIds.length + ' kontrak terpilih secara kolektif?';
            });
        }

        updateSelectedContractCount();

        document.querySelectorAll('form[data-confirm-message], form[data-loading-text]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (form.dataset.submitted === '1') {
                    event.preventDefault();
                    return;
                }

                const message = form.dataset.confirmMessage;

                if (!message) {
                    form.dataset.submitted = '1';
                    setLoading(form);
                    return;
                }

                event.preventDefault();

                const submitForm = function () {
                    form.dataset.submitted = '1';
                    setLoading(form);
                    form.submit();
                };

                if (window.Swal) {
                    Swal.fire({
                        icon: 'question',
                        title: 'Konfirmasi',
                        text: message,
                        showCancelButton: true,
                        confirmButtonText: 'Ya, lanjutkan',
                        cancelButtonText: 'Batal'
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            submitForm();
                        }
                    });
                    return;
                }

                if (window.confirm(message)) {
                    submitForm();
                }
            });
        });
    });
</script>
@endpush
