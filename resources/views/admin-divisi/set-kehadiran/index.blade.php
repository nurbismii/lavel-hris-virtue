@extends('layouts.app')

@push('styles')
<style>
    .attendance-select {
        min-width: 85px !important;
        font-size: 12px;
        padding: 2px 6px;
    }

    .attendance-select option {
        color: #000;
    }

    .dataTables_filter {
        position: sticky;
        top: 0;
        background: white;
        z-index: 1050;
        padding: 10px 0;
    }

    .dataTables_paginate {
        position: sticky;
        bottom: 0;
        background: white;
        z-index: 1050;
        padding: 10px 0;
    }
</style>

<link rel="stylesheet" href="https://cdn.datatables.net/fixedheader/3.4.0/css/fixedHeader.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/fixedcolumns/4.3.0/css/fixedColumns.dataTables.min.css">
@endpush

@section('content')
<div class="container-fluid">

    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
            <div>
                <h4 class="fw-bold">
                    <i class="fas fa-cog text-primary me-2"></i>
                    Setting Kehadiran
                </h4>

                <small class="text-muted">
                    Setting Kehadiran (Cut Off 16 - 15)
                </small>
            </div>
        </div>

        <form method="GET" class="row g-2 mb-3 align-items-end">

            <div class="col-md-3">
                <label class="form-label">Periode</label>
                <input type="month" name="periode" value="{{ $periode }}" class="form-control">
            </div>

            <div class="col-md-3">
                <label class="form-label">Departemen</label>
                <input type="text" class="form-control" value="{{ $departemen->departemen }}">
            </div>

            <div class="col-md-4">
                <label class="form-label">Divisi</label>
                <select id="filter_divisi" name="divisi" class="form-select form-control">
                    <option value="">Semua Divisi</option>
                    @foreach ($divisis as $v)
                    <option value="{{ $v->id }}">
                        {{ $v->nama_divisi }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2 d-grid">
                <button class="btn btn-primary">
                    Tampilkan
                </button>
            </div>

        </form>

        <div class="card shadow-sm border-0">
            <div class="card-body">

                <div class="table-responsive">
                    <table id="table-set-kehadiran" class="table table-bordered table-striped mb-0 table-sm small text-sm nowrap" style="width:100%">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>NIK</th>
                                <th>Divisi</th>
                                <th>Departemen</th>
                                <th>Posisi</th>

                                @foreach($dates as $date)
                                <th>{{ $date->format('d') }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($employees as $employee)
                            <tr>
                                <td>{{ $employee->nama_karyawan }}</td>
                                <td>{{ $employee->nik }}</td>
                                <td>{{ optional($employee->divisi)->nama_divisi ?? '-' }}</td>
                                <td>{{ optional($employee->departemen)->departemen ?? '-' }}</td>
                                <td>{{ optional($employee)->posisi ?? '-' }}</td>

                                @foreach($dates as $date)
                                @php
                                $empAttendance = $offData->get($employee->nik);

                                $isOff = $empAttendance
                                ? $empAttendance->firstWhere('tanggal', $date->toDateString())
                                : null;

                                $status = $isOff ? 'OFF' : 'HADIR';
                                @endphp

                                <td>
                                    <select
                                        class="form-select form-select-sm attendance-select"
                                        data-employee="{{ $employee->nik }}"
                                        data-date="{{ $date->toDateString() }}">

                                        <option value="HADIR" {{ $status === 'HADIR' ? 'selected' : '' }}>
                                            H
                                        </option>

                                        <option value="OFF" {{ $status === 'OFF' ? 'selected' : '' }}>
                                            O
                                        </option>

                                    </select>
                                </td>
                                @endforeach
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {

        let table = $('#table-set-kehadiran').DataTable({
            processing: true,
            scrollX: true,
            scrollY: "65vh",
            scrollCollapse: true,
            paging: true,
            fixedHeader: true,
            fixedColumns: {
                leftColumns: 2 
            },
            pageLength: 10,
            ordering: false
        });

    });
</script>

<script>
    $(document).on('change', '.attendance-select', function() {

        fetch("{{ route('set-kehadiran.update') }}", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": "{{ csrf_token() }}"
            },
            body: JSON.stringify({
                employee_id: $(this).data('employee'),
                tanggal: $(this).data('date'),
                status: $(this).val()
            })
        });

    });
</script>

<script src="https://cdn.datatables.net/fixedheader/3.4.0/js/dataTables.fixedHeader.min.js"></script>
<script src="https://cdn.datatables.net/fixedcolumns/4.3.0/js/dataTables.fixedColumns.min.js"></script>
@endpush

@endsection