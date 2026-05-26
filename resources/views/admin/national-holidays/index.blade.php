@extends('layouts.app')

@section('title', 'Master Tanggal Merah')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/national-holidays.css') }}">
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-calendar text-primary me-2"></i>
                    Master Tanggal Merah
                </h4>
                <small class="text-muted">Kelola tanggal merah nasional yang dipakai oleh jadwal kerja, presensi, dan pengaturan shift.</small>
            </div>
        </div>

        @if(!$isTableReady)
        <div class="alert alert-warning">
            Fitur ini belum aktif karena tabel tanggal merah nasional belum tersedia. Jalankan <code>php artisan migrate</code> terlebih dahulu.
        </div>
        @else
        <div class="row g-3">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-1">Input Tanggal Merah</h5>
                        <small class="text-muted d-block mb-3">Jika tanggal yang sama diinput ulang, nama liburnya akan diperbarui.</small>

                        <form action="{{ route('national-holidays.store') }}" method="POST" class="row g-3">
                            @csrf
                            <div class="col-12">
                                <label class="form-label">Tanggal</label>
                                <input type="date" name="holiday_date" class="form-control @error('holiday_date') is-invalid @enderror" value="{{ old('holiday_date') }}" required>
                                @error('holiday_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label">Nama Tanggal Merah</label>
                                <input type="text" name="holiday_name" class="form-control @error('holiday_name') is-invalid @enderror" value="{{ old('holiday_name') }}" maxlength="150" placeholder="Contoh: Hari Raya Idul Fitri" required>
                                @error('holiday_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary w-100">Simpan Tanggal Merah</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-2 mb-3">
                            <div>
                                <h5 class="mb-1">Daftar Tanggal Merah</h5>
                                <small class="text-muted">Menampilkan data tahun {{ $year }}.</small>
                            </div>
                            <form method="GET" class="d-flex gap-2 align-items-end">
                                <div>
                                    <label class="form-label">Tahun</label>
                                    <input type="number" name="year" class="form-control form-control-sm" value="{{ $year }}" min="2000" max="2100">
                                </div>
                                <button class="btn btn-primary">Tampilkan</button>
                            </form>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="national-holidays-table__no">{{ __('tables.no') }}</th>
                                        <th class="national-holidays-table__date">{{ __('tables.date') }}</th>
                                        <th>{{ __('tables.national_holiday_name') }}</th>
                                        <th class="national-holidays-table__action">{{ __('tables.action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($nationalHolidays as $index => $holiday)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ formatDateIndonesia($holiday->holiday_date) }}</td>
                                        <td>{{ $holiday->holiday_name }}</td>
                                        <td>
                                            <form action="{{ route('national-holidays.destroy', ['nationalHoliday' => $holiday->id, 'year' => $year]) }}" method="POST" data-confirm-submit="Hapus tanggal merah ini?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm">Hapus</button>
                                            </form>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Belum ada tanggal merah nasional pada tahun ini.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/js/national-holidays.js') }}"></script>
@endpush
