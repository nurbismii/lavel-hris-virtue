@extends('layouts.app')

@section('content')

<div class="container">
    <div class="page-inner">

        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-users text-primary me-2"></i>
                    Data presensi keseluruhan
                </h4>
                <small id="cutoffLabel" class="text-muted">
                    Pilih Cut off
                </small>
            </div>

            <div class="ms-md-auto py-2 py-md-0">
                <a class="btn btn-sm btn-primary" id="btnExport">
                    Export CSV
                </a>
            </div>
        </div>

        <div class="card shadow">

            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Area</label>
                        <select id="filter_area" class="form-select form-control">
                            <option value="">Pilih Area</option>
                            @foreach ($areas as $area)
                            <option value="{{ $area->kode_perusahaan }}">{{ $area->kode_perusahaan }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label>Departemen</label>
                        <select id="filter_departemen" class="form-select form-control">
                            <option value="">Semua Departemen</option>
                            @php
                            $groupedDepts = [];
                            foreach ($departemens as $d) {
                            $groupedDepts[$d->perusahaan['nama_perusahaan']][] = $d;
                            }
                            @endphp

                            @foreach($groupedDepts as $perusahaan => $departemens)
                            <optgroup label="{{ $perusahaan }}">
                                @foreach($departemens as $d)
                                <option value="{{ $d->id }}">{{ $d->departemen }}</option>
                                @endforeach
                            </optgroup>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label>Divisi</label>
                        <select id="filter_divisi" class="form-select form-control">
                            <option value="">Semua Divisi</option>
                            @foreach ($divisis as $v)
                            <option value="{{ $v->id }}">{{ $v->nama_divisi }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label>Cutoff Month</label>
                        <input type="month" id="cutoff_month" class="form-control">
                    </div>

                    <div class="col-md-2 align-self-end">
                        <button id="btnFilter" class="btn btn-primary w-100">
                            Filter Data
                        </button>
                    </div>

                </div>

                {{-- TABLE --}}
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="table-presensi" style="width:100%">
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    let table = null;

    function buildTable(columns) {

        if (table !== null) {
            table.destroy();
            $('#table-presensi').empty();
        }

        table = $('#table-presensi').DataTable({
            processing: true,
            serverSide: true,
            pageLength: 50,
            scrollX: true,
            fixedHeader: true,

            ajax: {
                url: "{{ route('fetch.data-presensi') }}",
                type: "GET",
                data: function(d) {
                    d.cutoff_month = $('#cutoff_month').val();
                    d.departemen = $('#filter_departemen').val();
                    d.divisi = $('#filter_divisi').val();
                }
            },

            columns: columns
        });
    }

    function loadData() {

        if (!$('#filter_departemen').val()) {
            alert("Silakan pilih Departemen terlebih dahulu");
            return;
        }

        $.get("{{ route('fetch.data-presensi') }}", {
            cutoff_month: $('#cutoff_month').val(),
            departemen: $('#filter_departemen').val(),
            divisi: $('#filter_divisi').val(),
            length: 1
        }, function(res) {

            if (!res.tanggalHeaders) return;

            let cols = [{
                    data: 'nik_karyawan',
                    title: 'NIK'
                },
                {
                    data: 'nama_karyawan',
                    title: 'Nama'
                }
            ];

            res.tanggalHeaders.forEach(tgl => {
                cols.push({
                    data: `tanggal_data.${tgl}`,
                    title: moment(tgl).format('DD'),
                    orderable: false,
                    searchable: false,
                    render: function(data) {

                        if (!data) return '-';

                        const formatTime = (val) => {
                            if (!val) return '-';

                            // kalau format datetime → ambil jam:menit
                            if (val.length >= 16) {
                                return val.substring(11, 16);
                            }

                            // kalau format HH:mm:ss
                            if (val.length >= 5) {
                                return val.substring(0, 5);
                            }

                            return val;
                        };

                        return `
                        <div style="
                            font-size:11px;
                            display:grid;
                            grid-template-columns: 1fr 1fr;
                            gap:2px 6px;
                            text-align:left;
                        ">
                            <div>${data.m ?? '-'}</div>
                            <div>${data.i ?? '-'}</div>
                            <div>${data.k ?? '-'}</div>
                            <div>${data.p ?? '-'}</div>
                        </div>
                    `;
                    }
                });
            });

            buildTable(cols);
        });
    }

    $('#btnFilter').click(function() {
        loadData();
    });

    // AREA berubah
    $('#filter_area').on('change', function() {
        let area = $(this).val();
        $('#filter_departemen').html('<option value="">Loading...</option>');
        $('#filter_divisi').html('<option value="">Semua Divisi</option>');

        if (!area) {
            $('#filter_departemen').html('<option value="">Semua Departemen</option>');
        }

        $.get("{{ route('ajax.departemen.by.area') }}", {
            area
        }, function(res) {
            let opt = '<option value="">Semua Departemen</option>';
            res.forEach(r => {
                opt += `<option value="${r.id}">${r.departemen}</option>`;
            });
            $('#filter_departemen').html(opt);
        });
    });

    // DEPARTEMEN berubah
    $('#filter_departemen').on('change', function() {
        let departemen = $(this).val();

        $('#filter_divisi').html('<option value="">Loading...</option>');

        if (!departemen) {
            $('#filter_divisi').html('<option value="">Semua Divisi</option>');
            return;
        }

        $.get("{{ route('ajax.divisi.by.departemen') }}", {
            departemen
        }, function(res) {
            let opt = '<option value="">Semua Divisi</option>';
            res.forEach(r => {
                opt += `<option value="${r.id}">${r.nama_divisi}</option>`;
            });
            $('#filter_divisi').html(opt);
        });
    });

    $('#filter_departemen, #filter_divisi').prop('disabled', true);

    $('#filter_area').on('change', function() {
        $('#filter_departemen').prop('disabled', !this.value);
        $('#filter_divisi').prop('disabled', true);
    });

    $('#filter_departemen').on('change', function() {
        $('#filter_divisi').prop('disabled', !this.value);
    });

    function updateCutoffLabel() {

        let month = $('#cutoff_month').val();

        if (!month) {
            $('#cutoffLabel').text('Pilih Cutoff');
            return;
        }

        let start = moment(month).subtract(1, 'month').startOf('month').add(15, 'days');
        let end = moment(month).startOf('month').add(14, 'days');

        let label = `Cutoff ${start.format('DD MMM YYYY')} - ${end.format('DD MMM YYYY')}`;

        $('#cutoffLabel').text(label);
    }

    $('#cutoff_month').change(function() {
        updateCutoffLabel();
    });

    $(document).ready(function() {

        let now = moment().format('YYYY-MM');
        $('#cutoff_month').val(now);

        updateCutoffLabel();
    });
</script>

<script>
    $('#btnExport').click(function() {

        let departemen = $('#filter_departemen').val();
        let divisi = $('#filter_divisi').val();
        let cutoff = $('#cutoff_month').val();

        if (!departemen) {
            alert("Pilih departemen terlebih dahulu");
            return;
        }

        let url = "{{ route('presensi.export') }}" +
            "?departemen=" + departemen +
            "&divisi=" + divisi +
            "&cutoff_month=" + cutoff;

        window.open(url, '_blank');
    });
</script>

{{-- moment.js untuk format tanggal --}}
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>

@endpush

@endsection