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
                    Setting Hari Off
                </h4>

                <small class="text-muted d-block">
                    Setting kehadiran: jika tercentang maka OFF, jika tidak dicentang maka HADIR.
                    (Cut Off {{ formatDateIndonesia($start) }} - {{ formatDateIndonesia($end) }})
                </small>
                <small class="text-muted d-block">Checked = OFF</small>
            </div>
        </div>

        <form method="GET" class="row g-2 mb-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Periode</label>
                <input type="month" name="periode" value="{{ $periode }}" class="form-control">
            </div>

            @if($isDepartmentScoped)
                <div class="col-md-3">
                    <label class="form-label">Departemen</label>
                    <input type="text" class="form-control" value="{{ optional($departemen)->departemen ?? '-' }}" readonly>
                </div>
            @else
                <div class="col-md-3">
                    <label class="form-label">Departemen</label>
                    <select id="filter_departemen" name="departemen" class="form-select form-control">
                        <option value="">Pilih Departemen</option>
                        @php
                            $groupedDepts = [];
                            foreach ($departemens as $dept) {
                                $groupedDepts[optional($dept->perusahaan)->nama_perusahaan ?? 'Lainnya'][] = $dept;
                            }
                        @endphp

                        @foreach($groupedDepts as $perusahaan => $deptItems)
                            <optgroup label="{{ $perusahaan }}">
                                @foreach($deptItems as $dept)
                                    <option value="{{ $dept->id }}" {{ (string) $selectedDepartemenId === (string) $dept->id ? 'selected' : '' }}>
                                        {{ $dept->departemen }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="col-md-4">
                <label class="form-label">Divisi</label>
                <select id="filter_divisi" name="divisi" class="form-select form-control" {{ !$selectedDepartemenId ? 'disabled' : '' }}>
                    <option value="">Semua Divisi</option>
                    @foreach ($divisis as $v)
                        <option value="{{ $v->id }}" {{ (string) $selectedDivisiId === (string) $v->id ? 'selected' : '' }}>
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

        @if(!$selectedDepartemenId)
            <div class="alert alert-info">
                Pilih departemen terlebih dahulu untuk menampilkan setting hari off.
            </div>
        @endif

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="table-set-kehadiran" class="table table-hover table-striped mb-0 table-xs small text-sm nowrap" style="width:100%">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama</th>
                                <th>NIK</th>
                                <th>Divisi</th>
                                <th>Departemen</th>
                                <th>Posisi</th>

                                @foreach($dates as $date)
                                    <th class="text-center">
                                        <div>{{ $date->format('d') }}</div>
                                        <div style="font-size:11px; color:#666;">
                                            {{ $date->translatedFormat('D') }}
                                        </div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($employees as $index => $employee)
                                <tr>
                                    <td>{{ ++$index }}</td>
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
                                            $checked = $isOff ? 'checked' : '';
                                        @endphp

                                        <td class="text-center">
                                            <input type="checkbox" class="attendance-checkbox" data-employee="{{ $employee->nik }}" data-date="{{ $date->toDateString() }}" {{ $checked }}>
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 6 + count($dates) }}" class="text-center text-muted py-4">
                                        {{ $selectedDepartemenId ? 'Tidak ada data karyawan untuk filter yang dipilih.' : 'Pilih departemen untuk mulai menampilkan data.' }}
                                    </td>
                                </tr>
                            @endforelse
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
        $('#table-set-kehadiran').DataTable({
            processing: true,
            scrollX: true,
            scrollY: "65vh",
            scrollCollapse: true,
            paging: true,
            fixedHeader: true,
            fixedColumns: {
                leftColumns: 3
            },
            pageLength: 10,
            ordering: false
        });

        const filterDepartemen = $('#filter_departemen');
        const filterDivisi = $('#filter_divisi');

        if (filterDepartemen.length) {
            filterDepartemen.on('change', function() {
                const departemen = $(this).val();

                filterDivisi.prop('disabled', true).html('<option value="">Loading...</option>');

                if (!departemen) {
                    filterDivisi.html('<option value="">Semua Divisi</option>').prop('disabled', true);
                    return;
                }

                $.get("{{ route('ajax.divisi.by.departemen') }}", {
                    departemen: departemen
                }).done(function(response) {
                    let options = '<option value="">Semua Divisi</option>';

                    response.forEach(function(item) {
                        options += `<option value="${item.id}">${item.nama_divisi}</option>`;
                    });

                    filterDivisi.html(options).prop('disabled', false);
                }).fail(function() {
                    filterDivisi.html('<option value="">Gagal memuat divisi</option>').prop('disabled', true);
                });
            });
        }
    });
</script>

<script>
    let dirtyCells = new Map();
    let debounceTimer = null;

    $(document).on('change', '.attendance-checkbox', function() {
        let checkbox = $(this);

        let employee = checkbox.data('employee');
        let date = checkbox.data('date');

        let newStatus = checkbox.is(':checked') ? 'OFF' : 'HADIR';
        let oldStatus = checkbox.data('status');

        if (newStatus === oldStatus) return;

        let key = employee + '_' + date;

        dirtyCells.set(key, {
            employee_id: employee,
            tanggal: date,
            status: newStatus,
            element: checkbox
        });

        checkbox.closest('td').addClass('table-warning');

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(sendBatch, 700);
    });

    async function sendBatch() {
        let payload = Array.from(dirtyCells.values());

        if (payload.length === 0) return;

        try {
            let response = await fetch("{{ route('set-kehadiran.update') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}"
                },
                body: JSON.stringify({
                    data: payload.map(p => ({
                        employee_id: p.employee_id,
                        tanggal: p.tanggal,
                        status: p.status
                    }))
                })
            });

            if (!response.ok) throw new Error();

            payload.forEach(item => {
                item.element.data('status', item.status);
                item.element.closest('td').removeClass('table-warning')
                    .addClass('table-success');

                setTimeout(() => {
                    item.element.closest('td').removeClass('table-success');
                }, 800);
            });

            dirtyCells.clear();
        } catch (e) {
            payload.forEach(item => {
                let oldStatus = item.element.data('status');

                item.element.prop('checked', oldStatus === 'OFF');
                item.element.closest('td').removeClass('table-warning');
            });

            alert('Update gagal');
        }
    }
</script>

<script src="https://cdn.datatables.net/fixedheader/3.4.0/js/dataTables.fixedHeader.min.js"></script>
<script src="https://cdn.datatables.net/fixedcolumns/4.3.0/js/dataTables.fixedColumns.min.js"></script>
@endpush

@endsection
