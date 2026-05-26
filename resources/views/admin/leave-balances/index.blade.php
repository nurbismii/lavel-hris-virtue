@extends('layouts.app')

@section('title', 'Saldo Cuti')

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4 gap-2">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-calendar-check text-primary me-2"></i>
                    Saldo Cuti Resmi
                </h4>
                <small class="text-muted">Pantau saldo cuti tahunan karyawan aktif VDNI dan VDNIP.</small>
            </div>
        </div>

        @if(!$isTableReady)
            <div class="alert alert-warning">
                Fitur ledger saldo cuti belum aktif karena tabel <code>leave_balance_ledgers</code> belum tersedia. Jalankan <code>php artisan migrate</code> terlebih dahulu.
            </div>
        @endif

        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label">Cari Karyawan</label>
                        <input type="text" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="NIK atau nama karyawan">
                    </div>
                    <div class="col-md-7 d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i> Tampilkan
                        </button>
                        <a href="{{ route('leave-balances.index') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-undo me-1"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                    <div>
                        <h5 class="mb-1">Daftar Saldo Karyawan</h5>
                        <small class="text-muted">Menampilkan karyawan aktif dengan area kerja VDNI dan VDNIP.</small>
                    </div>
                    <div class="text-muted small">Total: {{ number_format($employees->total()) }} karyawan</div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 130px;">{{ __('tables.nik') }}</th>
                                <th>{{ __('tables.name') }}</th>
                                <th style="width: 110px;">{{ __('tables.company') }}</th>
                                <th>{{ __('tables.department') }}</th>
                                <th>{{ __('tables.division') }}</th>
                                <th style="width: 130px;">{{ __('tables.balance') }}</th>
                                <th style="width: 130px;">{{ __('tables.action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($employees as $employee)
                                <tr>
                                    <td>{{ $employee->nik }}</td>
                                    <td>{{ $employee->nama_karyawan }}</td>
                                    <td>
                                        <span class="badge bg-light text-dark border">{{ $employee->area_kerja }}</span>
                                    </td>
                                    <td>{{ optional($employee->departemen)->departemen ?? '-' }}</td>
                                    <td>{{ optional($employee->divisi)->nama_divisi ?? '-' }}</td>
                                    <td>
                                        <span class="badge bg-primary">
                                            {{ number_format((float) $employee->sisa_cuti, 0) }} hari
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('leave-balances.show', $employee->nik) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-list me-1"></i> Ledger
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        Tidak ada karyawan untuk filter ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $employees->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
