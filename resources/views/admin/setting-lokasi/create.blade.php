@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-inner">

        {{-- HEADER --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1">
                    <i class="fas fa-map-marker-alt text-primary me-2"></i>
                    Penambahan Lokasi Presensi
                </h3>
                <small class="text-muted">
                    Tentukan titik lokasi presensi berdasarkan area/divisi
                </small>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">

                <form action="{{ route('setting-lokasi-presensi.store') }}" method="POST">
                    @csrf

                    {{-- FILTER SECTION --}}
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Area</label>
                            <select id="filter_area" class="form-select">
                                <option value="">Semua Area</option>
                                @foreach ($areas as $area)
                                <option value="{{ $area->kode_perusahaan }}">
                                    {{ $area->kode_perusahaan }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Departemen</label>
                            <select id="filter_departemen" class="form-select" disabled>
                                <option value="">Semua Departemen</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Divisi</label>
                            <select id="filter_divisi" name="divisi_id" class="form-select" disabled>
                                <option value="">Semua Divisi</option>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Radius (meter)</label>
                            <input type="number" name="radius" id="radius" class="form-control" value="100" min="10" step="10">
                        </div>
                    </div>

                    {{-- MAP SECTION --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold">
                            Titik Lokasi Presensi
                        </label>

                        <div id="location"
                            class="rounded border shadow-sm"
                            style="width:100%; height:400px;">
                        </div>

                        <div class="mt-3 text-end">
                            <button type="button"
                                class="btn btn-warning btn-sm px-4"
                                onclick="getLocation()">
                                <i class="fas fa-crosshairs me-1"></i>
                                Ambil Lokasi Saat Ini
                            </button>
                        </div>
                    </div>

                    {{-- COORDINATE SECTION --}}
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Latitude</label>
                            <input type="text"
                                name="lat"
                                id="latitude"
                                class="form-control"
                                readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Longitude</label>
                            <input type="text"
                                name="long"
                                id="longitude"
                                class="form-control"
                                readonly>
                        </div>
                    </div>

                    {{-- BUTTON SECTION --}}
                    <div class="mt-4 d-flex justify-content-end gap-2">
                        <a href="{{ route('cuti.index') }}"
                            class="btn btn-light border">
                            Kembali
                        </a>

                        <button type="submit"
                            class="btn btn-primary px-4">
                            <i class="fas fa-save me-1"></i>
                            Simpan Lokasi
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://maps.googleapis.com/maps/api/js?v=3"></script>

<script>
    let map;
    let marker;
    let circle;

    function initMap(lat = -6.200000, lng = 106.816666) {

        let center = new google.maps.LatLng(lat, lng);

        map = new google.maps.Map(document.getElementById("location"), {
            center: center,
            zoom: 16,
            mapTypeId: "hybrid", // terlihat lebih enterprise
            tilt: 45,
            heading: 90,
            streetViewControl: false,
            mapTypeControl: false,
            fullscreenControl: true
        });

        marker = new google.maps.Marker({
            position: center,
            map: map,
            draggable: true,
            animation: google.maps.Animation.DROP
        });

        circle = new google.maps.Circle({
            strokeColor: "#0d6efd",
            strokeOpacity: 0.8,
            strokeWeight: 2,
            fillColor: "#0d6efd",
            fillOpacity: 0.2,
            map: map,
            center: center,
            radius: 100
        });

        updateInputs(lat, lng);

        // Drag marker
        marker.addListener("dragend", function(event) {
            updateInputs(event.latLng.lat(), event.latLng.lng());
            circle.setCenter(event.latLng);
        });

        // Click map
        map.addListener("click", function(event) {
            marker.setPosition(event.latLng);
            circle.setCenter(event.latLng);
            updateInputs(event.latLng.lat(), event.latLng.lng());
        });
    }

    // ================= GET LOCATION =================
    function getLocation() {

        if (!navigator.geolocation) {
            alert("Browser tidak mendukung geolocation");
            return;
        }

        navigator.geolocation.getCurrentPosition(function(position) {

            let lat = position.coords.latitude;
            let lng = position.coords.longitude;

            initMap(lat, lng);

        }, function() {
            alert("Gagal mengambil lokasi");
        }, {
            enableHighAccuracy: true
        });
    }

    // ================= UPDATE INPUT =================
    function updateInputs(lat, lng) {
        document.getElementById("latitude").value = lat.toFixed(8);
        document.getElementById("longitude").value = lng.toFixed(8);
    }

    // ================= RADIUS DINAMIS =================
    document.getElementById("radius").addEventListener("input", function() {
        if (circle) {
            circle.setRadius(parseInt(this.value));
        }
    });

    // ================= INIT DEFAULT =================
    window.onload = function() {
        initMap();
    };
</script>

<script>
    // AREA berubah
    $('#filter_area').on('change', function() {
        let area = $(this).val();
        $('#filter_departemen').html('<option value="">Loading...</option>');
        $('#filter_divisi').html('<option value="">Semua Divisi</option>');

        if (!area) {
            $('#filter_departemen').html('<option value="">Semua Departemen</option>');
            table.draw();
            return;
        }

        $.get("{{ route('ajax.departemen.by.area') }}", {
            area
        }, function(res) {
            let opt = '<option value="">Semua Departemen</option>';
            res.forEach(r => {
                opt += `<option value="${r.id}">${r.departemen}</option>`;
            });
            $('#filter_departemen').html(opt);
            table.draw();
        });
    });

    // DEPARTEMEN berubah
    $('#filter_departemen').on('change', function() {
        let departemen = $(this).val();

        $('#filter_divisi').html('<option value="">Loading...</option>');

        if (!departemen) {
            $('#filter_divisi').html('<option value="">Semua Divisi</option>');
            table.draw();
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
            table.draw();
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
</script>
@endpush

@endsection