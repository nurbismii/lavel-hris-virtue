@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-inner">

        <div class="page-header">
            <h3 class="fw-bold mb-3">{{ __('Edit Perusahaan') }}</h3>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('perusahaan.update', $perusahaan->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    {{-- DATA UTAMA --}}
                    <h5 class="fw-bold mb-3">{{ __('Data Utama') }}</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Kode Perusahaan') }}</label>
                            <input type="text" name="kode_perusahaan" class="form-control" value="{{ $perusahaan->kode_perusahaan }}" require>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Nama Perusahaan') }}</label>
                            <input type="text" name="nama_perusahaan" class="form-control" value="{{ $perusahaan->nama_perusahaan ?? '-' }}" required>
                        </div>

                        {{-- BUTTON --}}
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> {{ __('Update') }}
                            </button>
                            <a href="{{ route('user.index') }}" class="btn btn-secondary">
                                {{ __('Kembali') }}
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection