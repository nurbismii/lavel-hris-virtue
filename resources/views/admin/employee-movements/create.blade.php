@extends('layouts.app')

@section('title', 'Buat Transisi Karyawan')

@push('styles')
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" />
<style>
    .movement-summary {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 14px 16px;
    }

    .movement-summary__label {
        color: #64748b;
        font-size: 0.78rem;
        text-transform: uppercase;
    }

    .movement-summary__value {
        color: #0f172a;
        font-weight: 600;
    }
</style>
@endpush

@section('content')
@php
$selectedEmployeePayload = $selectedEmployee ? [
'nik' => $selectedEmployee->nik,
'name' => $selectedEmployee->nama_karyawan,
'area' => optional(optional($selectedEmployee->departemen)->perusahaan)->kode_perusahaan ?: $selectedEmployee->area_kerja,
'posisi' => $selectedEmployee->posisi,
'jabatan' => $selectedEmployee->jabatan,
'departemen_id' => $selectedEmployee->departemen_id,
'departemen' => optional($selectedEmployee->departemen)->departemen,
'divisi_id' => $selectedEmployee->divisi_id,
'divisi' => optional($selectedEmployee->divisi)->nama_divisi,
] : null;
@endphp

<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4 gap-2">
            <div>
                <h4 class="fw-bold mb-1">Buat Transisi Karyawan</h4>
                <small class="text-muted">Pengajuan akan masuk workflow HOD lalu HRD. Master karyawan baru berubah setelah HRD approve.</small>
            </div>
            <div class="ms-md-auto">
                <a href="{{ route('employee-movements.index') }}" class="btn btn-light border">Kembali</a>
            </div>
        </div>

        <form
            action="{{ route('employee-movements.store') }}"
            method="POST"
            data-swal-confirm="Ajukan Transisi Karyawan ini ke workflow approval?"
            data-swal-title="Konfirmasi Pengajuan"
            data-swal-confirm-button="Ya, ajukan">
            @csrf

            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Karyawan</label>
                                    <select
                                        name="employee_nik"
                                        class="form-select js-movement-employee-select @error('employee_nik') is-invalid @enderror"
                                        data-search-url="{{ route('employee-movements.employees.search') }}"
                                        data-placeholder="Ketik minimal 2 karakter nama, NIK, posisi, atau jabatan">
                                        @if($selectedEmployee)
                                        <option value="{{ $selectedEmployee->nik }}" selected>
                                            {{ $selectedEmployee->nama_karyawan }} - {{ $selectedEmployee->nik }} | {{ $selectedEmployee->posisi ?: '-' }}
                                        </option>
                                        @endif
                                    </select>
                                    @error('employee_nik')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <small class="text-muted">Hanya karyawan aktif sesuai scope HR, HOD, atau delegasi HOD yang dapat dipilih.</small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Jenis Pergerakan</label>
                                    <select name="movement_type" id="movementType" class="form-select @error('movement_type') is-invalid @enderror">
                                        <option value="">-- Pilih Jenis --</option>
                                        @foreach($typeOptions as $value => $label)
                                        <option value="{{ $value }}" {{ old('movement_type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('movement_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Tanggal Efektif</label>
                                    <input type="date" name="effective_date" class="form-control @error('effective_date') is-invalid @enderror" value="{{ old('effective_date', now()->toDateString()) }}">
                                    @error('effective_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <small class="text-muted">Tanggal efektif tidak boleh lebih dari hari ini karena approval HRD final langsung menerapkan master.</small>
                                </div>

                                <div class="col-12">
                                    <div id="employeeCurrentSummary" class="movement-summary d-none">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <div class="movement-summary__label">NIK / Nama</div>
                                                <div class="movement-summary__value" data-current-identity>-</div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="movement-summary__label">Posisi / Jabatan Saat Ini</div>
                                                <div class="movement-summary__value" data-current-position>-</div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="movement-summary__label">Departemen / Divisi Saat Ini</div>
                                                <div class="movement-summary__value" data-current-organization>-</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 js-position-fields d-none">
                                    <div class="alert alert-light border mb-0">
                                        Promosi/demosi hanya mengubah posisi atau jabatan. Jika sekaligus pindah departemen/divisi, catat mutasi sebagai transaksi terpisah.
                                    </div>
                                </div>
                                <div class="col-md-6 js-position-fields d-none">
                                    <label class="form-label">Posisi Baru</label>
                                    <input type="text" name="new_posisi" class="form-control @error('new_posisi') is-invalid @enderror" value="{{ old('new_posisi') }}">
                                    @error('new_posisi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6 js-position-fields d-none">
                                    <label class="form-label">Jabatan Baru</label>
                                    <input type="text" name="new_jabatan" class="form-control @error('new_jabatan') is-invalid @enderror" value="{{ old('new_jabatan') }}">
                                    @error('new_jabatan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <small class="text-muted">Kosongkan jika jabatan tetap sama.</small>
                                </div>

                                <div class="col-12 js-mutation-fields d-none">
                                    <div class="alert alert-light border mb-0">
                                        Mutasi memindahkan karyawan ke departemen atau divisi lain. Jika departemen berubah dan divisi dikosongkan, divisi karyawan akan dikosongkan.
                                    </div>
                                </div>
                                <div class="col-md-6 js-mutation-fields d-none">
                                    <label class="form-label">Departemen Tujuan</label>
                                    <select name="new_departemen_id" id="newDepartemenId" class="form-select @error('new_departemen_id') is-invalid @enderror">
                                        <option value="">-- Pilih Departemen --</option>
                                        @php
                                        $departemenGroups = [];
                                        foreach ($departemens as $departemen) {
                                        $departemenGroups[optional($departemen->perusahaan)->kode_perusahaan ?: 'Lainnya'][] = $departemen;
                                        }
                                        @endphp
                                        @foreach($departemenGroups as $company => $items)
                                        <optgroup label="{{ $company }}">
                                            @foreach($items as $departemen)
                                            <option value="{{ $departemen->id }}" {{ (string) old('new_departemen_id') === (string) $departemen->id ? 'selected' : '' }}>
                                                {{ $departemen->departemen }}
                                            </option>
                                            @endforeach
                                        </optgroup>
                                        @endforeach
                                    </select>
                                    @error('new_departemen_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6 js-mutation-fields d-none">
                                    <label class="form-label">Divisi Tujuan</label>
                                    <select name="new_divisi_id" id="newDivisiId" class="form-select @error('new_divisi_id') is-invalid @enderror">
                                        <option value="">-- Pilih Divisi --</option>
                                        @foreach($divisis as $divisi)
                                        <option
                                            value="{{ $divisi->id }}"
                                            data-departemen="{{ $divisi->departemen_id }}"
                                            {{ (string) old('new_divisi_id') === (string) $divisi->id ? 'selected' : '' }}>
                                            {{ $divisi->nama_divisi }}
                                        </option>
                                        @endforeach
                                    </select>
                                    @error('new_divisi_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Nomor Referensi / SK</label>
                                    <input type="text" name="reference_number" class="form-control @error('reference_number') is-invalid @enderror" value="{{ old('reference_number') }}" placeholder="Opsional">
                                    @error('reference_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Alasan / Dasar Perubahan</label>
                                    <textarea name="reason" rows="4" class="form-control @error('reason') is-invalid @enderror" placeholder="Tuliskan dasar HR, evaluasi, atau keputusan manajemen.">{{ old('reason') }}</textarea>
                                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-primary" data-loading-text="Memproses...">
                            <i class="fas fa-paper-plane me-1"></i> Ajukan Perubahan
                        </button>
                        <a href="{{ route('employee-movements.index') }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="mb-2">Catatan Kontrol</h5>
                            <ul class="small text-muted ps-3 mb-0">
                                <li>Pengajuan menyimpan snapshot data lama dan data baru agar approval tetap auditable.</li>
                                <li>Master karyawan hanya berubah setelah HRD menyetujui pengajuan.</li>
                                <li>Jika data karyawan berubah saat menunggu approval, HRD final akan ditolak dan perlu pengajuan baru.</li>
                                <li>Promosi/demosi tidak otomatis menilai level jabatan; HR tetap menentukan jenis berdasarkan dokumen keputusan.</li>
                                <li>Gunakan tanggal efektif hari ini atau tanggal lampau untuk koreksi administratif yang sudah berlaku.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/js/plugin/select2/select2.full.min.js') }}"></script>
<script>
    (function() {
        const selectedEmployee = @json($selectedEmployeePayload);
        const movementType = document.getElementById('movementType');
        const positionFields = Array.from(document.querySelectorAll('.js-position-fields'));
        const mutationFields = Array.from(document.querySelectorAll('.js-mutation-fields'));
        const summary = document.getElementById('employeeCurrentSummary');
        const currentIdentity = summary.querySelector('[data-current-identity]');
        const currentPosition = summary.querySelector('[data-current-position]');
        const currentOrganization = summary.querySelector('[data-current-organization]');
        const departemenSelect = document.getElementById('newDepartemenId');
        const divisiSelect = document.getElementById('newDivisiId');
        const allDivisionOptions = Array.from(divisiSelect.querySelectorAll('option'));

        function fieldInputs(fields) {
            return fields.flatMap(function(field) {
                return Array.from(field.querySelectorAll('input, select, textarea'));
            });
        }

        function setFieldsVisible(fields, visible) {
            fields.forEach(function(field) {
                field.classList.toggle('d-none', !visible);
            });

            fieldInputs(fields).forEach(function(input) {
                input.disabled = !visible;
            });
        }

        function syncMovementFields() {
            const type = movementType.value;
            const isPositionMovement = type === 'promotion' || type === 'demotion';
            const isMutation = type === 'mutation';

            setFieldsVisible(positionFields, isPositionMovement);
            setFieldsVisible(mutationFields, isMutation);
        }

        function renderEmployee(employee) {
            if (!employee) {
                summary.classList.add('d-none');
                return;
            }

            currentIdentity.textContent = [employee.nik, employee.name].filter(Boolean).join(' - ') || '-';
            currentPosition.textContent = [employee.posisi, employee.jabatan].filter(Boolean).join(' / ') || '-';
            currentOrganization.textContent = [employee.area, employee.departemen, employee.divisi].filter(Boolean).join(' / ') || '-';
            summary.classList.remove('d-none');
        }

        function syncDivisionOptions() {
            const selectedDepartment = departemenSelect.value;
            const selectedDivision = divisiSelect.value;

            allDivisionOptions.forEach(function(option, index) {
                if (index === 0) {
                    option.hidden = false;
                    return;
                }

                const shouldShow = !selectedDepartment || option.dataset.departemen === selectedDepartment;
                option.hidden = !shouldShow;
            });

            const selectedOption = divisiSelect.options[divisiSelect.selectedIndex];
            if (selectedOption && selectedOption.hidden) {
                divisiSelect.value = '';
            } else {
                divisiSelect.value = selectedDivision;
            }
        }

        $('.js-movement-employee-select').select2({
            width: '100%',
            placeholder: $('.js-movement-employee-select').data('placeholder'),
            minimumInputLength: 2,
            ajax: {
                url: $('.js-movement-employee-select').data('search-url'),
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        q: params.term,
                        page: params.page || 1
                    };
                },
                processResults: function(data) {
                    return data;
                }
            }
        }).on('select2:select', function(event) {
            renderEmployee(event.params.data.employee || null);
        });

        movementType.addEventListener('change', syncMovementFields);
        departemenSelect.addEventListener('change', syncDivisionOptions);

        syncMovementFields();
        syncDivisionOptions();
        renderEmployee(selectedEmployee);
    })();
</script>
@endpush