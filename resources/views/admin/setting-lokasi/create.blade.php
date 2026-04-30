@extends('layouts.app')

@push('styles')
<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin="">
@endpush

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
<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""></script>

<script>
    let map;
    let marker;
    let circle;
    const freeMapTileUrl = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
    const freeMapAttribution = 'Tiles &copy; Esri - Source: Esri, Maxar, Earthstar Geographics, and the GIS User Community';

    function radiusValue() {
        const value = parseInt(document.getElementById("radius").value, 10);

        return Number.isFinite(value) && value > 0 ? value : 100;
    }

    function initMap(lat = -6.200000, lng = 106.816666) {
        const center = [lat, lng];

        if (map) {
            map.remove();
        }

        map = L.map("location", {
            zoomControl: true,
            attributionControl: true
        }).setView(center, 16);

        L.tileLayer(freeMapTileUrl, {
            maxZoom: 19,
            attribution: freeMapAttribution
        }).addTo(map);

        marker = L.marker(center, {
            draggable: true,
            title: "Titik lokasi presensi"
        }).addTo(map);

        circle = L.circle(center, {
            color: "#0d6efd",
            opacity: 0.8,
            weight: 2,
            fillColor: "#0d6efd",
            fillOpacity: 0.2,
            radius: radiusValue()
        }).addTo(map);

        updateInputs(lat, lng);

        // Drag marker
        marker.on("dragend", function(event) {
            const position = event.target.getLatLng();
            setLocation(position.lat, position.lng, false);
        });

        // Click map
        map.on("click", function(event) {
            setLocation(event.latlng.lat, event.latlng.lng, false);
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

            if (!map) {
                initMap(lat, lng);
                return;
            }

            setLocation(lat, lng);

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

    function setLocation(lat, lng, moveMap = true) {
        const position = [lat, lng];

        if (marker) {
            marker.setLatLng(position);
        }

        if (circle) {
            circle.setLatLng(position);
        }

        if (moveMap && map) {
            map.setView(position, map.getZoom() || 16);
        }

        updateInputs(lat, lng);
    }

    // ================= RADIUS DINAMIS =================
    document.getElementById("radius").addEventListener("input", function() {
        if (circle) {
            circle.setRadius(radiusValue());
        }
    });

    // ================= INIT DEFAULT =================
    document.addEventListener("DOMContentLoaded", function() {
        initMap();
    });
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
