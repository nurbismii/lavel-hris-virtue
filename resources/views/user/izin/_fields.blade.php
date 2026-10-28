@php
    $record = $izin ?? null;
    $selectedTipe = old('tipe', $record ? $record->tipe : request('type'));
    $selectedCategory = old('tipe_izin', $record ? $record->tipe_izin : null);
    $categories = [
        'Izin Menikah ( 3 Hari )' => 'marriage',
        'Izin menikahkan anak ( 2 Hari )' => 'child_marriage',
        'Izin Khitan / Baptis anak ( 2 Hari )' => 'child_circumcision_baptism',
        'Izin istri melahirkan / Keguguran ( 2 Hari )' => 'wife_birth_miscarriage',
        'Izin Duka keluarga ( 2 Hari )' => 'family_bereavement',
        'Cuti melahirkan ( 3 Bulan )' => 'maternity_leave',
    ];
@endphp

@if($errors->any())
<div class="alert alert-danger" role="alert">
    <strong>{{ __('Pengajuan belum disimpan. Periksa kembali isian berikut.') }}</strong>
    <ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
</div>
@endif

<fieldset class="mb-4">
    <legend class="form-label fw-bold">{{ __('self_service.permission.type') }} <span class="text-danger">*</span></legend>
    @foreach(['PAID' => 'paid', 'UNPAID' => 'unpaid'] as $value => $label)
    <div class="form-check izin-choice">
        <input class="form-check-input @error('tipe') is-invalid @enderror" type="radio" name="tipe" id="izin-type-{{ $value }}" value="{{ $value }}" {{ $selectedTipe === $value ? 'checked' : '' }} required>
        <label class="form-check-label" for="izin-type-{{ $value }}">{{ __('self_service.permission.' . $label) }}</label>
    </div>
    @endforeach
    @error('tipe')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</fieldset>

<fieldset id="paidOptions" class="mb-4 {{ $selectedTipe === 'PAID' ? '' : 'izin-paid-options-hidden' }}">
    <legend class="form-label fw-bold">{{ __('self_service.permission.paid_category') }} <span class="text-danger">*</span></legend>
    @foreach($categories as $value => $label)
    <div class="form-check izin-choice">
        <input class="form-check-input @error('tipe_izin') is-invalid @enderror" type="radio" name="tipe_izin" id="izin-category-{{ $loop->index }}" value="{{ $value }}" {{ $selectedCategory === $value ? 'checked' : '' }} {{ $selectedTipe === 'PAID' ? 'required' : 'disabled' }}>
        <label class="form-check-label" for="izin-category-{{ $loop->index }}">{{ __('self_service.permission.categories.' . $label) }}</label>
    </div>
    @endforeach
    @error('tipe_izin')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</fieldset>

<div class="row g-3">
    @foreach(['tanggal_mulai' => 'start', 'tanggal_berakhir' => 'end'] as $name => $label)
    <div class="col-md-6 ui-field">
        <label class="form-label" for="{{ $name }}">{{ __('tables.' . $label) }} <span class="text-danger">*</span></label>
        <input type="date" id="{{ $name }}" name="{{ $name }}" class="form-control @error($name) is-invalid @enderror" value="{{ old($name, $record ? $record->{$name} : '') }}" required>
        @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    @endforeach
    <div class="col-12 ui-field">
        <label class="form-label" for="keterangan">{{ __('tables.information') }}</label>
        <textarea id="keterangan" name="keterangan" class="form-control @error('keterangan') is-invalid @enderror" rows="4" maxlength="2000">{{ old('keterangan', $record ? $record->keterangan : '') }}</textarea>
        @error('keterangan')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 ui-field">
        <label class="form-label" for="foto">{{ __('self_service.permission.proof_optional') }}</label>
        <input type="file" id="foto" name="foto" class="form-control @error('foto') is-invalid @enderror" accept="image/jpeg,image/png" aria-describedby="izin-file-help">
        @error('foto')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text" id="izin-file-help">{{ __('Format JPG atau PNG. Maksimal 2 MB.') }}</div>
        @if($record && $record->foto && $record->foto !== '-')
        <a class="d-inline-block mt-2" href="{{ route('izin.proof', $record->id) }}" target="_blank" rel="noopener">{{ __('self_service.permission.current_proof_link') }}</a>
        @endif
    </div>
</div>
