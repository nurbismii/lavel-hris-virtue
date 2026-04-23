@extends('layouts.app')

@section('title', 'Buat Perintah Lembur')

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex justify-content-between align-items-center pt-2 pb-4">
            <div>
                <h4 class="fw-bold mb-1">Buat Perintah Lembur</h4>
                <small class="text-muted">Perintah akan dikirim ke karyawan dan baru berlaku jika disetujui.</small>
            </div>
            <a href="{{ route('overtime-orders.index') }}" class="btn btn-light">Kembali</a>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('overtime-orders.store') }}" method="POST" class="row g-3">
                    @csrf
                    <div class="col-md-12">
                        <label class="form-label">Karyawan</label>
                        <select name="nik_karyawan" class="form-select @error('nik_karyawan') is-invalid @enderror">
                            <option value="">-- Pilih Karyawan --</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->nik }}" {{ old('nik_karyawan') === $employee->nik ? 'selected' : '' }}>
                                    {{ $employee->nama_karyawan }} - {{ $employee->nik }}@if($employee->workPattern) | {{ $employee->workPattern->code }}@endif
                                </option>
                            @endforeach
                        </select>
                        @error('nik_karyawan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tipe Lembur</label>
                        <select name="overtime_type" class="form-select @error('overtime_type') is-invalid @enderror">
                            <option value="">-- Pilih Tipe --</option>
                            @foreach ($typeOptions as $value => $label)
                                <option value="{{ $value }}" {{ old('overtime_type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('overtime_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tanggal Lembur</label>
                        <input type="date" name="overtime_date" class="form-control @error('overtime_date') is-invalid @enderror" value="{{ old('overtime_date') }}">
                        @error('overtime_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Jam Mulai</label>
                        <input type="time" name="start_time" class="form-control @error('start_time') is-invalid @enderror" value="{{ old('start_time') }}">
                        @error('start_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Jam Selesai</label>
                        <input type="time" name="end_time" class="form-control @error('end_time') is-invalid @enderror" value="{{ old('end_time') }}">
                        @error('end_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Alasan / Dasar Perintah</label>
                        <textarea name="reason" rows="4" class="form-control @error('reason') is-invalid @enderror" placeholder="Jelaskan alasan kebutuhan lembur.">{{ old('reason') }}</textarea>
                        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Catatan Perintah</label>
                        <textarea name="instruction_notes" rows="3" class="form-control @error('instruction_notes') is-invalid @enderror" placeholder="Catatan tambahan untuk karyawan.">{{ old('instruction_notes') }}</textarea>
                        @error('instruction_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Kirim Perintah</button>
                        <a href="{{ route('overtime-orders.index') }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
