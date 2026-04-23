@extends('layouts.app')

@section('title', 'Edit Master Jadwal Kerja')

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex justify-content-between align-items-center pt-2 pb-4">
            <div>
                <h4 class="fw-bold mb-1">Edit Master Jadwal Kerja</h4>
                <small class="text-muted">Perbarui pola kerja yang dipakai sebagai acuan penjadwalan.</small>
            </div>
            <a href="{{ route('work-patterns.index') }}" class="btn btn-light">Kembali</a>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('work-patterns.update', $workPattern->id) }}" method="POST" class="row g-3">
                    @csrf
                    @method('PUT')
                    <div class="col-md-4">
                        <label class="form-label">Kode</label>
                        <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $workPattern->code) }}">
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Nama Pola Kerja</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $workPattern->name) }}">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Durasi Kerja</label>
                        <input type="number" min="1" name="work_duration_value" class="form-control @error('work_duration_value') is-invalid @enderror" value="{{ old('work_duration_value', $workPattern->work_duration_value) }}">
                        @error('work_duration_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Satuan Kerja</label>
                        <select name="work_duration_unit" class="form-select @error('work_duration_unit') is-invalid @enderror">
                            @foreach($unitOptions as $value => $label)
                                <option value="{{ $value }}" {{ old('work_duration_unit', $workPattern->work_duration_unit) === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('work_duration_unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Durasi Off</label>
                        <input type="number" min="1" name="off_duration_value" class="form-control @error('off_duration_value') is-invalid @enderror" value="{{ old('off_duration_value', $workPattern->off_duration_value) }}">
                        @error('off_duration_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Satuan Off</label>
                        <select name="off_duration_unit" class="form-select @error('off_duration_unit') is-invalid @enderror">
                            @foreach($unitOptions as $value => $label)
                                <option value="{{ $value }}" {{ old('off_duration_unit', $workPattern->off_duration_unit) === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('off_duration_unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jam Masuk</label>
                        <input type="time" name="start_time" class="form-control @error('start_time') is-invalid @enderror" value="{{ old('start_time', $workPattern->start_time ? \Carbon\Carbon::createFromFormat('H:i:s', $workPattern->start_time)->format('H:i') : '') }}">
                        <small class="text-muted">Digunakan sebagai awal rentang jam kerja.</small>
                        @error('start_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jam Pulang</label>
                        <input type="time" name="end_time" class="form-control @error('end_time') is-invalid @enderror" value="{{ old('end_time', $workPattern->end_time ? \Carbon\Carbon::createFromFormat('H:i:s', $workPattern->end_time)->format('H:i') : '') }}">
                        <small class="text-muted">Dipakai sebagai akhir rentang kerja kotor sebelum dikurangi jadwal istirahat.</small>
                        @error('end_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jam Istirahat</label>
                        <input type="time" name="break_start_time" class="form-control @error('break_start_time') is-invalid @enderror" value="{{ old('break_start_time', $workPattern->break_start_time ? \Carbon\Carbon::createFromFormat('H:i:s', $workPattern->break_start_time)->format('H:i') : '') }}">
                        <small class="text-muted">Opsional. Isi jika pola kerja punya jadwal istirahat baku.</small>
                        @error('break_start_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jam Kembali Bekerja</label>
                        <input type="time" name="break_end_time" class="form-control @error('break_end_time') is-invalid @enderror" value="{{ old('break_end_time', $workPattern->break_end_time ? \Carbon\Carbon::createFromFormat('H:i:s', $workPattern->break_end_time)->format('H:i') : '') }}">
                        <small class="text-muted">Opsional. Jika diisi, sistem akan menghitung jam kerja efektif setelah istirahat.</small>
                        @error('break_end_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active" {{ old('is_active', $workPattern->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">
                                Pola kerja aktif
                            </label>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Keterangan</label>
                        <textarea name="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $workPattern->description) }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        <a href="{{ route('work-patterns.index') }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
