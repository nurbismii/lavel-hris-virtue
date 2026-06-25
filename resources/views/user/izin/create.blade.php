@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/user-izin.css') }}">
@endpush

@section('content')
@php
    $selectedTipe = old('tipe', request('type'));
    $selectedTipe = in_array($selectedTipe, ['PAID', 'UNPAID']) ? $selectedTipe : null;
@endphp

<div class="container-fluid">
    <div class="page-inner">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-file-alt text-primary me-2"></i>
                    {{ __('self_service.permission.create_title') }}
                </h4>
                <small class="text-muted">
                    {{ __('self_service.permission.create_subtitle') }}
                </small>
            </div>

            <a href="{{ route('izin.index') }}" class="btn btn-sm btn-light">
                <i class="fas fa-long-arrow-alt-left me-1"></i> {{ __('self_service.actions.back') }}
            </a>
        </div>

        <div class="card shadow-sm border-0">


            <div class="card-body">
                <form action="{{ route('izin.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    {{-- PILIH TIPE IZIN --}}
                    <div class="mb-3">
                        <label class="form-label fw-bold">{{ __('self_service.permission.type') }}</label>

                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipe" value="PAID" id="paidRadio" {{ $selectedTipe === 'PAID' ? 'checked' : '' }}>
                            <label class="form-check-label" for="paidRadio">
                                {{ __('self_service.permission.paid') }}
                            </label>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipe" value="UNPAID" id="unpaidRadio" {{ $selectedTipe === 'UNPAID' ? 'checked' : '' }}>
                            <label class="form-check-label" for="unpaidRadio">
                                {{ __('self_service.permission.unpaid') }}
                            </label>
                        </div>
                    </div>

                    {{-- KHUSUS PAID --}}
                    <div id="paidOptions" class="{{ $selectedTipe === 'PAID' ? '' : 'izin-paid-options-hidden' }}">
                        <label class="form-label fw-bold">{{ __('self_service.permission.paid_category') }}</label>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="tipe_izin"
                                value="Izin Menikah ( 3 Hari )" {{ old('tipe_izin') === 'Izin Menikah ( 3 Hari )' ? 'checked' : '' }}>
                            <label class="form-check-label">
                                {{ __('self_service.permission.categories.marriage') }}
                            </label>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="tipe_izin"
                                value="Izin menikahkan anak ( 2 Hari )" {{ old('tipe_izin') === 'Izin menikahkan anak ( 2 Hari )' ? 'checked' : '' }}>
                            <label class="form-check-label">
                                {{ __('self_service.permission.categories.child_marriage') }}
                            </label>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="tipe_izin"
                                value="Izin Khitan / Baptis anak ( 2 Hari )" {{ old('tipe_izin') === 'Izin Khitan / Baptis anak ( 2 Hari )' ? 'checked' : '' }}>
                            <label class="form-check-label">
                                {{ __('self_service.permission.categories.child_circumcision_baptism') }}
                            </label>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="tipe_izin"
                                value="Izin istri melahirkan / Keguguran ( 2 Hari )" {{ old('tipe_izin') === 'Izin istri melahirkan / Keguguran ( 2 Hari )' ? 'checked' : '' }}>
                            <label class="form-check-label">
                                {{ __('self_service.permission.categories.wife_birth_miscarriage') }}
                            </label>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="tipe_izin"
                                value="Izin Duka keluarga ( 2 Hari )" {{ old('tipe_izin') === 'Izin Duka keluarga ( 2 Hari )' ? 'checked' : '' }}>
                            <label class="form-check-label">
                                {{ __('self_service.permission.categories.family_bereavement') }}
                            </label>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="tipe_izin"
                                value="Cuti melahirkan ( 3 Bulan )" {{ old('tipe_izin') === 'Cuti melahirkan ( 3 Bulan )' ? 'checked' : '' }}>
                            <label class="form-check-label">
                                {{ __('self_service.permission.categories.maternity_leave') }}
                            </label>
                        </div>
                    </div>

                    {{-- TANGGAL --}}
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('tables.start') }}</label>
                            <input type="date" name="tanggal_mulai" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">{{ __('tables.end') }}</label>
                            <input type="date" name="tanggal_berakhir" class="form-control">
                        </div>
                    </div>

                    {{-- KETERANGAN --}}
                    <div class="mt-3">
                        <label class="form-label">{{ __('tables.information') }}</label>
                        <textarea name="keterangan" class="form-control"></textarea>
                    </div>

                    {{-- UPLOAD --}}
                    <div class="mt-3">
                        <label class="form-label">{{ __('self_service.permission.proof_optional') }}</label>
                        <input type="file" name="foto" class="form-control">
                    </div>

                    <button type="submit" class="btn btn-primary mt-4">
                        {{ __('self_service.actions.submit_permission') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    const paidRadio = document.getElementById('paidRadio');
    const unpaidRadio = document.getElementById('unpaidRadio');
    const paidOptions = document.getElementById('paidOptions');
    const paidCategoryInputs = document.querySelectorAll('input[name="tipe_izin"]');

    const syncPaidOptions = function() {
        const showPaidOptions = paidRadio.checked;

        paidOptions.classList.toggle('izin-paid-options-hidden', !showPaidOptions);

        if (!showPaidOptions) {
            paidCategoryInputs.forEach(function(input) {
                input.checked = false;
            });
        }
    };

    paidRadio.addEventListener('change', syncPaidOptions);
    unpaidRadio.addEventListener('change', syncPaidOptions);
    syncPaidOptions();
</script>
@endsection
