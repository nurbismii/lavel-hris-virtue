@extends('layouts.app')

@push('styles')
<style>
    .delegation-select2 .select2-container {
        width: 100% !important;
    }

    .delegation-select2 .select2-container--default .select2-selection--single {
        border-color: #ced4da;
        min-height: 38px;
        padding: 4px 8px;
    }

    .delegation-select2 .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 28px;
        padding-left: 0;
    }

    .delegation-select2 .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px;
    }
</style>
@endpush

@section('content')
@php
    $selectedDepartemen = old('departemen_id', $filters['departemen_id'] ?? '');
    $selectedDivisi = old('divisi_id', $filters['divisi_id'] ?? '');
    $selectedDelegate = (string) old('delegate_user_id', '');
    $selectedModules = collect(old('modules', [\App\Models\ApprovalDelegation::MODULE_ALL]))
        ->map(fn($module) => (string) $module)
        ->all();
@endphp

<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h3 class="text-primary mb-1">Delegasi Approval HOD</h3>
                <small class="text-muted">Atur karyawan dalam departemen/divisi yang boleh verifikasi pengajuan sebelum masuk ke HOD.</small>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="fw-semibold mb-1">Tambah Delegasi</h5>
                        <p class="text-muted small mb-3">Pilih scope, lalu sistem akan memuat kandidat karyawan secara otomatis.</p>

                        <form
                            method="POST"
                            action="{{ route('approval.delegations.store') }}"
                            id="delegation_form"
                            data-candidates-url="{{ route('approval.delegations.candidates') }}"
                            data-selected-delegate="{{ $selectedDelegate }}">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label">Departemen</label>
                                <select name="departemen_id" id="delegation_departemen_id" class="form-select">
                                    <option value="">Pilih departemen</option>
                                    @foreach($departemens as $departemen)
                                        <option value="{{ $departemen->id }}" {{ (string) $selectedDepartemen === (string) $departemen->id ? 'selected' : '' }}>
                                            {{ $departemen->departemen }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Divisi Opsional</label>
                                <select name="divisi_id" id="delegation_divisi_id" class="form-select">
                                    <option value="">Semua divisi dalam departemen</option>
                                    @foreach($departemens as $departemen)
                                        @foreach($departemen->divisi as $divisi)
                                            <option
                                                value="{{ $divisi->id }}"
                                                data-departemen-id="{{ $departemen->id }}"
                                                {{ (string) $selectedDivisi === (string) $divisi->id ? 'selected' : '' }}>
                                                {{ $divisi->nama_divisi }}
                                            </option>
                                        @endforeach
                                    @endforeach
                                </select>
                                <small class="text-muted">Kosongkan divisi jika delegasi berlaku untuk satu departemen.</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Modul Delegasi</label>
                                <div class="row g-2">
                                    @foreach($modules as $key => $label)
                                        <div class="col-sm-6">
                                            <label class="form-check border rounded p-2 h-100">
                                                <input
                                                    type="checkbox"
                                                    name="modules[]"
                                                    value="{{ $key }}"
                                                    class="form-check-input ms-0 me-2"
                                                    {{ in_array($key, $selectedModules, true) ? 'checked' : '' }}>
                                                <span class="form-check-label">{{ $label }}</span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                                @error('modules')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                                @error('modules.*')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Pilih beberapa modul sekaligus. Jika "Semua Modul" dicentang, pilihan spesifik lain akan diabaikan.</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Karyawan Delegasi</label>
                                <div class="delegation-select2">
                                    <select
                                        name="delegate_user_id"
                                        id="delegation_delegate_user_id"
                                        class="form-select @error('delegate_user_id') is-invalid @enderror"
                                        data-placeholder="Cari nama atau NIK karyawan"
                                        disabled>
                                        <option value="">Pilih departemen terlebih dahulu</option>
                                    </select>
                                </div>
                                @error('delegate_user_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted" id="delegation_candidate_help">Ketik nama atau NIK untuk mencari kandidat dalam scope terpilih.</small>
                            </div>

                            <div class="alert alert-info small" id="delegation_candidate_state">Pilih departemen untuk memuat kandidat delegasi.</div>

                            <button type="submit" id="delegation_submit_button" class="btn btn-primary w-100" disabled>
                                <i class="fas fa-user-check me-1"></i>
                                Simpan Delegasi
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                            <div>
                                <h5 class="fw-semibold mb-1">Delegasi Aktif dan Nonaktif</h5>
                                <p class="text-muted small mb-0">Gunakan tombol on/off untuk menghentikan delegasi tanpa menghapus histori.</p>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Delegasi</th>
                                        <th>Scope</th>
                                        <th>Modul</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($delegations as $delegation)
                                        <tr>
                                            <td>
                                                <strong>{{ optional(optional($delegation->delegate)->employee)->nama_karyawan ?: optional($delegation->delegate)->name }}</strong>
                                                <small class="d-block text-muted">{{ optional($delegation->delegate)->nik_karyawan }}</small>
                                                @if(auth()->user()->hasRole('Super Admin'))
                                                    <small class="d-block text-muted">HOD: {{ optional($delegation->hod)->name }}</small>
                                                @endif
                                            </td>
                                            <td>{{ $delegation->scope_label }}</td>
                                            <td>{{ $delegation->module_label }}</td>
                                            <td>
                                                @if($delegation->is_active)
                                                    <span class="badge bg-success">Aktif</span>
                                                @else
                                                    <span class="badge bg-secondary">Nonaktif</span>
                                                @endif
                                            </td>
                                            <td>
                                                <form method="POST" action="{{ route('approval.delegations.toggle', $delegation) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm {{ $delegation->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                                        {{ $delegation->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">Belum ada delegasi approval.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">
                            {{ $delegations->links() }}
                        </div>
                    </div>
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
        const form = document.getElementById('delegation_form');
        const departemenSelect = document.getElementById('delegation_departemen_id');
        const divisiSelect = document.getElementById('delegation_divisi_id');
        const delegateSelect = document.getElementById('delegation_delegate_user_id');
        const submitButton = document.getElementById('delegation_submit_button');
        const stateBox = document.getElementById('delegation_candidate_state');
        const helpText = document.getElementById('delegation_candidate_help');

        if (!form || !departemenSelect || !divisiSelect || !delegateSelect || !submitButton || !stateBox) {
            return;
        }

        const candidatesUrl = form.dataset.candidatesUrl;
        const initialSelectedDelegate = form.dataset.selectedDelegate || '';
        const placeholder = divisiSelect.querySelector('option[value=""]');
        const divisiOptions = Array.from(divisiSelect.options).filter((option) => option.value !== '');
        let latestRequestId = 0;

        function hasSelect2() {
            return window.jQuery && typeof window.jQuery.fn.select2 === 'function';
        }

        function refreshDelegateSearch() {
            if (!hasSelect2()) {
                return;
            }

            const $delegateSelect = window.jQuery(delegateSelect);

            if (!$delegateSelect.data('select2')) {
                $delegateSelect.select2({
                    width: '100%',
                    placeholder: delegateSelect.dataset.placeholder || 'Cari karyawan',
                    allowClear: true,
                    ajax: {
                        url: candidatesUrl,
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return {
                                departemen_id: departemenSelect.value,
                                divisi_id: divisiSelect.value,
                                q: params.term || ''
                            };
                        },
                        processResults: function (payload) {
                            return {
                                results: payload.results || []
                            };
                        },
                        cache: true
                    },
                    language: {
                        noResults: function () {
                            return 'Karyawan tidak ditemukan';
                        },
                        errorLoading: function () {
                            return 'Karyawan gagal dimuat';
                        },
                        searching: function () {
                            return 'Mencari...';
                        }
                    }
                });

                return;
            }

            $delegateSelect.trigger('change.select2');
        }

        function setState(type, message) {
            stateBox.className = 'alert alert-' + type + ' small';
            stateBox.textContent = message;
        }

        function resetDelegateOptions(message) {
            delegateSelect.innerHTML = '';

            const option = document.createElement('option');
            option.value = '';
            option.textContent = message;
            delegateSelect.appendChild(option);
            refreshDelegateSearch();
        }

        function syncDivisiOptions() {
            const selectedDepartemenId = departemenSelect.value;
            const selectedDivisiId = divisiSelect.value;
            let selectedDivisiStillAvailable = false;

            divisiOptions.forEach((option) => {
                const isAvailable = selectedDepartemenId !== '' && option.dataset.departemenId === selectedDepartemenId;

                option.hidden = !isAvailable;
                option.disabled = !isAvailable;

                if (isAvailable && option.value === selectedDivisiId) {
                    selectedDivisiStillAvailable = true;
                }
            });

            if (selectedDepartemenId === '' || !selectedDivisiStillAvailable) {
                divisiSelect.value = '';
            }

            if (placeholder) {
                placeholder.textContent = selectedDepartemenId === ''
                    ? 'Pilih departemen terlebih dahulu'
                    : 'Semua divisi dalam departemen';
            }

            divisiSelect.disabled = selectedDepartemenId === '';
        }

        function populateCandidates(candidates, limited, restoreSelected) {
            resetDelegateOptions(candidates.length ? 'Pilih karyawan' : 'Tidak ada kandidat');

            candidates.forEach((candidate) => {
                const option = document.createElement('option');
                option.value = candidate.id;
                option.textContent = candidate.label;
                delegateSelect.appendChild(option);
            });

            if (restoreSelected && initialSelectedDelegate !== '') {
                delegateSelect.value = initialSelectedDelegate;
            }

            const hasCandidates = candidates.length > 0;
            delegateSelect.disabled = !hasCandidates;
            submitButton.disabled = !hasCandidates;
            refreshDelegateSearch();

            if (!hasCandidates) {
                setState('warning', 'Tidak ada akun karyawan aktif pada scope ini.');
                return;
            }

            setState('success', candidates.length + ' kandidat tersedia untuk scope ini.');

            if (helpText) {
                helpText.textContent = limited
                    ? 'Kandidat dibatasi 500 akun pertama. Gunakan divisi atau ketik nama/NIK untuk mempersempit pencarian.'
                    : 'Ketik nama atau NIK untuk mencari kandidat dalam scope terpilih.';
            }
        }

        function loadCandidates(restoreSelected) {
            const departemenId = departemenSelect.value;
            const divisiId = divisiSelect.value;
            const requestId = ++latestRequestId;

            if (departemenId === '') {
                resetDelegateOptions('Pilih departemen terlebih dahulu');
                delegateSelect.disabled = true;
                submitButton.disabled = true;
                setState('info', 'Pilih departemen untuk memuat kandidat delegasi.');
                return;
            }

            resetDelegateOptions('Memuat kandidat...');
            delegateSelect.disabled = true;
            submitButton.disabled = true;
            setState('info', 'Memuat kandidat karyawan...');

            const url = new URL(candidatesUrl, window.location.href);
            url.searchParams.set('departemen_id', departemenId);

            if (divisiId !== '') {
                url.searchParams.set('divisi_id', divisiId);
            }

            fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json()
                        .catch(function () {
                            return {};
                        })
                        .then(function (payload) {
                            if (!response.ok || payload.success === false) {
                                throw new Error(payload.message || 'Kandidat gagal dimuat.');
                            }

                            return payload;
                        });
                })
                .then(function (payload) {
                    if (requestId !== latestRequestId) {
                        return;
                    }

                    populateCandidates(payload.data || [], Boolean(payload.limited), restoreSelected);
                })
                .catch(function (error) {
                    if (requestId !== latestRequestId) {
                        return;
                    }

                    resetDelegateOptions('Kandidat gagal dimuat');
                    delegateSelect.disabled = true;
                    submitButton.disabled = true;
                    setState('danger', error.message || 'Kandidat gagal dimuat.');
                });
        }

        departemenSelect.addEventListener('change', function () {
            syncDivisiOptions();
            loadCandidates(false);
        });

        divisiSelect.addEventListener('change', function () {
            loadCandidates(false);
        });

        syncDivisiOptions();
        refreshDelegateSearch();
        loadCandidates(true);
    });
</script>
@endpush
