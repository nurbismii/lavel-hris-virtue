@extends('layouts.app')

@section('content')
<div class="container">

    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
            <div>
                <h3 class="fw-bold mb-3">Data Slip-Gaji</h3>
            </div>
        </div>

        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <div class="row mb-3 align-items-end">
                            <div class="col-md-8 mb-2">
                                <label class="form-label fw-semibold">Filter Periode</label>
                                <input type="month" id="filter_periode" class="form-control form-control-sm">
                            </div>

                            <div class="col-md-2 mb-2">
                                <button class="btn btn-sm btn-primary w-100" id="btnFilter">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                            </div>

                            <div class="col-md-2 mb-2">
                                <button class="btn btn-sm btn-secondary w-100" id="btnReset">
                                    <i class="fas fa-sync"></i> Reset
                                </button>
                            </div>
                        </div>
                        <table id="multi-filter-select" class="table table-bordered table-striped mb-0 table-sm small text-sm nowrap">
                            <thead>
                                <tr>
                                    <th>NIK</th>
                                    <th>Nama</th>
                                    <th>Periode</th>
                                    <th>Total Gaji</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<!-- Datatables -->
<script>
    let table = $('#multi-filter-select').DataTable({
        processing: true,
        serverSide: true,

        ajax: {
            url: "{{ route('slip-gaji.index') }}",
            data: function(d) {
                d.periode = $('#filter_periode').val();
            }
        },

        columns: [{
                data: 'nik',
                name: 'data_karyawans.nik'
            },
            {
                data: 'nama',
                name: 'data_karyawans.nama'
            },
            {
                data: 'periode',
                name: 'komponen_gajis.periode'
            },
            {
                data: 'total_gaji',
                name: 'komponen_gajis.tot_diterima'
            },
            {
                data: 'aksi',
                orderable: false,
                searchable: false
            },
        ]
    });

    $('#btnFilter').click(function() {
        table.ajax.reload();
    });

    $('#btnReset').click(function() {
        $('#filter_periode').val('');
        table.ajax.reload();
    });
</script>
@endpush

@endsection