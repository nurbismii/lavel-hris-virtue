@extends('layouts.app')

@push('styles')
<style>
    .attendance-shell {
        display: grid;
        gap: 1.5rem;
    }

    .attendance-hero {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.2rem 1.35rem;
        border-radius: 24px;
        background:
            radial-gradient(circle at top right, rgba(13, 110, 253, 0.14), transparent 30%),
            linear-gradient(135deg, #ffffff 0%, #f4f9ff 100%);
        border: 1px solid rgba(13, 110, 253, 0.08);
    }

    .attendance-kicker,
    .map-chip,
    .history-chip,
    .face-status-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 700;
    }

    .attendance-kicker,
    .map-chip {
        color: #0d6efd;
        background: rgba(13, 110, 253, 0.1);
        border: 1px solid rgba(13, 110, 253, 0.1);
    }

    .attendance-hero__title,
    .section-title,
    .history-header__title {
        color: #0f172a;
        font-weight: 700;
    }

    .attendance-hero__title {
        margin: 0.8rem 0 0.35rem;
        font-size: 1.45rem;
    }

    .attendance-hero__text,
    .section-subtitle,
    .history-header__text {
        margin: 0;
        color: #64748b;
        line-height: 1.6;
    }

    .attendance-steps {
        display: flex;
        flex-wrap: wrap;
        gap: 0.7rem;
    }

    .attendance-step,
    .attendance-metric,
    .face-photo-card {
        border-radius: 18px;
        border: 1px solid rgba(148, 163, 184, 0.14);
        background: #ffffff;
        box-shadow: 0 10px 26px rgba(15, 23, 42, 0.06);
    }

    .attendance-step {
        min-width: 116px;
        padding: 0.8rem 0.9rem;
    }

    .attendance-step__label,
    .section-caption,
    .location-status-card__label,
    .attendance-metric__label {
        display: block;
        margin-bottom: 0.25rem;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #94a3b8;
    }

    .attendance-step__value,
    .attendance-metric__time {
        color: #0f172a;
        font-weight: 700;
    }

    .attendance-card {
        border: 0;
        border-radius: 24px;
        background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
        box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
    }

    .attendance-card__body,
    .face-section {
        padding: 1.25rem;
    }

    .map-card__header,
    .face-section__header,
    .history-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .map-frame {
        overflow: hidden;
        border-radius: 22px;
        border: 1px solid rgba(15, 23, 42, 0.08);
    }

    .map-surface {
        height: 360px;
        border-radius: 22px;
    }

    .location-status-card {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-top: 1rem;
        padding: 1rem 1.1rem;
        border-radius: 18px;
        background: #f8fafc;
        border: 1px solid rgba(15, 23, 42, 0.06);
    }

    .location-status-card__icon {
        width: 46px;
        height: 46px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        background: rgba(13, 110, 253, 0.1);
        color: #0d6efd;
        font-size: 18px;
    }

    .location-status-card__value {
        font-size: 15px;
        font-weight: 700;
        color: #0f172a;
    }

    .attendance-summary {
        margin-top: 1.2rem;
    }

    .attendance-metric {
        height: 100%;
        padding: 1rem;
    }

    .attendance-action-card {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-top: 1.25rem;
        padding: 1.1rem 1.2rem;
        border-radius: 22px;
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        box-shadow: 0 18px 36px rgba(15, 23, 42, 0.2);
    }

    .attendance-action-card__caption {
        display: block;
        margin-bottom: 0.35rem;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: rgba(255, 255, 255, 0.64);
    }

    .attendance-action-card__title,
    .attendance-action-card__text {
        color: #ffffff;
        margin: 0;
    }

    .attendance-action-card__text {
        margin-top: 0.35rem;
        color: rgba(255, 255, 255, 0.74);
        font-size: 14px;
    }

    .attendance-action-card .btn {
        min-width: 210px;
        min-height: 52px;
        border-radius: 16px;
        font-weight: 700;
    }

    .face-section {
        margin-top: 1.35rem;
        border-radius: 24px;
        background:
            radial-gradient(circle at top right, rgba(13, 110, 253, 0.1), transparent 28%),
            linear-gradient(180deg, #f9fbff 0%, #f5f9ff 100%);
        border: 1px solid rgba(13, 110, 253, 0.08);
    }

    .face-photo-card {
        height: 100%;
        padding: 1rem;
    }

    .face-photo-card__label {
        display: block;
        margin-bottom: 0.75rem;
        font-size: 13px;
        font-weight: 700;
        color: #334155;
    }

    .face-preview-frame {
        position: relative;
        overflow: hidden;
        min-height: 80px;
        border-radius: 18px;
        background: linear-gradient(180deg, #eff4f9 0%, #e5edf5 100%);
        border: 1px solid rgba(148, 163, 184, 0.16);
    }

    .sr-reference-image {
        position: absolute;
        left: -9999px;
        width: 1px;
        height: 1px;
        opacity: 0;
        pointer-events: none;
    }

    .face-preview-image {
        width: 100%;
        min-height: 240px;
        object-fit: cover;
        background: transparent;
    }

    .selfie-placeholder {
        min-height: 240px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.7rem;
        padding: 1.5rem;
        text-align: center;
        color: #64748b;
    }

    .selfie-placeholder__icon {
        width: 58px;
        height: 58px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 18px;
        background: rgba(13, 110, 253, 0.12);
        color: #0d6efd;
        font-size: 22px;
    }

    .selfie-placeholder__title {
        margin: 0;
        font-size: 15px;
        font-weight: 700;
        color: #334155;
    }

    .selfie-placeholder__text {
        margin: 0;
        max-width: 280px;
        font-size: 13px;
        line-height: 1.2;
    }

    .face-upload-box {
        margin-top: 1rem;
        padding: 0.9rem;
        border-radius: 16px;
        background: #f8fafc;
        border: 1px solid rgba(148, 163, 184, 0.14);
    }

    .face-upload-box .form-control {
        min-height: 48px;
        border-radius: 14px;
    }

    .face-guidance {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.8rem;
        margin-top: 1rem;
    }

    .face-guidance__item {
        padding: 0.85rem;
        border-radius: 16px;
        background: rgba(255, 255, 255, 0.82);
        border: 1px solid rgba(148, 163, 184, 0.12);
    }

    .face-guidance__item i {
        color: #0d6efd;
        margin-bottom: 0.45rem;
        font-size: 16px;
    }

    .face-guidance__item strong {
        display: block;
        margin-bottom: 0.2rem;
        font-size: 13px;
        color: #1e293b;
    }

    .face-guidance__item span {
        display: block;
        font-size: 12px;
        line-height: 1.5;
        color: #64748b;
    }

    .history-chip {
        color: #334155;
        background: #f8fafc;
        border: 1px solid rgba(148, 163, 184, 0.14);
    }

    @media (max-width: 991.98px) {

        .attendance-steps,
        .history-header {
            justify-content: flex-start;
        }

        .face-guidance {
            grid-template-columns: 1fr;
        }

        .attendance-action-card .btn {
            width: 100%;
        }
    }

    @media (max-width: 767.98px) {

        .attendance-hero,
        .attendance-card__body,
        .face-section {
            padding: 1rem;
        }

        .map-surface {
            height: 300px;
        }

        .attendance-hero__title {
            font-size: 1.25rem;
        }
    }
</style>
@endpush

@section('content')
@php
$faceReferencePath = auth()->user()->employee->face_reference_path ?? null;
@endphp

<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1">
                    <i class="fas fa-map-pin text-primary me-2"></i>
                    Presensi
                </h3>
                <small class="text-muted">
                    Silakan presensi untuk mencatat kehadiranmu
                </small>
            </div>
        </div>

        @if (!$lokasi)
        <div class="alert alert-danger">
            Lokasi presensi untuk divisi Anda belum diatur.
        </div>
        @else
        @if (!$faceReferencePath)
        <div class="alert alert-warning">
            Foto referensi wajah belum didaftarkan oleh admin. Presensi dikunci sampai foto referensi tersedia.
        </div>
        @endif

        @php
        $nextType = null;
        $label = '';
        $btnClass = 'btn-primary';
        $btnIcon = 'fas fa-arrow-right';
        $actionTitle = 'Siap untuk langkah berikutnya';
        $actionText = 'Ambil selfie terlebih dahulu, tunggu matching berhasil, lalu lanjutkan presensi saat GPS valid.';

        if (!$absensiHariIni || !$absensiHariIni->jam_masuk) {
        $nextType = 'masuk';
        $label = 'Absen Masuk';
        $btnClass = 'btn-primary';
        $btnIcon = 'fas fa-sign-in-alt';
        $actionTitle = 'Absen masuk tersedia';
        $actionText = 'Ambil selfie dulu, pastikan matching berhasil, lalu sistem akan mengizinkan presensi masuk.';
        } elseif (!$absensiHariIni->jam_istirahat) {
        $nextType = 'istirahat';
        $label = 'Mulai Istirahat';
        $btnClass = 'btn-warning';
        $btnIcon = 'fas fa-mug-hot';
        $actionTitle = 'Mulai waktu istirahat';
        $actionText = 'Lanjutkan ke presensi istirahat setelah selfie cocok dan lokasi kamu tetap valid.';
        } elseif (!$absensiHariIni->jam_kembali_istirahat) {
        $nextType = 'kembali';
        $label = 'Kembali Istirahat';
        $btnClass = 'btn-info';
        $btnIcon = 'fas fa-undo-alt';
        $actionTitle = 'Kembali dari istirahat';
        $actionText = 'Sistem akan membuka tombol kembali setelah selfie terverifikasi dan GPS tetap sesuai.';
        } elseif (!$absensiHariIni->jam_pulang) {
        $nextType = 'pulang';
        $label = 'Absen Pulang';
        $btnClass = 'btn-danger';
        $btnIcon = 'fas fa-sign-out-alt';
        $actionTitle = 'Tutup presensi hari ini';
        $actionText = 'Ambil selfie terakhir hari ini, tunggu matching, lalu lakukan presensi pulang.';
        }
        @endphp

        <div class="attendance-shell">
            <div class="attendance-hero">
                <div>
                    <span class="attendance-kicker">
                        <i class="fas fa-shield-alt"></i>
                        Presensi Aman
                    </span>
                </div>

                <div class="attendance-steps">
                    <div class="attendance-step">
                        <span class="attendance-step__label">Langkah 1</span>
                        <span class="attendance-step__value">Ambil Selfie</span>
                    </div>
                    <div class="attendance-step">
                        <span class="attendance-step__label">Langkah 2</span>
                        <span class="attendance-step__value">Matching Wajah</span>
                    </div>
                    <div class="attendance-step">
                        <span class="attendance-step__label">Langkah 3</span>
                        <span class="attendance-step__value">Presensi</span>
                    </div>
                </div>
            </div>

            @if ($nextType)
            <div class="face-section">
                <div class="face-section__header">
                    <div>
                        <span class="section-caption">Langkah Awal</span>

                    </div>
                    <div id="faceStatusBadge" class="face-status-chip bg-light text-muted">
                        Menyiapkan model verifikasi...
                    </div>
                </div>

                @if ($faceReferencePath)
                <img
                    id="referenceFaceImage"
                    src="{{ asset($faceReferencePath) }}"
                    alt="Foto referensi wajah"
                    class="sr-reference-image">

                <div class="row g-4 align-items-stretch">
                    <div class="col-12">
                        <div class="face-photo-card">
                            <span class="face-photo-card__label">Selfie Presensi</span>
                            <div class="face-preview-frame">
                                <div id="selfiePlaceholder" class="selfie-placeholder">
                                    <div class="selfie-placeholder__icon">
                                        <i class="fas fa-camera-retro"></i>
                                    </div>
                                    <p class="selfie-placeholder__title">Ambil selfie untuk memulai matching</p>
                                </div>
                            </div>

                            <div class="face-upload-box">
                                <input
                                    type="file"
                                    id="selfie_capture"
                                    name="selfie_capture"
                                    accept="image/png,image/jpeg,image/webp"
                                    capture="user"
                                    form="formAbsen"
                                    class="form-control">

                                <small class="text-muted d-block mt-2">
                                    Ambil selfie terbaru. Tombol presensi akan tetap terkunci sampai matching wajah dinyatakan cocok.
                                </small>
                            </div>

                            <div id="faceVerificationAlert" class="alert alert-secondary mt-3 mb-0">
                                Ambil selfie untuk memulai verifikasi wajah.
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
            @endif

            <div class="attendance-card">
                <div class="attendance-card__body">
                    <div class="map-card__header">
                        <div>
                            <span class="section-caption">Area Presensi</span>
                        </div>
                        <div class="map-chip">
                            <i class="fas fa-map-marked-alt"></i>
                            Validasi GPS aktif
                        </div>
                    </div>

                    <div class="map-frame">
                        <div id="map" class="map-surface"></div>
                    </div>

                    <div class="location-status-card">
                        <div class="location-status-card__icon">
                            <i class="fas fa-crosshairs"></i>
                        </div>
                        <div>
                            <span class="location-status-card__label">Status Lokasi</span>
                            <div id="distanceInfo" class="location-status-card__value">
                                Mendeteksi lokasi...
                            </div>
                        </div>
                    </div>

                    <div class="attendance-summary row g-3">
                        <div class="col-6 col-lg-3">
                            <div class="attendance-metric">
                                <span class="attendance-metric__label">Masuk</span>
                                <div class="attendance-metric__time">{{ $absensiHariIni->jam_masuk ?? '--:--' }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="attendance-metric">
                                <span class="attendance-metric__label">Istirahat</span>
                                <div class="attendance-metric__time">{{ $absensiHariIni->jam_istirahat ?? '--:--' }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="attendance-metric">
                                <span class="attendance-metric__label">Kembali</span>
                                <div class="attendance-metric__time">{{ $absensiHariIni->jam_kembali_istirahat ?? '--:--' }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="attendance-metric">
                                <span class="attendance-metric__label">Pulang</span>
                                <div class="attendance-metric__time">{{ $absensiHariIni->jam_pulang ?? '--:--' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="attendance-action-card">
                        <div>
                            <span class="attendance-action-card__caption">Langkah Berikutnya</span>
                            <h6 class="attendance-action-card__title">{{ $actionTitle }}</h6>
                            <p class="attendance-action-card__text">{{ $actionText }}</p>
                        </div>

                        <div class="d-grid">
                            @if ($nextType)
                            <button class="btn {{ $btnClass }} btn-absen shadow" data-type="{{ $nextType }}" disabled>
                                <i class="{{ $btnIcon }} me-2"></i>
                                {{ $label }}
                            </button>
                            @else
                            <button class="btn btn-success shadow" disabled>
                                <i class="fas fa-check-circle me-2"></i>
                                Presensi Hari Ini Selesai
                            </button>
                            @endif
                        </div>
                    </div>

                    <form id="formAbsen" method="POST" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="lat_user" id="lat_user">
                        <input type="hidden" name="long_user" id="long_user">
                        <input type="hidden" name="accuracy" id="accuracy_user">
                        <input type="hidden" name="speed" id="speed_user">
                        <input type="hidden" name="device_info" id="device_info">
                        <input type="hidden" name="face_verified" id="face_verified" value="0">
                        <input type="hidden" name="face_distance" id="face_distance" value="">
                        <input type="hidden" name="face_detection_count" id="face_detection_count" value="0">
                        <input type="hidden" name="face_verification_meta" id="face_verification_meta" value="">
                    </form>
                </div>
            </div>

            <hr class="my-5">

        </div>

        <div class="history-header">
            <div>
                <h5 class="history-header__title">Riwayat Presensi</h5>
                <p class="history-header__text">
                    Periode {{ formatDateIndonesia($cutoffStart) }} - {{ formatDateIndonesia($cutoffEnd) }}
                </p>
            </div>
            <div class="history-chip">
                <i class="fas fa-history"></i>
                Data presensi periode aktif
            </div>
        </div>

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
<script src="{{ asset('vendor/face-api/face-api.min.js') }}"></script>

@if ($lokasi)
<script>
    let map;
    let markerUser;
    let markerOffice;
    let circleOffice;
    let currentDistance = 0;
    let stableStartTime = null;
    let validLogCount = 0;
    let gpsReady = false;
    let faceApiReady = false;
    let faceVerificationPassed = false;
    let referenceFaceDescriptor = null;
    let positionHistory = [];
    const faceDistanceThreshold = 0.5;
    const faceModelPath = @json(asset('vendor/face-api/weights'));
    const faceReferencePath = @json($faceReferencePath ? asset($faceReferencePath) : null);

    function initMap(latOffice, longOffice, radius) {
        map = new google.maps.Map(document.getElementById("map"), {
            zoom: 17,
            center: {
                lat: latOffice,
                lng: longOffice
            },
            mapTypeId: "hybrid",
        });

        markerOffice = new google.maps.Marker({
            position: {
                lat: latOffice,
                lng: longOffice
            },
            map: map,
            title: "Lokasi presensi",
            icon: "https://maps.google.com/mapfiles/ms/icons/red-dot.png"
        });

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

    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371000;
        const toRad = (value) => value * Math.PI / 180;

        const dLat = toRad(lat2 - lat1);
        const dLon = toRad(lon2 - lon1);

        const a =
            Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(toRad(lat1)) *
            Math.cos(toRad(lat2)) *
            Math.sin(dLon / 2) *
            Math.sin(dLon / 2);

        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

        return R * c;
    }

    function updateFaceStatus(message, type = 'secondary') {
        const badge = document.getElementById('faceStatusBadge');
        const alert = document.getElementById('faceVerificationAlert');

        if (badge) {
            badge.className = 'face-status-chip bg-' + type + (type === 'warning' ? ' text-dark' : '') + (type === 'light' ? ' text-muted' : ' text-white');
            badge.textContent = message;
        }

        if (alert) {
            alert.className = 'alert alert-' + type + ' mt-3 mb-3';
            alert.textContent = message;
        }
    }

    function resetFaceVerificationState() {
        faceVerificationPassed = false;
        document.getElementById('face_verified').value = '0';
        document.getElementById('face_distance').value = '';
        document.getElementById('face_detection_count').value = '0';
        document.getElementById('face_verification_meta').value = '';
        updateAttendanceButtonState();
    }

    function updateAttendanceButtonState() {
        document.querySelectorAll(".btn-absen").forEach(button => {
            button.disabled = !(gpsReady && faceVerificationPassed);
        });
    }

    async function loadFaceModels() {
        if (!faceReferencePath) {
            updateFaceStatus('Foto referensi wajah belum tersedia.', 'warning');
            resetFaceVerificationState();
            return;
        }

        async function loadFaceModels() {
            if (!faceReferencePath) {
                updateFaceStatus('Foto referensi wajah belum tersedia.', 'warning');
                resetFaceVerificationState();
                return;
            }

            try {
                updateFaceStatus('Memuat model verifikasi wajah...', 'secondary');

                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(faceModelPath),
                    faceapi.nets.faceLandmark68TinyNet.loadFromUri(faceModelPath),
                    faceapi.nets.faceRecognitionNet.loadFromUri(faceModelPath)
                ]);

                faceApiReady = true;
                updateFaceStatus('Model siap. Ambil selfie untuk memulai matching.', 'info');
            } catch (error) {
                console.error('Face model load error:', error);
                faceApiReady = false;
                updateFaceStatus(`Gagal memuat model verifikasi wajah: ${error.message || error}`, 'danger');
            }
        }

        updateAttendanceButtonState();
    }

    function createImageFromFile(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();

            reader.onload = function(event) {
                const image = new Image();
                image.onload = function() {
                    resolve(image);
                };
                image.onerror = reject;
                image.src = event.target.result;
            };

            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    }

    async function detectFaceDescriptors(image) {
        const options = new faceapi.TinyFaceDetectorOptions({
            inputSize: 320,
            scoreThreshold: 0.5
        });

        return faceapi
            .detectAllFaces(image, options)
            .withFaceLandmarks(true)
            .withFaceDescriptors();
    }

    async function ensureReferenceFaceDescriptor() {
        if (referenceFaceDescriptor) {
            return referenceFaceDescriptor;
        }

        const referenceImage = document.getElementById('referenceFaceImage');

        if (!referenceImage) {
            throw new Error('Foto referensi tidak ditemukan.');
        }

        if (!referenceImage.complete) {
            await new Promise((resolve, reject) => {
                referenceImage.onload = resolve;
                referenceImage.onerror = reject;
            });
        }

        const detections = await detectFaceDescriptors(referenceImage);

        if (detections.length !== 1) {
            throw new Error('Foto referensi harus memuat tepat satu wajah.');
        }

        referenceFaceDescriptor = detections[0].descriptor;
        return referenceFaceDescriptor;
    }

    function validateNaturalMovement() {
        if (positionHistory.length < 2) {
            return {
                status: false,
                reason: "Mengumpulkan data GPS..."
            };
        }

        let totalVariation = 0;
        let zeroMoveCount = 0;

        for (let i = 1; i < positionHistory.length; i++) {
            let distance = getDistance(
                positionHistory[i - 1].lat,
                positionHistory[i - 1].long,
                positionHistory[i].lat,
                positionHistory[i].long
            );

            totalVariation += distance;

            if (distance < 0.1) {
                zeroMoveCount++;
            }
        }

        if (zeroMoveCount >= positionHistory.length - 1) {
            return {
                status: true
            };
        }

        if (totalVariation < 0.5) {
            return {
                status: true
            };
        }

        if (totalVariation <= 25) {
            return {
                status: true
            };
        }

        return {
            status: false,
            reason: "Pergerakan tidak wajar"
        };
    }

    document.addEventListener("DOMContentLoaded", function() {
        let latOffice = {
            {
                $lokasi - > lat
            }
        };
        let longOffice = {
            {
                $lokasi - > long
            }
        };
        let radius = {
            {
                $lokasi - > radius
            }
        };
        let lastLat = null;
        let lastLong = null;
        let lastTime = null;
        let speed = null;
        let stableCounter = 0;
        const selfieInput = document.getElementById('selfie_capture');
        const selfiePreview = document.getElementById('selfiePreview');
        const selfiePlaceholder = document.getElementById('selfiePlaceholder');

        const syncSelfiePreviewState = function(hasImage) {
            if (selfiePlaceholder) {
                selfiePlaceholder.classList.toggle('d-none', hasImage);
            }

            if (selfiePreview) {
                selfiePreview.classList.toggle('d-none', !hasImage);
            }
        };

        initMap(latOffice, longOffice, radius);
        updateAttendanceButtonState();
        loadFaceModels();
        syncSelfiePreviewState(false);

        if (!navigator.geolocation) {
            document.getElementById("distanceInfo").innerHTML =
                "<span class='text-danger'>Browser tidak mendukung GPS</span>";
            return;
        }

        navigator.geolocation.watchPosition(function(position) {
            let latUser = position.coords.latitude;
            let longUser = position.coords.longitude;
            let accuracy = position.coords.accuracy;
            let now = Date.now();

            if (accuracy > 75) {
                gpsReady = false;
                updateAttendanceButtonState();

                document.getElementById("distanceInfo").innerHTML =
                    "<span class='text-danger'>GPS tidak valid (" + Math.round(accuracy) + "m)</span>";
                return;
            }

            if (lastLat !== null && lastTime !== null) {
                let distanceMove = getDistance(lastLat, lastLong, latUser, longUser);
                let timeDiff = (now - lastTime) / 1000;

                if (timeDiff > 0) {
                    speed = distanceMove / timeDiff;
                }

                if (speed > 50) {
                    gpsReady = false;
                    updateAttendanceButtonState();

                    document.getElementById("distanceInfo").innerHTML =
                        "<span class='text-danger'>Pergerakan tidak wajar terdeteksi</span>";
                    return;
                }
            }

            if (!stableStartTime) {
                stableStartTime = now;
            }

            if (now - stableStartTime < 5000) {
                gpsReady = false;
                updateAttendanceButtonState();

                document.getElementById("distanceInfo").innerHTML =
                    "<span class='text-warning'>Validasi lokasi... (" +
                    Math.floor((5000 - (now - stableStartTime)) / 1000) +
                    " detik)</span>";
                return;
            }

            positionHistory.push({
                lat: latUser,
                long: longUser
            });

            if (positionHistory.length > 3) {
                positionHistory.shift();
            }

            lastLat = latUser;
            lastLong = longUser;
            lastTime = now;

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

            if (currentDistance <= radius) {
                document.getElementById("distanceInfo").innerHTML =
                    "<span class='text-success'>" +
                    currentDistance.toFixed(1) +
                    " meter (Dalam Radius)</span>";

                if (accuracy < 75) {
                    stableCounter++;
                    validLogCount++;
                } else {
                    stableCounter = 0;
                }

                if (stableCounter >= 1) {
                    gpsReady = true;
                    updateAttendanceButtonState();
                }
            } else {
                document.getElementById("distanceInfo").innerHTML =
                    "<span class='text-danger'>" +
                    currentDistance.toFixed(1) +
                    " meter (Di luar radius)</span>";

                gpsReady = false;
                updateAttendanceButtonState();
            }

            let naturalCheck = validateNaturalMovement();

            if (!naturalCheck.status) {
                gpsReady = false;
                updateAttendanceButtonState();

                document.getElementById("distanceInfo").innerHTML =
                    "<span class='text-danger'>" + naturalCheck.reason + "</span>";

                return;
            }

            if (position.coords.mocked === true) {
                gpsReady = false;
                updateAttendanceButtonState();

                document.getElementById("distanceInfo").innerHTML =
                    "<span class='text-danger'>Mock location terdeteksi</span>";

                return;
            }

            document.getElementById("lat_user").value = latUser;
            document.getElementById("long_user").value = longUser;
            document.getElementById("accuracy_user").value = accuracy;
            document.getElementById("speed_user").value = speed ?? 0;

            let deviceInfo = {
                platform: navigator.platform,
                language: navigator.language,
                userAgent: navigator.userAgent,
                screen: window.screen.width + "x" + window.screen.height,
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                memory: navigator.deviceMemory || "unknown",
                cores: navigator.hardwareConcurrency || "unknown"
            };

            document.getElementById("device_info").value = JSON.stringify(deviceInfo);

            if (gpsReady) {
                if (!window.lastLat) {
                    window.lastLat = latUser;
                    window.lastLong = longUser;
                    window.lastLogTime = Date.now();
                    return;
                }

                if (Date.now() - window.lastLogTime >= 5000) {
                    const distance = calculateDistance(window.lastLat, window.lastLong, latUser, longUser);

                    if (distance < 3 && Date.now() - window.lastLogTime < 60000) {
                        return;
                    }

                    if (accuracy > 75) {
                        return;
                    }

                    fetch("/api/gps-log", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": document.querySelector('meta[name=\"csrf-token\"]').content
                        },
                        body: JSON.stringify({
                            lat: latUser,
                            long: longUser,
                            accuracy: accuracy,
                            speed: speed ?? 0,
                        })
                    }).catch(err => console.log("GPS Log Error:", err));

                    window.lastLat = latUser;
                    window.lastLong = longUser;
                    window.lastLogTime = Date.now();
                }
            }
        }, function() {
            document.getElementById("distanceInfo").innerHTML =
                "<span class='text-danger'>Gagal mengambil lokasi</span>";
        }, {
            enableHighAccuracy: true,
            timeout: 20000,
            maximumAge: 0
        });

        document.querySelectorAll(".btn-absen").forEach(button => {
            button.addEventListener("click", function() {
                if (!gpsReady) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'GPS belum tervalidasi',
                        text: 'Pastikan lokasi stabil sebelum absen.'
                    });
                    return;
                }

                if (validLogCount < 2) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Validasi belum cukup',
                        text: 'Tunggu beberapa detik lagi.'
                    });
                    return;
                }

                if (!faceReferencePath) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Foto referensi belum tersedia',
                        text: 'Minta admin mengunggah foto referensi wajah terlebih dahulu.'
                    });
                    return;
                }

                if (!faceVerificationPassed) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Matching wajah belum selesai',
                        text: 'Ambil selfie dulu dan tunggu sampai hasil matching dinyatakan cocok.'
                    });
                    return;
                }

                let type = this.dataset.type;
                let form = document.getElementById("formAbsen");
                form.action = `/absen/${type}`;
                form.submit();
            });
        });

        if (selfieInput) {
            selfieInput.addEventListener('change', async function(event) {
                const file = event.target.files && event.target.files[0];

                resetFaceVerificationState();

                if (selfiePreview) {
                    selfiePreview.removeAttribute('src');
                }
                syncSelfiePreviewState(false);

                if (!file) {
                    updateFaceStatus('Ambil selfie untuk memulai verifikasi wajah.', 'secondary');
                    return;
                }

                if (!faceApiReady) {
                    updateFaceStatus('Model verifikasi belum siap. Coba lagi beberapa detik.', 'warning');
                    return;
                }

                try {
                    updateFaceStatus('Menganalisis selfie...', 'info');

                    const previewUrl = URL.createObjectURL(file);
                    if (selfiePreview) {
                        selfiePreview.src = previewUrl;
                    }
                    syncSelfiePreviewState(true);

                    const selfieImage = await createImageFromFile(file);
                    const referenceDescriptor = await ensureReferenceFaceDescriptor();
                    const selfieDetections = await detectFaceDescriptors(selfieImage);

                    document.getElementById('face_detection_count').value = selfieDetections.length;

                    if (selfieDetections.length !== 1) {
                        updateFaceStatus('Selfie harus memuat tepat satu wajah.', 'warning');
                        return;
                    }

                    const distance = faceapi.euclideanDistance(referenceDescriptor, selfieDetections[0].descriptor);
                    const matched = distance <= faceDistanceThreshold;

                    document.getElementById('face_verified').value = matched ? '1' : '0';
                    document.getElementById('face_distance').value = distance.toFixed(6);
                    document.getElementById('face_verification_meta').value = JSON.stringify({
                        threshold: faceDistanceThreshold,
                        distance: Number(distance.toFixed(6)),
                        detection_count: selfieDetections.length,
                        reference: 'employee_face_reference',
                        verified_at_client: new Date().toISOString()
                    });

                    faceVerificationPassed = matched;
                    updateAttendanceButtonState();

                    if (matched) {
                        updateFaceStatus('Verifikasi wajah berhasil. Selfie valid untuk presensi.', 'success');
                    } else {
                        updateFaceStatus('Wajah tidak cocok dengan foto referensi. Ambil selfie lagi.', 'danger');
                    }
                } catch (error) {
                    resetFaceVerificationState();
                    updateFaceStatus(error.message || 'Verifikasi wajah gagal dijalankan.', 'danger');
                }
            });
        }
    });
</script>
@endif

<script>
    $(document).ready(function() {
        $("#table-presensi").DataTable({
            responsive: true,
            order: [
                [0, 'desc']
            ]
        });
    });
</script>
@endpush