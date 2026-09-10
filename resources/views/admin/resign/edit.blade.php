@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-inner">

        <div class="page-header">
            <h3 class="fw-bold mb-3">{{ __('Edit Data Resign') }}</h3>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('resign.update', $resign->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <h5 class="fw-bold mb-3">{{ __('Data Utama') }}</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">NIK</label>
                            <input type="text" class="form-control" value="{{ $resign->nik_karyawan }}" readonly>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Nama Karyawan') }}</label>
                            <input type="text" class="form-control" value="{{ $resign->employee->nama_karyawan ?? '-' }}" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Tanggal Keluar') }}</label>
                            <input type="date" class="form-control" name="tanggal_keluar" value="{{ $resign->tanggal_keluar }}">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Status') }}</label>
                            <select name="tipe" class="form-control form-select" required>

                                <option value="RESIGN SESUAI PROSEDUR" {{ old('tipe', $resign->tipe) == 'RESIGN SESUAI PROSEDUR' ? 'selected' : '' }}>{{ __('Resign Sesuai Prosedur') }}</option>
                                <option value="RESIGN TIDAK SESUAI PROSEDUR" {{ old('tipe', $resign->tipe) == 'RESIGN TIDAK SESUAI PROSEDUR' ? 'selected' : '' }}>{{ __('Resign Tidak Sesuai Prosedur') }}</option>
                                <option value="RESIGN TIDAK SESUAI PROSEDUR-PENGAJUAN" {{ old('tipe', $resign->tipe) == 'RESIGN TIDAK SESUAI PROSEDUR-PENGAJUAN' ? 'selected' : '' }}>{{ __('Resign Tidak Sesuai Prosedu - Pengajuan') }}</option>
                                <option value="RESIGN TIDAK SESUAI PROSEDUR-KABUR" {{ old('tipe', $resign->tipe) == 'RESIGN TIDAK SESUAI PROSEDUR-KABUR' ? 'selected' : '' }}>{{ __('Resign Tidak Sesuai Prosedur - Kabur') }}</option>
                                <option value="RESIGN TIDAK SESUAI PROSEDUR-PAYROLL" {{ old('tipe', $resign->tipe) == 'RESIGN TIDAK SESUAI PROSEDUR-PAYROLL' ? 'selected' : '' }}>{{ __('Resign Tidak Sesuai Prosedur - Payroll') }}</option>
                                <option value="PB RESIGN" {{ old('tipe', $resign->tipe) == 'PB RESIGN' ? 'selected' : '' }}>{{ __('PB Resign') }}</option>
                                <option value="PUTUS KONTRAK" {{ old('tipe', $resign->tipe) == 'PUTUS KONTRAK' ? 'selected' : '' }}>{{ __('Putus Kontrak') }}</option>
                                <option value="PHK" {{ old('tipe', $resign->tipe) == 'PHK' ? 'selected' : '' }}>{{ __('PHK') }}</option>
                                <option value="PHK PENSIUN" {{ old('tipe', $resign->tipe) == 'PHK PENSIUN' ? 'selected' : '' }}>{{ __('PHK Pensiun') }}</option>
                                <option value="PHK PENSIUN DINI" {{ old('tipe', $resign->tipe) == 'PHK PENSIUN DINI' ? 'selected' : '' }}>{{ __('PHK Pensiun Dini') }}</option>
                                <option value="PHK PIDANA" {{ old('tipe', $resign->tipe) == 'PHK PIDANA' ? 'selected' : '' }}>{{ __('PHK Pidana') }}</option>
                                <option value="PHK MENINGGAL DUNIA" {{ old('tipe', $resign->tipe) == 'PHK MENINGGAL DUNIA' ? 'selected' : '' }}>{{ __('PHK Meninggal Dunia') }}</option>
                            </select>
                        </div>

                        {{-- BUTTON --}}
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> {{ __('Update') }}
                            </button>
                            <a href="{{ route('resign.index') }}" class="btn btn-secondary">
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