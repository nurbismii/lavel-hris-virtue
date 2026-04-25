@extends('layouts.app')

@section('title', 'Tambah Master Jadwal Kerja')

@section('content')
@php
    $patternPresets = [
        'weekly_6_1' => [
            'code' => '6-1-MGG',
            'name' => '6:1 Hari Kerja Mingguan',
            'basis' => \App\Models\WorkPattern::BASIS_WEEKLY,
            'weekly_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
        ],
        'weekly_5_2' => [
            'code' => '5-2-MGG',
            'name' => '5:2 Hari Kerja Mingguan',
            'basis' => \App\Models\WorkPattern::BASIS_WEEKLY,
            'weekly_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
        ],
        'cycle_5_2_day' => [
            'code' => '5-2-HRN',
            'name' => '5:2 Siklus Harian',
            'basis' => \App\Models\WorkPattern::BASIS_CYCLE,
            'work_duration_value' => 5,
            'work_duration_unit' => 'day',
            'off_duration_value' => 2,
            'off_duration_unit' => 'day',
        ],
        'cycle_6_1_day' => [
            'code' => '6-1-HRN',
            'name' => '6:1 Siklus Harian',
            'basis' => \App\Models\WorkPattern::BASIS_CYCLE,
            'work_duration_value' => 6,
            'work_duration_unit' => 'day',
            'off_duration_value' => 1,
            'off_duration_unit' => 'day',
        ],
        'cycle_10_2' => [
            'code' => '10-2-SKL',
            'name' => '10:2 Siklus Mingguan',
            'basis' => \App\Models\WorkPattern::BASIS_CYCLE,
            'work_duration_value' => 10,
            'work_duration_unit' => 'week',
            'off_duration_value' => 2,
            'off_duration_unit' => 'week',
            'national_holiday_as_off' => false,
        ],
        'cycle_8_2' => [
            'code' => '8-2-SKL',
            'name' => '8:2 Siklus Mingguan',
            'basis' => \App\Models\WorkPattern::BASIS_CYCLE,
            'work_duration_value' => 8,
            'work_duration_unit' => 'week',
            'off_duration_value' => 2,
            'off_duration_unit' => 'week',
            'national_holiday_as_off' => false,
        ],
    ];
@endphp
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex justify-content-between align-items-center pt-2 pb-4">
            <div>
                <h4 class="fw-bold mb-1">Tambah Master Jadwal Kerja</h4>
                <small class="text-muted">Simpan pola kerja yang nanti bisa dipasang ke data karyawan.</small>
            </div>
            <a href="{{ route('work-patterns.index') }}" class="btn btn-light">Kembali</a>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('work-patterns.store') }}" method="POST" class="row g-3">
                    @csrf
                    <div class="col-12">
                        <label class="form-label">Preset Cepat</label>
                        <div class="d-flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="btn btn-outline-primary js-pattern-preset"
                                data-preset='{{ json_encode($patternPresets['weekly_6_1']) }}'>
                                6:1 Mingguan
                            </button>
                            <button
                                type="button"
                                class="btn btn-outline-primary js-pattern-preset"
                                data-preset='{{ json_encode($patternPresets['weekly_5_2']) }}'>
                                5:2 Mingguan
                            </button>
                            <button
                                type="button"
                                class="btn btn-outline-primary js-pattern-preset"
                                data-preset='{{ json_encode($patternPresets['cycle_5_2_day']) }}'>
                                5:2 Harian
                            </button>
                            <button
                                type="button"
                                class="btn btn-outline-primary js-pattern-preset"
                                data-preset='{{ json_encode($patternPresets['cycle_6_1_day']) }}'>
                                6:1 Harian
                            </button>
                            <button
                                type="button"
                                class="btn btn-outline-primary js-pattern-preset"
                                data-preset='{{ json_encode($patternPresets['cycle_10_2']) }}'>
                                10:2 Siklus
                            </button>
                            <button
                                type="button"
                                class="btn btn-outline-primary js-pattern-preset"
                                data-preset='{{ json_encode($patternPresets['cycle_8_2']) }}'>
                                8:2 Siklus
                            </button>
                        </div>
                        <small class="text-muted d-block mt-2">Preset ini hanya mengisi pola kerja. Jam masuk, jam pulang, dan jadwal istirahat tetap bisa Anda atur sendiri.</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Kode</label>
                        <input type="text" id="patternCode" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code') }}" placeholder="Contoh: 6-1">
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Nama Pola Kerja</label>
                        <input type="text" id="patternName" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="Contoh: 6 Hari Kerja 1 Hari Off">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Basis Pola Kerja</label>
                        <select name="pattern_basis" id="patternBasis" class="form-select @error('pattern_basis') is-invalid @enderror">
                            @foreach($basisOptions as $value => $label)
                                <option value="{{ $value }}" {{ old('pattern_basis', \App\Models\WorkPattern::BASIS_WEEKLY) === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('pattern_basis')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-8 js-weekly-section">
                        <label class="form-label">Hari Kerja Mingguan</label>
                        <div class="d-flex flex-wrap gap-3 pt-2">
                            @foreach($weekdayOptions as $value => $label)
                                <div class="form-check">
                                    <input
                                        class="form-check-input js-weekday-checkbox"
                                        type="checkbox"
                                        name="weekly_work_days[]"
                                        value="{{ $value }}"
                                        id="weekly_day_{{ $value }}"
                                        {{ in_array($value, old('weekly_work_days', ['mon', 'tue', 'wed', 'thu', 'fri', 'sat']), true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="weekly_day_{{ $value }}">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>
                        <small class="text-muted d-block mt-2">Contoh 6:1 mingguan = Senin sampai Sabtu dicentang, Minggu tidak dicentang.</small>
                        @error('weekly_work_days')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @error('weekly_work_days.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Durasi Kerja</label>
                        <input type="number" min="1" name="work_duration_value" id="workDurationValue" class="form-control @error('work_duration_value') is-invalid @enderror" value="{{ old('work_duration_value', 6) }}">
                        @error('work_duration_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Satuan Kerja</label>
                        <select name="work_duration_unit" id="workDurationUnit" class="form-select @error('work_duration_unit') is-invalid @enderror">
                            @foreach($unitOptions as $value => $label)
                                <option value="{{ $value }}" {{ old('work_duration_unit', 'day') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('work_duration_unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Durasi Off</label>
                        <input type="number" min="0" name="off_duration_value" id="offDurationValue" class="form-control @error('off_duration_value') is-invalid @enderror" value="{{ old('off_duration_value', 1) }}">
                        @error('off_duration_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Satuan Off</label>
                        <select name="off_duration_unit" id="offDurationUnit" class="form-select @error('off_duration_unit') is-invalid @enderror">
                            @foreach($unitOptions as $value => $label)
                                <option value="{{ $value }}" {{ old('off_duration_unit', 'day') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('off_duration_unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jam Masuk</label>
                        <input type="time" name="start_time" class="form-control @error('start_time') is-invalid @enderror" value="{{ old('start_time') }}">
                        <small class="text-muted">Digunakan sebagai awal rentang jam kerja.</small>
                        @error('start_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jam Pulang</label>
                        <input type="time" name="end_time" class="form-control @error('end_time') is-invalid @enderror" value="{{ old('end_time') }}">
                        <small class="text-muted">Dipakai sebagai akhir rentang kerja kotor sebelum dikurangi jadwal istirahat.</small>
                        @error('end_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jam Istirahat</label>
                        <input type="time" name="break_start_time" class="form-control @error('break_start_time') is-invalid @enderror" value="{{ old('break_start_time') }}">
                        <small class="text-muted">Opsional. Isi jika pola kerja punya jadwal istirahat baku.</small>
                        @error('break_start_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jam Kembali Bekerja</label>
                        <input type="time" name="break_end_time" class="form-control @error('break_end_time') is-invalid @enderror" value="{{ old('break_end_time') }}">
                        <small class="text-muted">Opsional. Jika diisi, sistem akan menghitung jam kerja efektif setelah istirahat.</small>
                        @error('break_end_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <div class="form-check">
                            <input type="hidden" name="national_holiday_as_off" value="0">
                            <input class="form-check-input" type="checkbox" value="1" id="national_holiday_as_off" name="national_holiday_as_off" {{ old('national_holiday_as_off', true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="national_holiday_as_off">
                                Tanggal merah nasional otomatis dianggap off
                            </label>
                        </div>
                        <small class="text-muted d-block mt-1">Nonaktifkan opsi ini untuk pola seperti 10:2 atau 8:2 jika tanggal merah tetap dianggap hari kerja biasa.</small>
                    </div>
                    <div class="col-md-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active" {{ old('is_active', true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">
                                Pola kerja aktif
                            </label>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Keterangan</label>
                        <textarea name="description" rows="4" class="form-control @error('description') is-invalid @enderror" placeholder="Contoh: Dipakai untuk karyawan site dengan pola 10 bulan kerja dan 2 minggu off.">{{ old('description') }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                        <a href="{{ route('work-patterns.index') }}" class="btn btn-outline-secondary">Batal</a>
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
        const basisSelect = document.getElementById('patternBasis');
        const weeklySection = document.querySelector('.js-weekly-section');
        const workDurationValue = document.getElementById('workDurationValue');
        const workDurationUnit = document.getElementById('workDurationUnit');
        const offDurationValue = document.getElementById('offDurationValue');
        const offDurationUnit = document.getElementById('offDurationUnit');
        const codeInput = document.getElementById('patternCode');
        const nameInput = document.getElementById('patternName');
        const nationalHolidayCheckbox = document.getElementById('national_holiday_as_off');
        const weekdayCheckboxes = Array.from(document.querySelectorAll('.js-weekday-checkbox'));
        const presetButtons = Array.from(document.querySelectorAll('.js-pattern-preset'));
        const weeklyValue = '{{ \App\Models\WorkPattern::BASIS_WEEKLY }}';

        if (!basisSelect || !weeklySection || !workDurationValue || !workDurationUnit || !offDurationValue || !offDurationUnit) {
            return;
        }

        function syncWeeklyDurations() {
            if (basisSelect.value !== weeklyValue) {
                workDurationValue.readOnly = false;
                offDurationValue.readOnly = false;
                workDurationUnit.disabled = false;
                offDurationUnit.disabled = false;
                return;
            }

            const checkedDays = weekdayCheckboxes.filter((checkbox) => checkbox.checked).length;
            workDurationValue.value = checkedDays;
            offDurationValue.value = Math.max(7 - checkedDays, 0);
            workDurationUnit.value = 'day';
            offDurationUnit.value = 'day';
            workDurationValue.readOnly = true;
            offDurationValue.readOnly = true;
            workDurationUnit.disabled = true;
            offDurationUnit.disabled = true;
        }

        function togglePatternSections() {
            const isWeekly = basisSelect.value === weeklyValue;
            weeklySection.style.display = isWeekly ? '' : 'none';
            syncWeeklyDurations();
        }

        function applyPreset(preset) {
            if (codeInput && preset.code) {
                codeInput.value = preset.code;
            }

            if (nameInput && preset.name) {
                nameInput.value = preset.name;
            }

            if (nationalHolidayCheckbox) {
                nationalHolidayCheckbox.checked = preset.national_holiday_as_off !== false;
            }

            basisSelect.value = preset.basis || weeklyValue;

            weekdayCheckboxes.forEach((checkbox) => {
                checkbox.checked = Array.isArray(preset.weekly_days) && preset.weekly_days.includes(checkbox.value);
            });

            if (basisSelect.value !== weeklyValue) {
                workDurationValue.readOnly = false;
                offDurationValue.readOnly = false;
                workDurationUnit.disabled = false;
                offDurationUnit.disabled = false;
                workDurationValue.value = preset.work_duration_value || '';
                workDurationUnit.value = preset.work_duration_unit || 'day';
                offDurationValue.value = preset.off_duration_value || 0;
                offDurationUnit.value = preset.off_duration_unit || 'day';
            }

            togglePatternSections();
        }

        basisSelect.addEventListener('change', togglePatternSections);
        weekdayCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', syncWeeklyDurations));
        presetButtons.forEach((button) => {
            button.addEventListener('click', function () {
                applyPreset(JSON.parse(this.dataset.preset || '{}'));
            });
        });

        togglePatternSections();
    })();
</script>
@endpush
