@extends('layouts.app')

@section('title', 'Tambah Lokasi Presensi')

@push('styles')
<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin="">
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-inner ui-page">
        <div class="ui-page-header">
            <div class="ui-page-heading">
                <span class="ui-page-icon" aria-hidden="true">
                    <i class="fas fa-map-marker-alt"></i>
                </span>
                <div>
                    <h3 class="ui-page-title">Tambah Lokasi Presensi</h3>
                    <p class="ui-page-subtitle">Buat master titik lokasi. Pembagian karyawan dilakukan lewat assignment lokasi presensi.</p>
                </div>
            </div>
            <div class="ui-page-actions">
                <a href="{{ route('setting-lokasi-presensi.index') }}" class="btn btn-light border ui-btn-icon" data-loading-text="Kembali...">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    <span>Kembali</span>
                </a>
            </div>
        </div>

        <section class="ui-panel" aria-labelledby="attendanceLocationFormTitle">
            <div class="ui-panel__header">
                <div>
                    <h5 class="ui-panel__title" id="attendanceLocationFormTitle">Data Lokasi</h5>
                    <p class="ui-panel__meta">Klik peta atau tarik marker untuk menentukan titik presensi paling akurat.</p>
                </div>
            </div>

            <div class="ui-panel__body">
                <form action="{{ route('setting-lokasi-presensi.store') }}" method="POST" data-loading-text="Menyimpan lokasi...">
                    @csrf

                    <div class="row g-3 mb-3">
                        <div class="col-md-8 ui-field">
                            <label class="form-label" for="nama_lokasi">Nama Lokasi Presensi</label>
                            <input
                                type="text"
                                id="nama_lokasi"
                                name="nama_lokasi"
                                class="form-control @error('nama_lokasi') is-invalid @enderror"
                                value="{{ old('nama_lokasi') }}"
                                maxlength="150"
                                placeholder="Contoh: Gate Gudang B, Office VDNI, Mess Site A"
                                required>
                            @error('nama_lokasi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4 ui-field">
                            <label class="form-label" for="radius">Radius (meter)</label>
                            <input
                                type="number"
                                id="radius"
                                name="radius"
                                class="form-control @error('radius') is-invalid @enderror"
                                value="{{ old('radius', 100) }}"
                                min="1"
                                max="10000"
                                step="1"
                                required>
                            @error('radius')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="location">Titik Lokasi Presensi</label>
                        <div id="location" class="ui-map-frame"></div>
                        <div class="ui-map-hint">
                            <div class="ui-table-note">
                                Gunakan lokasi perangkat sebagai titik awal, lalu koreksi marker jika GPS belum presisi.
                            </div>
                            <button type="button" id="getCurrentLocationButton" class="btn btn-warning btn-sm ui-btn-icon" onclick="getLocation()">
                                <i class="fas fa-crosshairs" aria-hidden="true"></i>
                                <span>Ambil Lokasi Saat Ini</span>
                            </button>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6 ui-field">
                            <label class="form-label" for="latitude">Latitude</label>
                            <input
                                type="text"
                                id="latitude"
                                name="lat"
                                class="form-control @error('lat') is-invalid @enderror"
                                value="{{ old('lat') }}"
                                readonly
                                required>
                            @error('lat')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6 ui-field">
                            <label class="form-label" for="longitude">Longitude</label>
                            <input
                                type="text"
                                id="longitude"
                                name="long"
                                class="form-control @error('long') is-invalid @enderror"
                                value="{{ old('long') }}"
                                readonly
                                required>
                            @error('long')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="ui-section-divider"></div>

                    <div class="ui-actions ui-actions--end ui-actions--sm-stack">
                        <a href="{{ route('setting-lokasi-presensi.index') }}" class="btn btn-light border ui-btn-icon" data-loading-text="Kembali...">
                            <i class="fas fa-arrow-left" aria-hidden="true"></i>
                            <span>Kembali</span>
                        </a>
                        <button type="submit" class="btn btn-primary ui-btn-icon" data-loading-text="Menyimpan lokasi...">
                            <i class="fas fa-save" aria-hidden="true"></i>
                            <span>Simpan Lokasi</span>
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>
@endsection

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
        const value = parseInt(document.getElementById('radius').value, 10);

        return Number.isFinite(value) && value > 0 ? value : 100;
    }

    function updateLocationButton(isLoading) {
        const button = document.getElementById('getCurrentLocationButton');

        if (!button) {
            return;
        }

        button.disabled = isLoading;
        button.innerHTML = isLoading
            ? '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span><span>Mengambil lokasi...</span>'
            : '<i class="fas fa-crosshairs" aria-hidden="true"></i><span>Ambil Lokasi Saat Ini</span>';
    }

    function initMap(lat = -6.200000, lng = 106.816666) {
        const center = [lat, lng];

        if (map) {
            map.remove();
        }

        map = L.map('location', {
            zoomControl: true,
            attributionControl: true
        }).setView(center, 16);

        L.tileLayer(freeMapTileUrl, {
            maxZoom: 19,
            attribution: freeMapAttribution
        }).addTo(map);

        marker = L.marker(center, {
            draggable: true,
            title: 'Titik lokasi presensi'
        }).addTo(map);

        circle = L.circle(center, {
            color: '#146c94',
            opacity: 0.85,
            weight: 2,
            fillColor: '#146c94',
            fillOpacity: 0.18,
            radius: radiusValue()
        }).addTo(map);

        updateInputs(lat, lng);

        marker.on('dragend', function(event) {
            const position = event.target.getLatLng();
            setLocation(position.lat, position.lng, false);
        });

        map.on('click', function(event) {
            setLocation(event.latlng.lat, event.latlng.lng, false);
        });
    }

    function getLocation() {
        if (!navigator.geolocation) {
            window.AppDialog.alert(
                'Geolocation tidak tersedia',
                'Browser tidak mendukung geolocation.',
                'warning'
            );
            return;
        }

        updateLocationButton(true);

        navigator.geolocation.getCurrentPosition(function(position) {
            setLocation(position.coords.latitude, position.coords.longitude);
            updateLocationButton(false);
        }, function() {
            updateLocationButton(false);
            window.AppDialog.alert(
                'Gagal mengambil lokasi',
                'Sistem gagal mengambil lokasi perangkat. Silakan cek izin lokasi browser.',
                'error'
            );
        }, {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0
        });
    }

    function updateInputs(lat, lng) {
        document.getElementById('latitude').value = lat.toFixed(8);
        document.getElementById('longitude').value = lng.toFixed(8);
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

    document.getElementById('radius').addEventListener('input', function() {
        if (circle) {
            circle.setRadius(radiusValue());
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const oldLatitude = parseFloat(document.getElementById('latitude').value);
        const oldLongitude = parseFloat(document.getElementById('longitude').value);

        if (Number.isFinite(oldLatitude) && Number.isFinite(oldLongitude)) {
            initMap(oldLatitude, oldLongitude);
            return;
        }

        initMap();
    });
</script>
@endpush
