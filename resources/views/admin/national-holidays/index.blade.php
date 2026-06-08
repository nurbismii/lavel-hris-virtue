@extends('layouts.app')

@section('title', 'Master Tanggal Merah')

@section('content')
<div class="container-fluid">
    <div class="page-inner ui-page">
        <div class="ui-page-header">
            <div class="ui-page-heading">
                <span class="ui-page-icon" aria-hidden="true">
                    <i class="fas fa-calendar"></i>
                </span>
                <div>
                    <h4 class="ui-page-title">Master Tanggal Merah</h4>
                    <p class="ui-page-subtitle">Kelola tanggal merah nasional yang dipakai oleh jadwal kerja, presensi, dan pengaturan shift.</p>
                </div>
            </div>
        </div>

        @if(!$isTableReady)
        <div class="alert ui-alert ui-alert--warning">
            Fitur ini belum aktif karena tabel tanggal merah nasional belum tersedia. Jalankan <code>php artisan migrate</code> terlebih dahulu.
        </div>
        @else
        <div class="ui-grid ui-grid--sidebar">
            <section class="ui-panel" aria-labelledby="nationalHolidayFormTitle">
                <div class="ui-panel__header">
                    <div>
                        <h5 class="ui-panel__title" id="nationalHolidayFormTitle">Input Tanggal Merah</h5>
                        <p class="ui-panel__meta">Jika tanggal yang sama diinput ulang, nama liburnya akan diperbarui.</p>
                    </div>
                </div>

                <div class="ui-panel__body">
                    <form action="{{ route('national-holidays.store') }}" method="POST" class="ui-form-grid" data-loading-text="Menyimpan...">
                        @csrf
                        <div class="ui-field">
                            <label class="form-label" for="holiday_date">Tanggal</label>
                            <input type="date" id="holiday_date" name="holiday_date" class="form-control @error('holiday_date') is-invalid @enderror" value="{{ old('holiday_date') }}" required>
                            @error('holiday_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="ui-field">
                            <label class="form-label" for="holiday_name">Nama Tanggal Merah</label>
                            <input type="text" id="holiday_name" name="holiday_name" class="form-control @error('holiday_name') is-invalid @enderror" value="{{ old('holiday_name') }}" maxlength="150" placeholder="Contoh: Hari Raya Idul Fitri" required>
                            @error('holiday_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <button type="submit" class="btn btn-primary ui-btn-icon w-100" data-loading-text="Menyimpan...">
                            <i class="fas fa-save" aria-hidden="true"></i>
                            <span>Simpan Tanggal Merah</span>
                        </button>
                    </form>
                </div>
            </section>

            <section class="ui-panel" aria-labelledby="nationalHolidayTableTitle">
                <div class="ui-panel__header">
                    <div>
                        <h5 class="ui-panel__title" id="nationalHolidayTableTitle">Daftar Tanggal Merah</h5>
                        <p class="ui-panel__meta">Menampilkan data tahun {{ $year }}.</p>
                    </div>
                    <form method="GET" class="ui-filter-bar" data-loading-text="Memuat...">
                        <div class="ui-field">
                            <label class="form-label" for="year">Tahun</label>
                            <input type="number" id="year" name="year" class="form-control form-control-sm" value="{{ $year }}" min="2000" max="2100">
                        </div>
                        <button class="btn btn-primary ui-btn-icon" data-loading-text="Memuat...">
                            <i class="fas fa-filter" aria-hidden="true"></i>
                            <span>Tampilkan</span>
                        </button>
                    </form>
                </div>

                <div class="ui-panel__body">
                    <div class="ui-table-wrap">
                        <table class="table table-bordered table-striped table-sm align-middle ui-table">
                            <thead>
                                <tr>
                                    <th class="ui-table__col--index">{{ __('tables.no') }}</th>
                                    <th class="ui-table__col--date">{{ __('tables.date') }}</th>
                                    <th>{{ __('tables.national_holiday_name') }}</th>
                                    <th class="ui-table__col--action">{{ __('tables.action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($nationalHolidays as $index => $holiday)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ formatDateIndonesia($holiday->holiday_date) }}</td>
                                    <td>{{ $holiday->holiday_name }}</td>
                                    <td>
                                        <form action="{{ route('national-holidays.destroy', ['nationalHoliday' => $holiday->id, 'year' => $year]) }}" method="POST" data-confirm-submit="Hapus tanggal merah ini?" data-loading-text="Menghapus...">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm ui-btn-icon" data-loading-text="Menghapus...">
                                                <i class="fas fa-trash" aria-hidden="true"></i>
                                                <span>Hapus</span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4">
                                        <div class="ui-empty-state">
                                            <i class="fas fa-calendar-times" aria-hidden="true"></i>
                                            <span>Belum ada tanggal merah nasional pada tahun ini.</span>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/js/national-holidays.js') }}"></script>
@endpush
