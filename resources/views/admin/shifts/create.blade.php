@extends('layouts.app')

@section('title', 'Tambah Master Shift')

@section('content')
@php
    $shiftPresets = [
        'reguler' => [
            'code' => 'REGULER',
            'name' => 'Reguler',
            'type' => \App\Models\Shift::TYPE_REGULER,
        ],
        'shift_1' => [
            'code' => 'SHIFT-1',
            'name' => 'Shift 1',
            'type' => \App\Models\Shift::TYPE_SHIFT_1,
        ],
        'shift_2' => [
            'code' => 'SHIFT-2',
            'name' => 'Shift 2',
            'type' => \App\Models\Shift::TYPE_SHIFT_2,
        ],
        'shift_3' => [
            'code' => 'SHIFT-3',
            'name' => 'Shift 3',
            'type' => \App\Models\Shift::TYPE_SHIFT_3,
        ],
    ];
@endphp
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex justify-content-between align-items-center pt-2 pb-4">
            <div>
                <h4 class="fw-bold mb-1">Tambah Master Shift</h4>
                <small class="text-muted">Simpan jam kerja yang nanti bisa dipakai pada pengaturan shift harian.</small>
            </div>
            <a href="{{ route('shifts.index') }}" class="btn btn-light">Kembali</a>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('shifts.store') }}" method="POST" class="row g-3">
                    @csrf
                    <div class="col-12">
                        <label class="form-label">Preset Cepat</label>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-primary js-shift-preset" data-preset='{{ json_encode($shiftPresets['reguler']) }}'>Reguler</button>
                            <button type="button" class="btn btn-outline-primary js-shift-preset" data-preset='{{ json_encode($shiftPresets['shift_1']) }}'>Shift 1</button>
                            <button type="button" class="btn btn-outline-primary js-shift-preset" data-preset='{{ json_encode($shiftPresets['shift_2']) }}'>Shift 2</button>
                            <button type="button" class="btn btn-outline-primary js-shift-preset" data-preset='{{ json_encode($shiftPresets['shift_3']) }}'>Shift 3</button>
                        </div>
                        <small class="text-muted d-block mt-2">Preset hanya mengisi kode, nama, dan tipe shift. Jam kerja tetap Anda tentukan sendiri.</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Kode</label>
                        <input type="text" id="shiftCode" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code') }}" placeholder="Contoh: REGULER">
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Nama Shift</label>
                        <input type="text" id="shiftName" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="Contoh: Shift 1">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tipe Shift</label>
                        <select name="type" id="shiftType" class="form-select @error('type') is-invalid @enderror">
                            @foreach($typeOptions as $value => $label)
                                <option value="{{ $value }}" {{ old('type', \App\Models\Shift::TYPE_CUSTOM) === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jam Masuk</label>
                        <input type="time" name="start_time" class="form-control @error('start_time') is-invalid @enderror" value="{{ old('start_time') }}">
                        @error('start_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jam Pulang</label>
                        <input type="time" name="end_time" class="form-control @error('end_time') is-invalid @enderror" value="{{ old('end_time') }}">
                        @error('end_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jam Istirahat</label>
                        <input type="time" name="break_start_time" class="form-control @error('break_start_time') is-invalid @enderror" value="{{ old('break_start_time') }}">
                        <small class="text-muted">Opsional.</small>
                        @error('break_start_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jam Kembali Bekerja</label>
                        <input type="time" name="break_end_time" class="form-control @error('break_end_time') is-invalid @enderror" value="{{ old('break_end_time') }}">
                        <small class="text-muted">Opsional.</small>
                        @error('break_end_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active" {{ old('is_active', true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">
                                Shift aktif
                            </label>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Keterangan</label>
                        <textarea name="description" rows="4" class="form-control @error('description') is-invalid @enderror" placeholder="Contoh: Dipakai untuk operasional malam atau lembur site.">{{ old('description') }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                        <a href="{{ route('shifts.index') }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const presetButtons = Array.from(document.querySelectorAll('.js-shift-preset'));
        const codeInput = document.getElementById('shiftCode');
        const nameInput = document.getElementById('shiftName');
        const typeSelect = document.getElementById('shiftType');

        presetButtons.forEach((button) => {
            button.addEventListener('click', function () {
                const preset = JSON.parse(this.dataset.preset || '{}');

                if (codeInput && preset.code) {
                    codeInput.value = preset.code;
                }

                if (nameInput && preset.name) {
                    nameInput.value = preset.name;
                }

                if (typeSelect && preset.type) {
                    typeSelect.value = preset.type;
                }
            });
        });
    })();
</script>
@endpush
