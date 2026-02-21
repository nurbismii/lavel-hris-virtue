@extends('layouts.app')

@section('content')
<div class="container">

    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
            <div>
                <h4 class="fw-bold">
                    <i class="fas fa-file-invoice-dollar text-primary me-2"></i>
                    Data V-Payslip
                </h4>
                <small class="text-muted">
                    Integrasi dengan data V-Payslip
                </small>
            </div>
        </div>

        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="multi-filter-select" class="table table-bordered table-striped mb-0 table-sm small text-sm">
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
        responsive: true,

        ajax: {
            url: "{{ route('slipgaji.index') }}"
        },

        columns: [{
                data: 'nik',
                name: 'data_karyawans.nik',
                responsivePriority: 2
            },
            {
                data: 'nama',
                name: 'data_karyawans.nama',
                responsivePriority: 1
            },
            {
                data: 'periode',
                name: 'komponen_gajis.periode',
                responsivePriority: 3
            },
            {
                data: 'total_gaji',
                name: 'komponen_gajis.tot_diterima',
                responsivePriority: 4
            },
            {
                data: 'aksi',
                orderable: false,
                searchable: false
            },
        ]
    });
</script>
@endpush

@endsection