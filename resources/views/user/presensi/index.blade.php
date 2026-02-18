@extends('layouts.app')

@section('content')
<div class="container">
    <div class="page-inner">
        <h4 class="mb-3">Presensi Karyawan</h4>
        @if (!$lokasi)
        <div class="alert alert-danger">
            Lokasi presensi untuk divisi Anda belum diatur.
        </div>
        @else
        <div class="card shadow border-0">

            <div class="card-body p-4">

                {{-- MAP --}}
                <div class="mb-4">
                    <div id="map" style="height:350px; border-radius:15px;"></div>

                    <div class="mt-3">
                        <div class="alert alert-light border mb-0">
                            <strong>Status Lokasi :</strong>
                            <span id="distanceInfo" class="fw-bold text-primary">
                                Mendeteksi lokasi...
                            </span>
                        </div>
                    </div>
                </div>

                {{-- STATUS PRESENSI --}}
                <div class="row text-center mb-4">

                    <div class="col">
                        <div class="small text-muted">Masuk</div>
                        <div class="fw-bold">
                            {{ $absensiHariIni->jam_masuk ?? '--:--' }}
                        </div>
                    </div>

                    <div class="col">
                        <div class="small text-muted">Istirahat</div>
                        <div class="fw-bold">
                            {{ $absensiHariIni->jam_istirahat ?? '--:--' }}
                        </div>
                    </div>

                    <div class="col">
                        <div class="small text-muted">Kembali</div>
                        <div class="fw-bold">
                            {{ $absensiHariIni->jam_kembali_istirahat ?? '--:--' }}
                        </div>
                    </div>

                    <div class="col">
                        <div class="small text-muted">Pulang</div>
                        <div class="fw-bold">
                            {{ $absensiHariIni->jam_pulang ?? '--:--' }}
                        </div>
                    </div>

                </div>

                {{-- TOMBOL UTAMA --}}
                @php
                $nextType = null;
                $label = '';
                $btnClass = 'btn-primary';

                if (!$absensiHariIni || !$absensiHariIni->jam_masuk) {
                $nextType = 'masuk';
                $label = 'Absen Masuk';
                $btnClass = 'btn-primary';
                } elseif (!$absensiHariIni->jam_istirahat) {
                $nextType = 'istirahat';
                $label = 'Mulai Istirahat';
                $btnClass = 'btn-warning';
                } elseif (!$absensiHariIni->jam_kembali_istirahat) {
                $nextType = 'kembali';
                $label = 'Kembali Istirahat';
                $btnClass = 'btn-info';
                } elseif (!$absensiHariIni->jam_pulang) {
                $nextType = 'pulang';
                $label = 'Absen Pulang';
                $btnClass = 'btn-danger';
                }
                @endphp

                <div class="d-grid">
                    @if ($nextType)
                    <button class="btn {{ $btnClass }} btn-absen shadow"
                        data-type="{{ $nextType }}">
                        {{ $label }}
                    </button>
                    @else
                    <button class="btn btn-success shadow" disabled>
                        Presensi Hari Ini Selesai ✅
                    </button>
                    @endif
                </div>

                <form id="formAbsen" method="POST">
                    @csrf
                    <input type="hidden" name="lat_user" id="lat_user">
                    <input type="hidden" name="long_user" id="long_user">
                </form>
            </div>
        </div>


        <hr class="my-5">

        <h5 class="mb-3">
            Riwayat Presensi
            ({{ formatDateIndonesia($cutoffStart) }} - {{ formatDateIndonesia($cutoffEnd) }})
        </h5>

        <div class="card">
            <div class="card-body table-responsive">
                <table id="table-presensi" class="table table-bordered table-striped mb-0 table-sm small text-sm nowrap">
                    <thead class="table-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>Masuk</th>
                            <th>Istirahat</th>
                            <th>Kembali</th>
                            <th>Pulang</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($presensi as $item)
                        <tr>
                            <td>{{ formatDateIndonesia($item->tanggal) }}</td>
                            <td>{{ $item->jam_masuk ?? '-' }}</td>
                            <td>{{ $item->jam_istirahat ?? '-' }}</td>
                            <td>{{ $item->jam_kembali_istirahat ?? '-' }}</td>
                            <td>{{ $item->jam_pulang ?? '-' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-muted">
                                Tidak ada data pada periode ini
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>

@endsection

@push('scripts')
<script src="https://maps.googleapis.com/maps/api/js?v=3"></script>
@if ($lokasi)
<script>
    let map;
    let markerUser;
    let markerOffice;
    let circleOffice;
    let currentDistance = 0;

    function initMap(latOffice, longOffice, radius) {

        map = new google.maps.Map(document.getElementById("map"), {
            zoom: 17,
            center: {
                lat: latOffice,
                lng: longOffice
            },
            mapTypeId: "hybrid",
        });

        // Marker Kantor
        markerOffice = new google.maps.Marker({
            position: {
                lat: latOffice,
                lng: longOffice
            },
            map: map,
            title: "Lokasi presensi",
            icon: "https://maps.google.com/mapfiles/ms/icons/red-dot.png"
        });

        // Circle Radius
        circleOffice = new google.maps.Circle({
            strokeColor: "#fd0d0d",
            strokeOpacity: 0.8,
            strokeWeight: 2,
            fillColor: "#fd0d0d",
            fillOpacity: 0.2,
            map,
            center: {
                lat: latOffice,
                lng: longOffice
            },
            radius: radius
        });
    }

    // Haversine
    function getDistance(lat1, lon1, lat2, lon2) {
        let R = 6371000;
        let dLat = (lat2 - lat1) * Math.PI / 180;
        let dLon = (lon2 - lon1) * Math.PI / 180;
        let a =
            Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(lat1 * Math.PI / 180) *
            Math.cos(lat2 * Math.PI / 180) *
            Math.sin(dLon / 2) * Math.sin(dLon / 2);
        let c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    }

    document.addEventListener("DOMContentLoaded", function() {

        let latOffice = {{$lokasi->lat}};
        let longOffice = {{$lokasi->long}};
        let radius = {{$lokasi->radius}};
        let gpsReady = false;
        let gpsTimer = null;

        initMap(latOffice, longOffice, radius);

        if (navigator.geolocation) {

            let gpsReady = false;
            let gpsTimer = null;

            navigator.geolocation.watchPosition(function(position) {

                let latUser = position.coords.latitude;
                let longUser = position.coords.longitude;
                let accuracy = position.coords.accuracy;

                // Tampilkan info akurasi realtime
                document.getElementById("distanceInfo").innerHTML =
                    "Mencari GPS... Akurasi: " + Math.round(accuracy) + " meter";

                // Jika accuracy masih besar
                if (accuracy > 70) {
                    gpsReady = false;
                    toggleAbsenButton(false);

                    document.getElementById("distanceInfo").innerHTML =
                        "<span class='text-warning'>Menunggu GPS stabil... (" +
                        Math.round(accuracy) + "m)</span>";

                    return;
                }

                // Marker User
                if (markerUser) {
                    markerUser.setPosition({
                        lat: latUser,
                        lng: longUser
                    });
                } else {
                    markerUser = new google.maps.Marker({
                        position: {
                            lat: latUser,
                            lng: longUser
                        },
                        map: map,
                        title: "Posisi kamu",
                        icon: "https://maps.google.com/mapfiles/ms/icons/blue-dot.png"
                    });
                }

                currentDistance = getDistance(latUser, longUser, latOffice, longOffice);

                let radius = {{$lokasi->radius}};

                if (currentDistance <= radius) {
                    document.getElementById("distanceInfo").innerHTML =
                        "<span class='text-success'>" +
                        currentDistance.toFixed(1) +
                        " meter (Dalam Radius)</span>";

                    gpsReady = true;
                    toggleAbsenButton(true);

                } else {
                    document.getElementById("distanceInfo").innerHTML =
                        "<span class='text-danger'>" +
                        currentDistance.toFixed(1) +
                        " meter (Di luar radius)</span>";

                    gpsReady = false;
                    toggleAbsenButton(false);
                }

                document.getElementById("lat_user").value = latUser;
                document.getElementById("long_user").value = longUser;

            }, function(error) {

                document.getElementById("distanceInfo").innerHTML =
                    "<span class='text-danger'>Gagal mengambil lokasi</span>";

            }, {
                enableHighAccuracy: true,
                timeout: 20000,
                maximumAge: 0
            });

        }
    });

    // Tombol Absen
    document.querySelectorAll(".btn-absen").forEach(button => {

        button.addEventListener("click", function() {

            if (!gpsReady) {
                Swal.fire({
                    icon: 'warning',
                    title: 'GPS belum siap',
                    text: 'Tunggu hingga lokasi stabil sebelum absen.'
                });
                return;
            }

            let type = this.dataset.type;
            let form = document.getElementById("formAbsen");
            form.action = `/absen/${type}`;
            form.submit();

        });

    });

    function toggleAbsenButton(status) {
        document.querySelectorAll(".btn-absen").forEach(button => {
            button.disabled = !status;
        });
    }
</script>
@endif

<script>
    $(document).ready(function() {
        $("#table-presensi").DataTable({
            order: [
                [1, 'desc']
            ] // kolom index 1, urut terbaru dulu
        });
    });
</script>

@endpush