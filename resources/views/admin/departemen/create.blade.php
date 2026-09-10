@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-inner">

        <div class="page-header">
            <h3 class="fw-bold mb-3">{{ __('Buat Departemen') }}</h3>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('departemen.store') }}" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Departemen') }}</label>
                            <input type="text" name="departemen" class="form-control">
                            <input type="hidden" name="perusahaan_id" value="{{ $perusahaan->id }}">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Kepala Departemen') }}</label>
                            <input type="text" name="kepala_dept" class="form-control" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('No Telepon Departemen') }}</label>
                            <input type="text" name="no_telp_departemen" class="form-control" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Status Pengeluaran') }}</label>
                            <select name="status_pengeluaran" class="form-control form-select" required>
                                <option value="">{{ __('-- Pilih status --') }}</option>
                                <option value="PRODUKSI 生产">{{ __('PRODUKSI 生产') }}</option>
                                <option value="NON PRODUKSI 生产 非生产">{{ __('NON PRODUKSI 生产 非生产') }}</option>
                            </select>
                        </div>

                        {{-- BUTTON --}}
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> {{ __('Buat') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection