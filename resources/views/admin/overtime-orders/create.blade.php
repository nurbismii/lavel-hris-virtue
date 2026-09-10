@extends('layouts.app')

@section('title', __('Buat Perintah Lembur'))

@push('styles')
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" />
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex justify-content-between align-items-center pt-2 pb-4">
            <div>
                <h4 class="fw-bold mb-1">{{ __('Buat Perintah Lembur') }}</h4>
                <small class="text-muted">{{ __('Perintah akan dikirim ke karyawan dan baru berlaku jika disetujui.') }}</small>
            </div>
            <a href="{{ route('overtime-orders.index') }}" class="btn btn-light">{{ __('Kembali') }}</a>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('overtime-orders.store') }}" method="POST" class="row g-3">
                    @csrf
                    <div class="col-md-12">
                        <label class="form-label">{{ __('Karyawan') }}</label>
                        <select
                            name="nik_karyawan"
                            class="form-select js-overtime-employee-select @error('nik_karyawan') is-invalid @enderror"
                            data-search-url="{{ route('overtime-orders.employees.search') }}"
                            data-placeholder="{{ __('Ketik minimal 2 karakter nama atau NIK karyawan') }}">
                            @if($selectedEmployee)
                                @php
                                    $selectedDetails = collect([
                                        optional(optional($selectedEmployee->departemen)->perusahaan)->kode_perusahaan,
                                        optional($selectedEmployee->departemen)->departemen,
                                        optional($selectedEmployee->divisi)->nama_divisi,
                                        optional($selectedEmployee->workPattern)->code ? 'Pola ' . optional($selectedEmployee->workPattern)->code : null,
                                    ])->filter()->implode(' | ');
                                @endphp
                                <option value="{{ $selectedEmployee->nik }}" selected>
                                    {{ $selectedEmployee->nama_karyawan }} - {{ $selectedEmployee->nik }}{{ $selectedDetails ? ' | ' . $selectedDetails : '' }}
                                </option>
                            @endif
                        </select>
                        <small class="text-muted">{{ __('Data hanya memuat karyawan aktif VDNI/VDNIP sesuai scope departemen/divisi Anda.') }}</small>
                        @error('nik_karyawan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Tipe Lembur') }}</label>
                        <select name="overtime_type" class="form-select @error('overtime_type') is-invalid @enderror">
                            <option value="">{{ __('-- Pilih Tipe --') }}</option>
                            @foreach ($typeOptions as $value => $label)
                                <option value="{{ $value }}" {{ old('overtime_type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('overtime_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Tanggal Lembur') }}</label>
                        <input type="date" name="overtime_date" class="form-control @error('overtime_date') is-invalid @enderror" value="{{ old('overtime_date') }}">
                        @error('overtime_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">{{ __('Jam Mulai') }}</label>
                        <input type="time" name="start_time" class="form-control @error('start_time') is-invalid @enderror" value="{{ old('start_time') }}">
                        @error('start_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">{{ __('Jam Selesai') }}</label>
                        <input type="time" name="end_time" class="form-control @error('end_time') is-invalid @enderror" value="{{ old('end_time') }}">
                        @error('end_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">{{ __('Alasan / Dasar Perintah') }}</label>
                        <textarea name="reason" rows="4" class="form-control @error('reason') is-invalid @enderror" placeholder="{{ __('Jelaskan alasan kebutuhan lembur.') }}">{{ old('reason') }}</textarea>
                        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">{{ __('Catatan Perintah') }}</label>
                        <textarea name="instruction_notes" rows="3" class="form-control @error('instruction_notes') is-invalid @enderror" placeholder="{{ __('Catatan tambahan untuk karyawan.') }}">{{ old('instruction_notes') }}</textarea>
                        @error('instruction_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">{{ __('Kirim Perintah') }}</button>
                        <a href="{{ route('overtime-orders.index') }}" class="btn btn-outline-secondary">{{ __('Batal') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/js/plugin/select2/select2.full.min.js') }}"></script>
<script src="{{ versioned_asset('assets/js/overtime-order-form.js') }}"></script>
@endpush
