@extends('layouts.app')

@push('styles')
<style>
    .highlight-match {
        background-color: #fff3cd;
        padding: 0 2px;
        border-radius: 3px;
    }
</style>
@endpush

@section('content')
<div class="container">

    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
            <div>
                <h4 class="fw-bold">
                    <i class="fas fa-user-friends text-primary me-2"></i>
                    Distribusi Wilayah
                </h4>

                <small class="text-muted">
                    Distribusi Karyawan per Wilayah
                </small>
            </div>
        </div>

        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="multi-filter-select" class="table table-bordered table-striped mb-0 table-sm small text-sm nowrap">
                            <thead class="table-light">
                                <tr>
                                    <th rowspan="2">Provinsi</th>
                                    <th colspan="3" class="text-center">Jumlah Karyawan</th>
                                </tr>
                                <tr>
                                    <th>Laki Laki</th>
                                    <th>Perempuan</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($response as $region => $provinsis)

                                @php
                                $region_id = Str::slug($region);

                                $total_l = collect($provinsis)->sum('laki-laki');
                                $total_p = collect($provinsis)->sum('perempuan');
                                $total_all = collect($provinsis)->sum('jumlah');
                                @endphp

                                <tr>
                                    <td>{{ strtoupper($region) }}</td>
                                    <td>{{ $total_l }}</td>
                                    <td>{{ $total_p }}</td>
                                    <td>
                                        {{ $total_all }}
                                        <button class="btn btn-sm btn-outline-primary ms-2 float-end"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#region-{{ $region_id }}">
                                            Detail
                                        </button>
                                    </td>
                                </tr>

                                <tr class="collapse" id="region-{{ $region_id }}">
                                    <td colspan="8" class="p-0">
                                        <div class="p-3">
                                            <input type="text"
                                                class="form-control mb-2 search-detail"
                                                placeholder="Cari Kabupaten, Kecamatan, atau Kelurahan..."
                                                data-target="#region-{{ $region_id }}">
                                        </div>

                                        <table class="table table-bordered mb-0">

                                            @foreach ($provinsis as $prov)
                                            <thead class="table-light">
                                                <tr>
                                                    <th colspan="8" class="ps-4">{{ $prov['nama'] }}</th>
                                                </tr>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Provinsi</th>
                                                    <th>Kabupaten</th>
                                                    <th>Kecamatan</th>
                                                    <th>Kelurahan</th>
                                                    <th>L</th>
                                                    <th>P</th>
                                                    <th>Total</th>
                                                </tr>
                                            </thead>

                                            <tbody>
                                                @php $no = 1; @endphp

                                                @foreach ($prov['kabupaten'] as $kab)
                                                @foreach ($kab['kecamatan'] as $kec)
                                                @foreach ($kec['kelurahan'] as $kel)

                                                <tr class="searchable-row">
                                                    <td>{{ $no++ }}</td>
                                                    <td>{{ $prov['nama'] }}</td>
                                                    <td>{{ $kab['nama'] }}</td>
                                                    <td>{{ $kec['nama'] }}</td>
                                                    <td>{{ $kel['nama'] }}</td>
                                                    <td>{{ $kel['laki-laki'] }}</td>
                                                    <td>{{ $kel['perempuan'] }}</td>
                                                    <td>{{ $kel['jumlah'] }}</td>
                                                </tr>

                                                @endforeach

                                                {{-- TOTAL KECAMATAN --}}
                                                <tr class="row-total-kec table-light">
                                                    <td colspan="5" class="text-end">
                                                        TOTAL KEC. {{ $kec['nama'] }}
                                                    </td>
                                                    <td>{{ $kec['laki-laki'] }}</td>
                                                    <td>{{ $kec['perempuan'] }}</td>
                                                    <td>{{ $kec['jumlah'] }}</td>
                                                </tr>

                                                @endforeach

                                                {{-- TOTAL KABUPATEN --}}
                                                <tr class="row-total-kab table-primary fw-semibold">
                                                    <td colspan="5" class="text-end">
                                                        TOTAL KAB. {{ $kab['nama'] }}
                                                    </td>
                                                    <td>{{ $kab['laki-laki'] }}</td>
                                                    <td>{{ $kab['perempuan'] }}</td>
                                                    <td>{{ $kab['jumlah'] }}</td>
                                                </tr>

                                                @endforeach

                                                {{-- TOTAL PROVINSI --}}
                                                <tr class="table-secondary fw-bold">
                                                    <td colspan="5" class="text-end">
                                                        TOTAL {{ $prov['nama'] }}
                                                    </td>
                                                    <td>{{ $prov['laki-laki'] }}</td>
                                                    <td>{{ $prov['perempuan'] }}</td>
                                                    <td>{{ $prov['jumlah'] }}</td>
                                                </tr>

                                            </tbody>
                                            @endforeach

                                        </table>
                                    </td>
                                </tr>

                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')

<script>
    $(document).ready(function() {

        // ===============================
        // DEBOUNCE
        // ===============================
        function debounce(func, delay) {
            let timer;
            return function() {
                const context = this;
                const args = arguments;

                clearTimeout(timer);
                timer = setTimeout(function() {
                    func.apply(context, args);
                }, delay);
            };
        }

        // ===============================
        // REMOVE OLD HIGHLIGHT
        // ===============================
        function removeHighlight($region) {
            $region.find('.highlight-match').each(function() {
                $(this).replaceWith($(this).text());
            });
        }

        // ===============================
        // APPLY HIGHLIGHT
        // ===============================
        function applyHighlight($elements, keyword) {
            if (!keyword) return;

            const regex = new RegExp('(' + keyword + ')', 'gi');

            $elements.each(function() {
                const originalText = $(this).text();
                const newText = originalText.replace(regex, '<span class="highlight-match">$1</span>');
                $(this).html(newText);
            });
        }

        // ===============================
        // FILTER FUNCTION
        // ===============================
        function filterRegion() {

            const keyword = $(this).val().toLowerCase();
            const regionSelector = $(this).data('target');
            const $region = $(regionSelector);

            const $rows = $region.find('.searchable-row');
            const $totalKec = $region.find('.row-total-kec');
            const $totalKab = $region.find('.row-total-kab');

            // Reset highlight dulu
            removeHighlight($region);

            // 1️⃣ Filter rows
            $rows.each(function() {
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.includes(keyword));
            });

            // 2️⃣ Toggle total kecamatan
            $totalKec.each(function() {
                const hasVisibleChild =
                    $(this).prevUntil('.row-total-kec, .row-total-kab')
                    .filter('.searchable-row:visible')
                    .length > 0;

                $(this).toggle(hasVisibleChild);
            });

            // 3️⃣ Toggle total kabupaten
            $totalKab.each(function() {
                const hasVisibleKec =
                    $(this).prevUntil('.row-total-kab')
                    .filter('.row-total-kec:visible')
                    .length > 0;

                $(this).toggle(hasVisibleKec);
            });

            // 4️⃣ Highlight keyword di baris yang visible
            if (keyword) {
                const $visibleCells = $region
                    .find('.searchable-row:visible td:nth-child(2), \
                       .searchable-row:visible td:nth-child(3), \
                       .searchable-row:visible td:nth-child(4), \
                       .searchable-row:visible td:nth-child(5)');

                applyHighlight($visibleCells, keyword);
            }
        }

        // ===============================
        // APPLY DEBOUNCE 300ms
        // ===============================
        $(document).on(
            'input',
            '.search-detail',
            debounce(filterRegion, 300)
        );

        // ===============================
        // RESET SAAT COLLAPSE TUTUP
        // ===============================
        $(document).on('hide.bs.collapse', '.collapse', function() {

            const $region = $(this);

            removeHighlight($region);
            $region.find('.search-detail').val('');
            $region.find('.searchable-row').show();
            $region.find('.row-total-kec').show();
            $region.find('.row-total-kab').show();
        });

    });
</script>

@endpush

@endsection