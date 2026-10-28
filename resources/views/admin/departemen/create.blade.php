@extends('layouts.app')
@section('title', __('Buat Departemen'))

@section('content')
<div class="container-fluid">
    <div class="page-inner">

        <div class="page-header">
            <h3 class="fw-bold mb-3">{{ __('Buat Departemen') }}</h3>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('departemen.store') }}" method="POST" data-submit-lock>
                    @csrf
                    @include('partials.form-errors')
                    <p class="text-muted">{{ $perusahaan->nama_perusahaan }}</p>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="departemen" class="form-label">{{ __('Departemen') }} <span class="text-danger">*</span></label>
                            <input id="departemen" type="text" name="departemen" class="form-control @error('departemen') is-invalid @enderror" value="{{ old('departemen') }}" maxlength="255" required @error('departemen') aria-invalid="true" aria-describedby="departemen-error" @enderror>
                            @error('departemen')<div id="departemen-error" class="invalid-feedback">{{ $message }}</div>@enderror
                            <input type="hidden" name="perusahaan_id" value="{{ $perusahaan->id }}">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="kepala_dept" class="form-label">{{ __('Kepala Departemen') }} <span class="text-danger">*</span></label>
                            <input id="kepala_dept" type="text" name="kepala_dept" class="form-control @error('kepala_dept') is-invalid @enderror" value="{{ old('kepala_dept') }}" maxlength="255" required @error('kepala_dept') aria-invalid="true" aria-describedby="kepala_dept-error" @enderror>
                            @error('kepala_dept')<div id="kepala_dept-error" class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="no_telp_departemen" class="form-label">{{ __('No Telepon Departemen') }} <span class="text-danger">*</span></label>
                            <input id="no_telp_departemen" type="tel" name="no_telp_departemen" class="form-control @error('no_telp_departemen') is-invalid @enderror" value="{{ old('no_telp_departemen') }}" maxlength="16" required @error('no_telp_departemen') aria-invalid="true" aria-describedby="no_telp_departemen-error" @enderror>
                            @error('no_telp_departemen')<div id="no_telp_departemen-error" class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="status_pengeluaran" class="form-label">{{ __('Status Pengeluaran') }} <span class="text-danger">*</span></label>
                            <select id="status_pengeluaran" name="status_pengeluaran" class="form-control form-select @error('status_pengeluaran') is-invalid @enderror" required @error('status_pengeluaran') aria-invalid="true" aria-describedby="status_pengeluaran-error" @enderror>
                                <option value="">{{ __('-- Pilih status --') }}</option>
                                <option value="PRODUKSI 生产" {{ old('status_pengeluaran') === 'PRODUKSI 生产' ? 'selected' : '' }}>{{ __('PRODUKSI 生产') }}</option>
                                <option value="NON PRODUKSI 生产 非生产" {{ old('status_pengeluaran') === 'NON PRODUKSI 生产 非生产' ? 'selected' : '' }}>{{ __('NON PRODUKSI 生产 非生产') }}</option>
                            </select>
                            @error('status_pengeluaran')<div id="status_pengeluaran-error" class="invalid-feedback">{{ $message }}</div>@enderror
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
