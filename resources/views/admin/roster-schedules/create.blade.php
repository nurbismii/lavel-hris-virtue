@extends('layouts.app')

@section('title', 'Generate Jadwal Roster')

@push('styles')
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" />
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4 gap-2">
            <div>
                <h4 class="fw-bold mb-1">Generate Jadwal Roster</h4>
                <small class="text-muted">Tanggal mulai adalah hari pertama masa kerja 10 minggu. Sistem otomatis membuat 14 hari off dan jadwal berikutnya setiap 84 hari.</small>
            </div>
            <div class="ms-md-auto"><a href="{{ route('roster-schedules.index') }}" class="btn btn-light border">Kembali</a></div>
        </div>

        <div class="row justify-content-center">
            <div class="col-xl-8">
                <div class="alert alert-info">
                    Proses aman dijalankan ulang. Jadwal dengan NIK dan tanggal mulai off yang sama tidak akan diduplikasi. Setiap 10 minggu kerja mencatat hak {{ config('roster.earned_off_days', 5) }} hari OFF.
                </div>
                <form action="{{ route('roster-schedules.store') }}" method="POST" id="rosterScheduleForm">
                    @csrf
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Karyawan aktif</label>
                                    <select name="employee_nik" id="employeeNik" class="form-select @error('employee_nik') is-invalid @enderror" data-search-url="{{ route('roster-schedules.employees.search') }}" required>
                                        @if($selectedEmployee)
                                        <option value="{{ $selectedEmployee->nik }}" selected>{{ $selectedEmployee->nama_karyawan }} - {{ $selectedEmployee->nik }}</option>
                                        @endif
                                    </select>
                                    @error('employee_nik')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Mulai masa kerja 10 minggu</label>
                                    <input type="date" name="work_start" id="workStart" value="{{ old('work_start', optional(optional($selectedEmployee)->work_pattern_start_date)->format('Y-m-d')) }}" class="form-control @error('work_start') is-invalid @enderror" required>
                                    @error('work_start')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Jumlah jadwal</label>
                                    <input type="number" name="cycles" value="{{ old('cycles', 10) }}" min="1" max="60" class="form-control @error('cycles') is-invalid @enderror" required>
                                    <small class="text-muted">10 jadwal kira-kira mencakup dua tahun.</small>
                                    @error('cycles')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-white text-end">
                            <button type="submit" class="btn btn-primary" data-loading-text="Membuat jadwal...">Generate Jadwal</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const select = $('#employeeNik');
    select.select2({
        width: '100%',
        placeholder: 'Ketik minimal 2 karakter nama atau NIK',
        ajax: {
            url: select.data('search-url'),
            dataType: 'json',
            delay: 300,
            data: function (params) { return { q: params.term || '', page: params.page || 1 }; },
            processResults: function (payload) { return payload; },
            cache: true
        }
    });

    select.on('select2:select', function (event) {
        const start = event.params.data.work_pattern_start_date;
        if (start && !document.getElementById('workStart').value) document.getElementById('workStart').value = start;
    });

    document.getElementById('rosterScheduleForm').addEventListener('submit', function () {
        const button = this.querySelector('button[type="submit"]');
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + button.dataset.loadingText;
    });
});
</script>
@endpush
