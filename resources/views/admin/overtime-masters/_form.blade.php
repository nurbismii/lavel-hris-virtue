@php
    $isEdit = $rule->exists;
@endphp

<div class="card">
    <div class="card-body">
        <form action="{{ $isEdit ? route('overtime-masters.update', $rule->id) : route('overtime-masters.store') }}" method="POST" class="row g-3">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <div class="col-md-4">
                <label class="form-label">Kode Rule</label>
                <input
                    type="text"
                    name="code"
                    class="form-control text-uppercase @error('code') is-invalid @enderror"
                    value="{{ old('code', $rule->code) }}"
                    placeholder="PP35_WORKDAY_HOUR_1">
                <small class="text-muted">Unik, gunakan huruf/angka/underscore.</small>
                @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-8">
                <label class="form-label">Nama Rule</label>
                <input
                    type="text"
                    name="name"
                    class="form-control @error('name') is-invalid @enderror"
                    value="{{ old('name', $rule->name) }}"
                    placeholder="Hari kerja - jam pertama">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="form-label">Pola Rule</label>
                <select name="schedule_type" class="form-select @error('schedule_type') is-invalid @enderror">
                    @foreach($ruleScheduleTypeOptions as $value => $label)
                        <option value="{{ $value }}" {{ old('schedule_type', $rule->schedule_type) === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('schedule_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="form-label">Jenis Hari</label>
                <select name="day_type" class="form-select @error('day_type') is-invalid @enderror">
                    @foreach($dayTypeOptions as $value => $label)
                        <option value="{{ $value }}" {{ old('day_type', $rule->day_type) === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('day_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2">
                <label class="form-label">Jam Mulai</label>
                <input
                    type="number"
                    name="hour_from"
                    min="1"
                    max="24"
                    class="form-control @error('hour_from') is-invalid @enderror"
                    value="{{ old('hour_from', $rule->hour_from) }}">
                @error('hour_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2">
                <label class="form-label">Jam Akhir</label>
                <input
                    type="number"
                    name="hour_to"
                    min="1"
                    max="24"
                    class="form-control @error('hour_to') is-invalid @enderror"
                    value="{{ old('hour_to', $rule->hour_to) }}"
                    placeholder="Kosong">
                <small class="text-muted">Kosong = seterusnya.</small>
                @error('hour_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">Pengali</label>
                <input
                    type="number"
                    name="multiplier"
                    min="0.01"
                    max="24"
                    step="0.01"
                    class="form-control @error('multiplier') is-invalid @enderror"
                    value="{{ old('multiplier', $rule->multiplier) }}"
                    placeholder="1.50">
                @error('multiplier')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">Urutan</label>
                <input
                    type="number"
                    name="sort_order"
                    min="0"
                    max="65535"
                    class="form-control @error('sort_order') is-invalid @enderror"
                    value="{{ old('sort_order', $rule->sort_order ?? 0) }}">
                @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label class="form-label">Status</label>
                <div class="form-check form-switch mt-2">
                    <input type="hidden" name="is_active" value="0">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="is_active"
                        value="1"
                        id="is_active"
                        {{ old('is_active', $rule->exists ? $rule->is_active : true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Aktif digunakan kalkulator</label>
                </div>
            </div>

            <div class="col-md-12">
                <label class="form-label">Dasar Hukum</label>
                <input
                    type="text"
                    name="legal_basis"
                    class="form-control @error('legal_basis') is-invalid @enderror"
                    value="{{ old('legal_basis', $rule->legal_basis) }}"
                    placeholder="PP 35/2021 Pasal 31 ayat ...">
                @error('legal_basis')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Rule' }}
                </button>
                <a href="{{ route('overtime-masters.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
