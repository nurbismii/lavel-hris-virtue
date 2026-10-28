@extends('layouts.app')
@section('title', __('Buat Divisi'))

@section('content')
<div class="container-fluid">
    <div class="page-inner">

        <div class="page-header">
            <h3 class="fw-bold mb-3">{{ __('Buat Divisi') }}</h3>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('divisi.store') }}" method="POST" data-submit-lock>
                    @csrf
                    @include('partials.form-errors')
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="departemen_id" class="form-label">{{ __('Departemen') }} <span class="text-danger">*</span></label>
                            <select id="departemen_id" name="departemen_id" class="form-select form-control @error('departemen_id') is-invalid @enderror" required @error('departemen_id') aria-invalid="true" aria-describedby="departemen_id-error" @enderror>
                                <option value="">{{ __('Pilih Departemen') }}</option>

                                @php
                                $groupedDepts = [];
                                foreach ($departemens as $d) {
                                $groupedDepts[$d->perusahaan->nama_perusahaan ?? '-'][] = $d;
                                }
                                @endphp

                                @foreach($groupedDepts as $companyName => $companyDepartments)
                                <optgroup label="{{ $companyName }}">
                                    @foreach($companyDepartments as $d)
                                    <option value="{{ $d->id }}" {{ (string) old('departemen_id') === (string) $d->id ? 'selected' : '' }}>{{ $d->departemen }}</option>
                                    @endforeach
                                </optgroup>
                                @endforeach
                            </select>
                            @error('departemen_id')<div id="departemen_id-error" class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="nama_divisi" class="form-label">{{ __('Nama Divisi') }} <span class="text-danger">*</span></label>
                            <input id="nama_divisi" type="text" name="nama_divisi" class="form-control @error('nama_divisi') is-invalid @enderror" value="{{ old('nama_divisi') }}" maxlength="255" required @error('nama_divisi') aria-invalid="true" aria-describedby="nama_divisi-error" @enderror>
                            @error('nama_divisi')<div id="nama_divisi-error" class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- BUTTON --}}
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary" data-loading-text="{{ __('Menyimpan...') }}">
                                <i class="fas fa-save me-1"></i> {{ __('Buat') }}
                            </button>
                            <a href="{{ route('perusahaan.index') }}" class="btn btn-outline-secondary">{{ __('Kembali') }}</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
