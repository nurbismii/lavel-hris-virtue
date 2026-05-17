@extends('layouts.app')

@section('title', 'Buat Kontrak Elektronik')

@push('styles')
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" />
<style>
    .contract-form-note {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        color: #475569;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4 gap-2">
            <div>
                <h4 class="fw-bold mb-1">Buat Kontrak</h4>
                <small class="text-muted">Pilih metode tanda tangan elektronik atau manual sesuai proses HR.</small>
            </div>
            <div class="ms-md-auto">
                <a href="{{ route('electronic-contracts.index') }}" class="btn btn-light">Kembali</a>
            </div>
        </div>

        @if($templates->isEmpty())
            <div class="alert alert-warning">
                Belum ada template aktif. Buat template kontrak terlebih dahulu sebelum generate kontrak karyawan.
            </div>
        @endif

        <form action="{{ route('electronic-contracts.store') }}" method="POST">
            @csrf
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Karyawan</label>
                                    <select
                                        name="nik"
                                        class="form-select js-contract-employee-select @error('nik') is-invalid @enderror"
                                        data-search-url="{{ route('electronic-contracts.employees.search') }}"
                                        data-placeholder="Ketik minimal 2 karakter nama atau NIK">
                                        @if($selectedEmployee)
                                            <option value="{{ $selectedEmployee->nik }}" selected>
                                                {{ $selectedEmployee->nama_karyawan }} - {{ $selectedEmployee->nik }} | {{ $selectedEmployee->posisi ?: '-' }}
                                            </option>
                                        @endif
                                    </select>
                                    @error('nik')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Tipe Kontrak</label>
                                    <select name="contract_type" id="contractType" class="form-select @error('contract_type') is-invalid @enderror">
                                        <option value="">-- Pilih Tipe --</option>
                                        @foreach($typeOptions as $value => $label)
                                            <option value="{{ $value }}" {{ old('contract_type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('contract_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Template</label>
                                    <select name="contract_template_id" id="contractTemplate" class="form-select @error('contract_template_id') is-invalid @enderror">
                                        <option value="">-- Pilih Template --</option>
                                        @foreach($templates as $template)
                                            <option
                                                value="{{ $template->id }}"
                                                data-contract-type="{{ $template->contract_type }}"
                                                {{ (string) old('contract_template_id') === (string) $template->id ? 'selected' : '' }}>
                                                {{ $template->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('contract_template_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-6">
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

                                <div class="col-md-4">
                                    <label class="form-label">No Kontrak</label>
                                    <input type="text" name="contract_number" class="form-control @error('contract_number') is-invalid @enderror" value="{{ old('contract_number') }}">
                                    @error('contract_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Kode Kontrak</label>
                                    <input type="text" name="contract_code" class="form-control @error('contract_code') is-invalid @enderror" value="{{ old('contract_code') }}">
                                    @error('contract_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">No PKWT</label>
                                    <input type="text" name="pkwt_number" class="form-control @error('pkwt_number') is-invalid @enderror" value="{{ old('pkwt_number') }}">
                                    @error('pkwt_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Jenis Kelamin</label>
                                    <select name="gender" id="genderInput" class="form-select @error('gender') is-invalid @enderror">
                                        <option value="">-- Pilih --</option>
                                        <option value="L" {{ old('gender') === 'L' ? 'selected' : '' }}>Laki-laki</option>
                                        <option value="P" {{ old('gender') === 'P' ? 'selected' : '' }}>Perempuan</option>
                                    </select>
                                    @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Status Pernikahan</label>
                                    <input type="text" name="marital_status" id="maritalStatusInput" class="form-control @error('marital_status') is-invalid @enderror" value="{{ old('marital_status') }}">
                                    @error('marital_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Jabatan</label>
                                    <input type="text" name="position" id="positionInput" class="form-control @error('position') is-invalid @enderror" value="{{ old('position') }}">
                                    @error('position')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Alamat</label>
                                    <textarea name="address" id="addressInput" rows="2" class="form-control @error('address') is-invalid @enderror">{{ old('address') }}</textarea>
                                    @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Durasi Kontrak</label>
                                    <input type="text" name="contract_duration" class="form-control @error('contract_duration') is-invalid @enderror" value="{{ old('contract_duration') }}" placeholder="Contoh: 12 bulan">
                                    @error('contract_duration')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Tanggal Mulai Kontrak</label>
                                    <input type="date" name="contract_start_date" class="form-control @error('contract_start_date') is-invalid @enderror" value="{{ old('contract_start_date') }}">
                                    @error('contract_start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Tanggal Berakhir Kontrak</label>
                                    <input type="date" name="contract_end_date" class="form-control @error('contract_end_date') is-invalid @enderror" value="{{ old('contract_end_date') }}">
                                    @error('contract_end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Gaji / Upah Terbaru</label>
                                    <input type="number" min="0" step="1" name="salary" class="form-control @error('salary') is-invalid @enderror" value="{{ old('salary', 0) }}">
                                    @error('salary')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Uang Makan</label>
                                    <input type="number" min="0" step="1" name="meal_allowance" class="form-control @error('meal_allowance') is-invalid @enderror" value="{{ old('meal_allowance', 0) }}">
                                    @error('meal_allowance')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-12 js-addendum-fields">
                                    <div class="contract-form-note p-3">
                                        <div class="fw-semibold mb-1">Data Adendum</div>
                                        <small>Nomor adendum dibuat otomatis: AD-{urutan}/PKWT/{NIK}/HRD-VDNI/{bulan romawi}/{tahun}. Bulan dan tahun diambil dari tanggal perpanjangan pertama berakhir.</small>
                                    </div>
                                </div>
                                <div class="col-md-4 js-addendum-fields">
                                    <label class="form-label">Durasi Perpanjangan Pertama</label>
                                    <input type="text" name="first_extension_duration" class="form-control @error('first_extension_duration') is-invalid @enderror" value="{{ old('first_extension_duration') }}" placeholder="Contoh: 6 bulan">
                                    @error('first_extension_duration')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4 js-addendum-fields">
                                    <label class="form-label">Tanggal Perpanjangan Pertama Berakhir</label>
                                    <input type="date" name="first_extension_end_date" class="form-control @error('first_extension_end_date') is-invalid @enderror" value="{{ old('first_extension_end_date') }}">
                                    @error('first_extension_end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4 js-addendum-fields">
                                    <label class="form-label">Klausul Adendum 1</label>
                                    <select name="clause_key" class="form-select @error('clause_key') is-invalid @enderror">
                                        <option value="">-- Pilih Klausul --</option>
                                        @foreach($clauseOptions as $key => $name)
                                            <option value="{{ $key }}" {{ old('clause_key') === $key ? 'selected' : '' }}>
                                                {{ $name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Jika adendum ke-2 dan seterusnya, sistem otomatis memakai Klausul 2.</small>
                                    @error('clause_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-primary" type="submit" {{ $templates->isEmpty() ? 'disabled' : '' }}>
                            <i class="fas fa-file-signature me-1"></i> Buat Kontrak
                        </button>
                        <a href="{{ route('electronic-contracts.index') }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="mb-2">Catatan Alur</h5>
                            <ul class="small text-muted ps-3 mb-0">
                                <li>Metode elektronik membuat kontrak tampil di menu Kontrak Elektronik karyawan aktif.</li>
                                <li>Metode manual dipakai untuk kontrak cetak lalu hasil scan diunggah kembali oleh HR.</li>
                                <li>PDF final elektronik baru disimpan setelah karyawan menandatangani.</li>
                                <li>Audit log mencatat pembuat kontrak, pembuka dokumen, dan tanda tangan.</li>
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
    (function () {
        const contractType = document.getElementById('contractType');
        const templateSelect = document.getElementById('contractTemplate');
        const addendumFields = Array.from(document.querySelectorAll('.js-addendum-fields'));
        const allTemplateOptions = Array.from(templateSelect.querySelectorAll('option'));

        $('.js-contract-employee-select').select2({
            width: '100%',
            placeholder: $('.js-contract-employee-select').data('placeholder'),
            minimumInputLength: 2,
            ajax: {
                url: $('.js-contract-employee-select').data('search-url'),
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term,
                        page: params.page || 1
                    };
                },
                processResults: function (data) {
                    return data;
                }
            }
        }).on('select2:select', function (event) {
            const employee = event.params.data.employee || {};

            if (employee.position && !document.getElementById('positionInput').value) {
                document.getElementById('positionInput').value = employee.position;
            }

            if (employee.gender && !document.getElementById('genderInput').value) {
                document.getElementById('genderInput').value = employee.gender;
            }

            if (employee.marital_status && !document.getElementById('maritalStatusInput').value) {
                document.getElementById('maritalStatusInput').value = employee.marital_status;
            }

            if (employee.address && !document.getElementById('addressInput').value) {
                document.getElementById('addressInput').value = employee.address;
            }
        });

        function syncTemplateOptions() {
            const selectedType = contractType.value;

            allTemplateOptions.forEach(function (option, index) {
                if (index === 0) {
                    option.hidden = false;
                    return;
                }

                const shouldShow = !selectedType || option.dataset.contractType === selectedType;
                option.hidden = !shouldShow;

                if (!shouldShow && option.selected) {
                    option.selected = false;
                }
            });

            const showAddendum = selectedType === 'addendum_pkwt';
            addendumFields.forEach(function (field) {
                field.style.display = showAddendum ? '' : 'none';
            });
        }

        contractType.addEventListener('change', syncTemplateOptions);
        syncTemplateOptions();
    })();
</script>
@endpush
