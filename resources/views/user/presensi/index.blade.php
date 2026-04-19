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

    .attendance-card__header {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.25rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(148, 163, 184, 0.14);
    }

    .attendance-card__body,
    .face-section {
        padding: 1.25rem;
    }

    .attendance-card__title {
        margin: 0.75rem 0 0.3rem;
        font-size: 1.35rem;
        font-weight: 700;
        color: #0f172a;
    }

    .attendance-card__text {
        margin: 0;
        max-width: 620px;
        color: #64748b;
        line-height: 1.6;
    }

    .attendance-wizard {
        display: grid;
        gap: 1rem;
    }

    .attendance-stage {
        display: grid;
        grid-template-columns: 56px minmax(0, 1fr);
        gap: 1rem;
        align-items: start;
    }

    .attendance-stage__rail {
        position: relative;
        display: flex;
        justify-content: center;
        height: 100%;
    }

    .attendance-stage__rail::after {
        content: '';
        position: absolute;
        top: 52px;
        bottom: -1rem;
        width: 2px;
        border-radius: 999px;
        background: linear-gradient(180deg, rgba(148, 163, 184, 0.35) 0%, rgba(148, 163, 184, 0.08) 100%);
    }

    .attendance-stage:last-child .attendance-stage__rail::after {
        display: none;
    }

    .attendance-stage__number {
        width: 42px;
        height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 16px;
        border: 1px solid rgba(148, 163, 184, 0.18);
        background: #e2e8f0;
        color: #64748b;
        font-size: 15px;
        font-weight: 800;
        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.08);
        transition: all 0.25s ease;
    }

    .attendance-stage__number.is-active {
        background: linear-gradient(135deg, #0d6efd 0%, #3b82f6 100%);
        border-color: rgba(13, 110, 253, 0.28);
        color: #ffffff;
    }

    .attendance-stage__number.is-done {
        background: linear-gradient(135deg, #198754 0%, #22c55e 100%);
        border-color: rgba(25, 135, 84, 0.28);
        color: #ffffff;
    }

    .attendance-stage__number.is-locked {
        background: #e2e8f0;
        color: #94a3b8;
    }

    .attendance-stage__main {
        padding: 1rem;
        border-radius: 20px;
        border: 1px solid rgba(148, 163, 184, 0.14);
        background: #ffffff;
        box-shadow: 0 10px 26px rgba(15, 23, 42, 0.05);
        transition: all 0.25s ease;
    }

    .attendance-stage.is-active .attendance-stage__main {
        border-color: rgba(13, 110, 253, 0.18);
        box-shadow: 0 16px 34px rgba(13, 110, 253, 0.08);
    }

    .attendance-stage.is-done .attendance-stage__main {
        border-color: rgba(25, 135, 84, 0.16);
        box-shadow: 0 16px 34px rgba(25, 135, 84, 0.08);
    }

    .attendance-stage.is-locked .attendance-stage__main {
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    }

    .attendance-stage__hint {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        margin-bottom: 1rem;
        padding: 0.95rem 1rem;
        border-radius: 16px;
        border: 1px dashed rgba(148, 163, 184, 0.35);
        background: #f8fafc;
        color: #64748b;
        font-size: 14px;
        font-weight: 600;
    }

    .attendance-stage__hint i {
        color: #0d6efd;
        font-size: 16px;
    }

    .attendance-stage__hint.is-success {
        border-style: solid;
        border-color: rgba(25, 135, 84, 0.18);
        background: rgba(25, 135, 84, 0.08);
        color: #166534;
    }

    .attendance-stage__hint.is-success i {
        color: #198754;
    }

    .attendance-card__grid {
        display: grid;
        grid-template-columns: minmax(320px, 0.95fr) minmax(360px, 1.05fr);
        gap: 1rem;
        align-items: stretch;
    }

    .attendance-panel {
        height: 100%;
        padding: 1rem;
        border-radius: 20px;
        border: 1px solid rgba(148, 163, 184, 0.14);
        background: #ffffff;
        box-shadow: 0 10px 26px rgba(15, 23, 42, 0.05);
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
        transition: box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .face-preview-frame[data-frame-state="red"] {
        border-color: rgba(220, 53, 69, 0.42);
        box-shadow: 0 16px 34px rgba(220, 53, 69, 0.12);
    }

    .face-preview-frame[data-frame-state="yellow"] {
        border-color: rgba(255, 193, 7, 0.48);
        box-shadow: 0 16px 34px rgba(255, 193, 7, 0.12);
    }

    .face-preview-frame[data-frame-state="green"] {
        border-color: rgba(25, 135, 84, 0.42);
        box-shadow: 0 16px 34px rgba(25, 135, 84, 0.14);
    }

    .camera-stage {
        display: grid;
        gap: 0.9rem;
        padding: 0.9rem;
    }

    .camera-stage__media {
        position: relative;
        overflow: hidden;
        min-height: 320px;
        border-radius: 16px;
        background:
            radial-gradient(circle at top, rgba(59, 130, 246, 0.16), transparent 36%),
            linear-gradient(180deg, #dbe7f3 0%, #cdd9e5 100%);
    }

    .camera-stage__mirror {
        position: absolute;
        inset: 0;
        transform: scaleX(-1);
        transform-origin: center;
    }

    .sr-reference-image {
        position: absolute;
        left: -9999px;
        width: 1px;
        height: 1px;
        opacity: 0;
        pointer-events: none;
    }

    .camera-live-video,
    .camera-overlay,
    .face-preview-image {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        border-radius: 18px;
    }

    .camera-live-video,
    .face-preview-image {
        object-fit: contain;
        background: linear-gradient(180deg, rgba(15, 23, 42, 0.12), rgba(15, 23, 42, 0.22));
    }

    .camera-overlay {
        pointer-events: none;
    }

    .selfie-placeholder {
        position: absolute;
        inset: 0;
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

    .camera-guide {
        position: absolute;
        inset: 0;
        pointer-events: none;
    }

    .camera-guide__shape {
        position: absolute;
        left: 18%;
        top: 10%;
        width: 64%;
        height: 80%;
        border: 3px solid rgba(148, 163, 184, 0.7);
        border-radius: 28px;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.32);
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .face-preview-frame[data-frame-state="red"] .camera-guide__shape {
        border-color: rgba(220, 53, 69, 0.92);
        box-shadow:
            inset 0 0 0 1px rgba(255, 255, 255, 0.22),
            0 0 0 999px rgba(220, 53, 69, 0.06);
    }

    .face-preview-frame[data-frame-state="yellow"] .camera-guide__shape {
        border-color: rgba(255, 193, 7, 0.96);
        box-shadow:
            inset 0 0 0 1px rgba(255, 255, 255, 0.22),
            0 0 0 999px rgba(255, 193, 7, 0.06);
    }

    .face-preview-frame[data-frame-state="green"] .camera-guide__shape {
        border-color: rgba(25, 135, 84, 0.96);
        box-shadow:
            inset 0 0 0 1px rgba(255, 255, 255, 0.22),
            0 0 0 999px rgba(25, 135, 84, 0.07);
    }

    .camera-guide__label {
        position: absolute;
        left: 50%;
        bottom: 18px;
        transform: translateX(-50%);
        padding: 8px 14px;
        border-radius: 999px;
        background: rgba(15, 23, 42, 0.7);
        color: #fff;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.01em;
        text-align: center;
        backdrop-filter: blur(8px);
    }

    .camera-stage__footer {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.9rem;
    }

    .camera-stage__status {
        flex: 1 1 280px;
    }

    .camera-frame-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        color: #64748b;
        background: rgba(148, 163, 184, 0.18);
    }

    .camera-frame-chip.is-red {
        color: #b42318;
        background: rgba(220, 53, 69, 0.12);
    }

    .camera-frame-chip.is-yellow {
        color: #9a6700;
        background: rgba(255, 193, 7, 0.16);
    }

    .camera-frame-chip.is-green {
        color: #087443;
        background: rgba(25, 135, 84, 0.14);
    }

    .camera-frame-chip.is-neutral {
        color: #475569;
        background: rgba(148, 163, 184, 0.16);
    }

    .camera-stage__message {
        display: block;
        margin-top: 0.55rem;
        color: #334155;
        font-size: 14px;
        font-weight: 600;
    }

    .camera-hold-meter {
        height: 8px;
        margin-top: 0.7rem;
        overflow: hidden;
        border-radius: 999px;
        background: rgba(148, 163, 184, 0.18);
    }

    .camera-hold-meter__bar {
        width: 0;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #f59e0b 0%, #22c55e 100%);
        transition: width 0.18s linear;
    }

    .camera-action-group {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
    }

    .selfie-input-hidden {
        position: absolute;
        left: -9999px;
        width: 1px;
        height: 1px;
        opacity: 0;
        pointer-events: none;
    }

    .face-preview-frame__hint {
        display: block;
        margin-top: 0.75rem;
        font-size: 13px;
        color: #64748b;
    }

    .camera-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
        margin-top: 0.95rem;
    }

    .camera-legend__item {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.55rem 0.85rem;
        border-radius: 999px;
        border: 1px solid rgba(148, 163, 184, 0.14);
        background: rgba(255, 255, 255, 0.88);
        color: #475569;
        font-size: 12px;
        font-weight: 700;
    }

    .camera-legend__dot {
        width: 10px;
        height: 10px;
        border-radius: 999px;
    }

    .camera-legend__dot.is-red {
        background: #dc3545;
    }

    .camera-legend__dot.is-yellow {
        background: #ffc107;
    }

    .camera-legend__dot.is-green {
        background: #198754;
    }

    .history-chip {
        color: #334155;
        background: #f8fafc;
        border: 1px solid rgba(148, 163, 184, 0.14);
    }

    @media (max-width: 991.98px) {
        .attendance-stage {
            grid-template-columns: 48px minmax(0, 1fr);
        }

        .attendance-card__grid {
            grid-template-columns: 1fr;
        }

        .attendance-steps,
        .history-header {
            justify-content: flex-start;
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

        .attendance-stage {
            grid-template-columns: 1fr;
            gap: 0.75rem;
        }

        .attendance-stage__rail {
            justify-content: flex-start;
        }

        .attendance-stage__rail::after {
            display: none;
        }

        .attendance-card__header {
            margin-bottom: 1rem;
            padding-bottom: 0.85rem;
        }

        .map-surface {
            height: 300px;
        }

        .attendance-hero__title {
            font-size: 1.25rem;
        }

        .attendance-card__title {
            font-size: 1.15rem;
        }

        .camera-stage {
            padding: 0.8rem;
        }

        .camera-stage__media {
            min-height: 280px;
        }

        .camera-guide__shape {
            left: 14%;
            width: 72%;
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

            $requiresFaceStep = (bool) ($nextType && $faceReferencePath);
        @endphp

        <div class="attendance-card">
            <div class="attendance-card__body">
                <div class="attendance-card__header">
                    <div>
                        <span class="attendance-kicker">
                            <i class="fas fa-shield-alt"></i>
                            Presensi Aman
                        </span>
                    </div>

                    <div class="attendance-steps">
                        <div class="attendance-step">
                            <span class="attendance-step__label">Langkah 1</span>
                            <span class="attendance-step__value">Foto & Matching</span>
                        </div>
                        <div class="attendance-step">
                            <span class="attendance-step__label">Langkah 2</span>
                            <span class="attendance-step__value">Presensi</span>
                        </div>
                    </div>
                </div>

                <div class="attendance-wizard">
                    @if ($requiresFaceStep)
                    <section id="wizardStepFace" class="attendance-stage is-active">
                        <div class="attendance-stage__rail">
                            <span id="wizardIndicatorFace" class="attendance-stage__number is-active">1</span>
                        </div>

                        <div class="attendance-stage__main">
                            <div class="face-section__header">
                                <div>
                                    <span class="section-caption">Verifikasi Wajah</span>
                                </div>
                                <div id="faceStatusBadge" class="face-status-chip bg-light text-muted">
                                    Menyiapkan model verifikasi...
                                </div>
                            </div>

                            <img
                                id="referenceFaceImage"
                                src="{{ asset($faceReferencePath) }}"
                                alt="Foto referensi wajah"
                                class="sr-reference-image">

                            <div
                                id="selfieCameraStage"
                                class="face-preview-frame"
                                data-frame-state="neutral"
                                aria-live="polite">
                                <div class="camera-stage">
                                    <div class="camera-stage__media">
                                        <div class="camera-stage__mirror">
                                            <video
                                                id="selfieCamera"
                                                class="camera-live-video d-none"
                                                autoplay
                                                muted
                                                playsinline></video>
                                            <canvas
                                                id="selfieOverlay"
                                                class="camera-overlay d-none"></canvas>
                                        </div>

                                        <div class="camera-guide">
                                            <div id="cameraGuideShape" class="camera-guide__shape"></div>
                                            <div id="cameraGuideLabel" class="camera-guide__label">
                                                Posisikan wajah di dalam frame
                                            </div>
                                        </div>

                                        <div
                                            id="selfiePlaceholder"
                                            class="selfie-placeholder">
                                            <div class="selfie-placeholder__icon">
                                                <i class="fas fa-camera-retro"></i>
                                            </div>
                                            <p class="selfie-placeholder__title">Kamera depan akan aktif otomatis</p>
                                            <p class="selfie-placeholder__text">
                                                Izinkan akses kamera, arahkan wajah ke tengah frame, lalu tahan 3 detik saat indikator hijau.
                                            </p>
                                        </div>

                                        <img
                                            id="selfiePreview"
                                            alt="Preview selfie presensi"
                                            class="face-preview-image d-none">
                                    </div>

                                    <div class="camera-stage__footer">
                                        <div class="camera-stage__status">
                                            <span id="cameraFrameBadge" class="camera-frame-chip is-neutral">Menyiapkan kamera</span>
                                            <strong id="cameraFrameMessage" class="camera-stage__message">
                                                Menunggu izin kamera dan model verifikasi wajah.
                                            </strong>
                                            <div class="camera-hold-meter">
                                                <div id="cameraHoldBar" class="camera-hold-meter__bar"></div>
                                            </div>
                                        </div>

                                        <div class="camera-action-group">
                                            <button type="button" id="retryCameraButton" class="btn btn-outline-primary btn-sm">
                                                Aktifkan Kamera
                                            </button>
                                            <button type="button" id="manualSelfieButton" class="btn btn-outline-secondary btn-sm">
                                                Upload Manual
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <input
                                type="file"
                                id="selfie_capture"
                                name="selfie_capture"
                                accept="image/png,image/jpeg,image/webp"
                                capture="user"
                                form="formAbsen"
                                class="selfie-input-hidden">

                            <input
                                type="hidden"
                                id="selfie_capture_data"
                                name="selfie_capture_data"
                                form="formAbsen">

                            <small class="face-preview-frame__hint">
                                Kamera aktif otomatis. Status frame: merah belum sesuai, kuning belum jelas, hijau cocok lalu tahan 3 detik.
                            </small>

                            <div class="camera-legend">
                                <div class="camera-legend__item">
                                    <span class="camera-legend__dot is-red"></span>
                                    Merah: belum sesuai
                                </div>
                                <div class="camera-legend__item">
                                    <span class="camera-legend__dot is-yellow"></span>
                                    Kuning: belum jelas
                                </div>
                                <div class="camera-legend__item">
                                    <span class="camera-legend__dot is-green"></span>
                                    Hijau: cocok, tahan 3 detik
                                </div>
                            </div>

                            <div id="faceVerificationAlert" class="alert alert-secondary mt-3 mb-0">
                                Kamera sedang disiapkan untuk verifikasi wajah.
                            </div>
                        </div>
                    </section>
                    @endif

                    <section id="wizardStepAttendance" class="attendance-stage {{ $requiresFaceStep ? 'is-locked' : 'is-active' }}">
                        <div class="attendance-stage__rail">
                            <span
                                id="wizardIndicatorAttendance"
                                class="attendance-stage__number {{ $requiresFaceStep ? 'is-locked' : 'is-active' }}">
                                {{ $requiresFaceStep ? '2' : '1' }}
                            </span>
                        </div>

                        <div class="attendance-stage__main">
                            <div class="map-card__header">
                                <div>
                                    <span class="section-caption">Validasi Lokasi</span>
                                    <h5 class="section-title">Tahap presensi dibuka setelah selfie valid</h5>
                                </div>
                                <div class="map-chip">
                                    <i class="fas fa-map-marked-alt"></i>
                                    GPS aktif
                                </div>
                            </div>

                            @if ($requiresFaceStep)
                            <div id="attendanceStepHint" class="attendance-stage__hint">
                                <i class="fas fa-lock"></i>
                                Selesaikan selfie dan tunggu matching berhasil untuk membuka tahap presensi.
                            </div>
                            @endif

                            <div id="wizardAttendanceContent" class="{{ $requiresFaceStep ? 'd-none' : '' }}">
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
                            </div>
                        </div>
                    </section>
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
<script src="{{ asset('/vendor/face-api/face-api.min.js') }}"></script>

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
    let cameraStream = null;
    let cameraDetectionIntervalId = null;
    let cameraHoldStartedAt = null;
    let cameraPreviewUrl = null;
    let cameraIsProcessing = false;
    let cameraVerificationLocked = false;
    const liveHoldDurationMs = 3000;
    const liveDetectionIntervalMs = 320;
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
        const statusKey = type + ':' + message;

        if (badge) {
            if (badge.dataset.statusKey === statusKey) {
                return;
            }

            badge.dataset.statusKey = statusKey;
            badge.className = 'face-status-chip bg-' + type + (type === 'warning' ? ' text-dark' : '') + (type === 'light' ? ' text-muted' : ' text-white');
            badge.textContent = message;
        }

        if (alert) {
            alert.dataset.statusKey = statusKey;
            alert.className = 'alert alert-' + type + ' mt-3 mb-3';
            alert.textContent = message;
        }
    }

    function resetFaceVerificationState() {
        faceVerificationPassed = false;
        cameraHoldStartedAt = null;
        cameraVerificationLocked = false;
        document.getElementById('face_verified').value = '0';
        document.getElementById('face_distance').value = '';
        document.getElementById('face_detection_count').value = '0';
        document.getElementById('face_verification_meta').value = '';
        const captureDataInput = document.getElementById('selfie_capture_data');
        const selfieInput = document.getElementById('selfie_capture');

        if (captureDataInput) {
            captureDataInput.value = '';
        }

        if (selfieInput) {
            selfieInput.value = '';
            delete selfieInput.dataset.skipNextChange;
        }

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
            faceApiReady = false;
            updateFaceStatus('Gagal memuat model verifikasi wajah.', 'danger');
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

    function setSelfieSurfaceMode(mode) {
        const video = document.getElementById('selfieCamera');
        const overlay = document.getElementById('selfieOverlay');
        const preview = document.getElementById('selfiePreview');
        const placeholder = document.getElementById('selfiePlaceholder');

        if (video) {
            video.classList.toggle('d-none', mode !== 'live');
        }

        if (overlay) {
            overlay.classList.toggle('d-none', mode !== 'live');
        }

        if (preview) {
            preview.classList.toggle('d-none', mode !== 'preview');
        }

        if (placeholder) {
            placeholder.classList.toggle('d-none', mode !== 'placeholder');
        }
    }

    function clearSelfiePreview() {
        const selfiePreview = document.getElementById('selfiePreview');

        if (cameraPreviewUrl) {
            URL.revokeObjectURL(cameraPreviewUrl);
            cameraPreviewUrl = null;
        }

        if (selfiePreview) {
            selfiePreview.removeAttribute('src');
        }
    }

    function syncLiveCameraOverlaySize() {
        const video = document.getElementById('selfieCamera');
        const overlay = document.getElementById('selfieOverlay');

        if (!video || !overlay) {
            return null;
        }

        const width = Math.max(Math.round(video.clientWidth || 0), 1);
        const height = Math.max(Math.round(video.clientHeight || 0), 1);

        if (overlay.width !== width || overlay.height !== height) {
            overlay.width = width;
            overlay.height = height;
        }

        return {
            width,
            height
        };
    }

    function getLiveGuideRect(width, height) {
        return {
            x: width * 0.18,
            y: height * 0.10,
            width: width * 0.64,
            height: height * 0.80
        };
    }

    function getFaceRollAngle(landmarks) {
        if (!landmarks) {
            return 0;
        }

        const averagePoint = function(points) {
            const total = points.reduce(function(accumulator, point) {
                return {
                    x: accumulator.x + point.x,
                    y: accumulator.y + point.y
                };
            }, {
                x: 0,
                y: 0
            });

            return {
                x: total.x / points.length,
                y: total.y / points.length
            };
        };

        const leftEye = averagePoint(landmarks.getLeftEye());
        const rightEye = averagePoint(landmarks.getRightEye());

        return Math.abs(Math.atan2(rightEye.y - leftEye.y, rightEye.x - leftEye.x) * (180 / Math.PI));
    }

    function drawLiveCameraOverlay(options = {}) {
        const overlay = document.getElementById('selfieOverlay');

        if (!overlay) {
            return;
        }

        const context = overlay.getContext('2d');

        if (!context) {
            return;
        }

        const displaySize = syncLiveCameraOverlaySize();

        if (!displaySize) {
            return;
        }

        const palette = {
            neutral: '#94a3b8',
            red: '#dc3545',
            yellow: '#ffc107',
            green: '#198754'
        };
        const state = options.state || 'neutral';
        const guideRect = getLiveGuideRect(displaySize.width, displaySize.height);
        const boxes = Array.isArray(options.boxes) ? options.boxes : [];

        context.clearRect(0, 0, overlay.width, overlay.height);
        context.setLineDash([10, 8]);
        context.lineWidth = 3;
        context.strokeStyle = palette[state] || palette.neutral;
        context.strokeRect(guideRect.x, guideRect.y, guideRect.width, guideRect.height);
        context.setLineDash([]);

        boxes.forEach(function(box) {
            context.lineWidth = 3;
            context.strokeStyle = palette[state] || palette.neutral;
            context.strokeRect(box.x, box.y, box.width, box.height);
        });
    }

    function updateLiveFrameFeedback(state, message, progress = 0) {
        const stage = document.getElementById('selfieCameraStage');
        const badge = document.getElementById('cameraFrameBadge');
        const label = document.getElementById('cameraGuideLabel');
        const messageElement = document.getElementById('cameraFrameMessage');
        const holdBar = document.getElementById('cameraHoldBar');
        const states = {
            neutral: {
                badge: 'Menunggu',
                alert: 'secondary'
            },
            red: {
                badge: 'Merah',
                alert: 'danger'
            },
            yellow: {
                badge: 'Kuning',
                alert: 'warning'
            },
            green: {
                badge: 'Hijau',
                alert: 'success'
            }
        };
        const config = states[state] || states.neutral;

        if (stage) {
            stage.dataset.frameState = state;
        }

        if (badge) {
            badge.className = 'camera-frame-chip is-' + state;
            badge.textContent = config.badge;
        }

        if (label) {
            label.textContent = config.badge + ' - ' + message;
        }

        if (messageElement) {
            messageElement.textContent = message;
        }

        if (holdBar) {
            holdBar.style.width = (Math.max(0, Math.min(progress, 1)) * 100) + '%';
        }

        updateFaceStatus(config.badge + ': ' + message, config.alert);
    }

    function fillFaceVerificationInputs(payload) {
        const matched = Boolean(payload.matched);
        const detectionCount = Number(payload.detectionCount || 0);
        const distanceValue = typeof payload.distance === 'number' ? payload.distance.toFixed(6) : '';

        document.getElementById('face_verified').value = matched ? '1' : '0';
        document.getElementById('face_distance').value = distanceValue;
        document.getElementById('face_detection_count').value = String(detectionCount);
        document.getElementById('face_verification_meta').value = JSON.stringify({
            threshold: faceDistanceThreshold,
            distance: distanceValue ? Number(distanceValue) : null,
            detection_count: detectionCount,
            reference: 'employee_face_reference',
            source: payload.source,
            detection_score: payload.detectionScore ?? null,
            roll_angle: payload.rollAngle ?? null,
            frame_state: payload.frameState ?? null,
            verified_at_client: new Date().toISOString()
        });
    }

    function stopLiveCamera(options = {}) {
        const preservePreview = options.preservePreview === true;
        const video = document.getElementById('selfieCamera');

        if (cameraDetectionIntervalId) {
            window.clearInterval(cameraDetectionIntervalId);
            cameraDetectionIntervalId = null;
        }

        cameraHoldStartedAt = null;
        cameraIsProcessing = false;

        if (cameraStream) {
            cameraStream.getTracks().forEach(function(track) {
                track.stop();
            });
            cameraStream = null;
        }

        if (video) {
            video.pause();
            video.srcObject = null;
        }

        if (!preservePreview) {
            setSelfieSurfaceMode('placeholder');
            drawLiveCameraOverlay();
        }
    }

    async function captureLiveSelfie(metadata) {
        const video = document.getElementById('selfieCamera');
        const selfieInput = document.getElementById('selfie_capture');
        const captureDataInput = document.getElementById('selfie_capture_data');
        const selfiePreview = document.getElementById('selfiePreview');

        if (!video || !video.videoWidth || !video.videoHeight) {
            throw new Error('Kamera belum siap untuk menyimpan selfie.');
        }

        const captureCanvas = document.createElement('canvas');
        captureCanvas.width = video.videoWidth;
        captureCanvas.height = video.videoHeight;
        captureCanvas.getContext('2d').drawImage(video, 0, 0, captureCanvas.width, captureCanvas.height);

        const dataUrl = captureCanvas.toDataURL('image/jpeg', 0.92);
        const captureBlob = await new Promise(function(resolve) {
            captureCanvas.toBlob(resolve, 'image/jpeg', 0.92);
        });

        if (!captureBlob) {
            throw new Error('Selfie tidak berhasil disimpan dari kamera.');
        }

        if (captureDataInput) {
            captureDataInput.value = dataUrl;
        }

        if (selfieInput && window.DataTransfer) {
            try {
                const file = new File([captureBlob], 'selfie-live-' + Date.now() + '.jpg', {
                    type: 'image/jpeg'
                });
                const transfer = new DataTransfer();
                transfer.items.add(file);
                selfieInput.dataset.skipNextChange = '1';
                selfieInput.files = transfer.files;
            } catch (error) {
                // Hidden input base64 tetap menjadi fallback utama bila browser menolak assignment file.
            }
        }

        clearSelfiePreview();
        cameraPreviewUrl = URL.createObjectURL(captureBlob);

        if (selfiePreview) {
            selfiePreview.src = cameraPreviewUrl;
        }

        fillFaceVerificationInputs({
            matched: true,
            distance: metadata.distance,
            detectionCount: 1,
            source: 'live-camera',
            detectionScore: metadata.detectionScore,
            rollAngle: metadata.rollAngle,
            frameState: 'green'
        });

        faceVerificationPassed = true;
        cameraVerificationLocked = true;
        setSelfieSurfaceMode('preview');
        stopLiveCamera({
            preservePreview: true
        });
        updateAttendanceButtonState();
        updateLiveFrameFeedback('green', 'Wajah cocok. Selfie tersimpan, lanjutkan ke tahap presensi.', 1);

        if (typeof window.setAttendanceWizardStep === 'function') {
            window.setAttendanceWizardStep(2, {
                scroll: true
            });
        }
    }

    async function runFaceVerificationFromFile(file) {
        const selfiePreview = document.getElementById('selfiePreview');
        const captureDataInput = document.getElementById('selfie_capture_data');

        stopLiveCamera();
        clearSelfiePreview();

        if (captureDataInput) {
            captureDataInput.value = '';
        }

        try {
            updateLiveFrameFeedback('neutral', 'Menganalisis selfie manual...', 0);

            cameraPreviewUrl = URL.createObjectURL(file);

            if (selfiePreview) {
                selfiePreview.src = cameraPreviewUrl;
            }

            setSelfieSurfaceMode('preview');

            const selfieImage = await createImageFromFile(file);
            const referenceDescriptor = await ensureReferenceFaceDescriptor();
            const selfieDetections = await detectFaceDescriptors(selfieImage);

            document.getElementById('face_detection_count').value = String(selfieDetections.length);

            if (selfieDetections.length !== 1) {
                faceVerificationPassed = false;
                updateAttendanceButtonState();
                updateLiveFrameFeedback('red', 'Selfie manual harus memuat tepat satu wajah.', 0);
                return;
            }

            const rollAngle = getFaceRollAngle(selfieDetections[0].landmarks);
            const distance = faceapi.euclideanDistance(referenceDescriptor, selfieDetections[0].descriptor);
            const matched = distance <= faceDistanceThreshold;

            fillFaceVerificationInputs({
                matched,
                distance,
                detectionCount: selfieDetections.length,
                source: 'manual-upload',
                detectionScore: Number(selfieDetections[0].detection.score.toFixed(4)),
                rollAngle: Number(rollAngle.toFixed(2)),
                frameState: matched ? 'green' : 'red'
            });

            faceVerificationPassed = matched;
            updateAttendanceButtonState();

            if (matched) {
                updateLiveFrameFeedback('green', 'Verifikasi wajah berhasil dari upload manual.', 1);

                if (typeof window.setAttendanceWizardStep === 'function') {
                    window.setAttendanceWizardStep(2, {
                        scroll: true
                    });
                }
            } else {
                updateLiveFrameFeedback('red', 'Wajah pada upload manual belum cocok dengan referensi.', 0);
            }
        } catch (error) {
            faceVerificationPassed = false;
            updateAttendanceButtonState();
            updateLiveFrameFeedback('red', error.message || 'Verifikasi selfie manual gagal dijalankan.', 0);
        }
    }

    async function evaluateLiveCameraFrame() {
        const video = document.getElementById('selfieCamera');

        if (!video || video.readyState < 2 || cameraVerificationLocked) {
            return;
        }

        const displaySize = syncLiveCameraOverlaySize();

        if (!displaySize) {
            return;
        }

        if (!faceApiReady) {
            drawLiveCameraOverlay({
                state: 'neutral'
            });
            updateLiveFrameFeedback('neutral', 'Kamera aktif. Model verifikasi sedang disiapkan...', 0);
            return;
        }

        const referenceDescriptor = await ensureReferenceFaceDescriptor();
        const detections = await detectFaceDescriptors(video);
        const resizedDetections = faceapi.resizeResults(detections, displaySize);

        document.getElementById('face_detection_count').value = String(detections.length);
        document.getElementById('face_verified').value = '0';
        faceVerificationPassed = false;
        updateAttendanceButtonState();

        if (detections.length === 0) {
            cameraHoldStartedAt = null;
            document.getElementById('face_distance').value = '';
            drawLiveCameraOverlay({
                state: 'red'
            });
            updateLiveFrameFeedback('red', 'Wajah belum terdeteksi. Dekatkan wajah ke tengah frame.', 0);
            return;
        }

        if (detections.length > 1) {
            cameraHoldStartedAt = null;
            document.getElementById('face_distance').value = '';
            drawLiveCameraOverlay({
                state: 'red',
                boxes: resizedDetections.map(function(detection) {
                    return detection.detection.box;
                })
            });
            updateLiveFrameFeedback('red', 'Terdeteksi lebih dari satu wajah. Pastikan hanya satu orang di kamera.', 0);
            return;
        }

        const detection = detections[0];
        const resizedDetection = resizedDetections[0];
        const faceBox = resizedDetection.detection.box;
        const guideRect = getLiveGuideRect(displaySize.width, displaySize.height);
        const centerX = faceBox.x + (faceBox.width / 2);
        const centerY = faceBox.y + (faceBox.height / 2);
        const insideGuide =
            centerX >= guideRect.x + (guideRect.width * 0.16) &&
            centerX <= guideRect.x + (guideRect.width * 0.84) &&
            centerY >= guideRect.y + (guideRect.height * 0.18) &&
            centerY <= guideRect.y + (guideRect.height * 0.82);
        const faceHeightRatio = faceBox.height / displaySize.height;
        const detectionScore = Number(detection.detection.score.toFixed(4));
        const rollAngle = Number(getFaceRollAngle(detection.landmarks).toFixed(2));
        const distance = faceapi.euclideanDistance(referenceDescriptor, detection.descriptor);

        fillFaceVerificationInputs({
            matched: false,
            distance,
            detectionCount: 1,
            source: 'live-camera',
            detectionScore,
            rollAngle,
            frameState: 'neutral'
        });

        if (!insideGuide) {
            cameraHoldStartedAt = null;
            drawLiveCameraOverlay({
                state: 'red',
                boxes: [faceBox]
            });
            updateLiveFrameFeedback('red', 'Posisikan wajah tepat di tengah frame panduan.', 0);
            return;
        }

        if (detectionScore < 0.78 || faceHeightRatio < 0.32 || faceHeightRatio > 0.76 || rollAngle > 12) {
            cameraHoldStartedAt = null;
            drawLiveCameraOverlay({
                state: 'yellow',
                boxes: [faceBox]
            });
            updateLiveFrameFeedback('yellow', 'Wajah belum jelas. Hadap lurus, dekatkan secukupnya, dan jaga tetap stabil.', 0);
            return;
        }

        if (distance > faceDistanceThreshold) {
            cameraHoldStartedAt = null;
            drawLiveCameraOverlay({
                state: 'red',
                boxes: [faceBox]
            });
            updateLiveFrameFeedback('red', 'Wajah belum sesuai dengan foto referensi.', 0);
            return;
        }

        if (!cameraHoldStartedAt) {
            cameraHoldStartedAt = Date.now();
        }

        const progress = Math.min((Date.now() - cameraHoldStartedAt) / liveHoldDurationMs, 1);
        const remainingSeconds = Math.max(0, Math.ceil((liveHoldDurationMs - (Date.now() - cameraHoldStartedAt)) / 1000));

        drawLiveCameraOverlay({
            state: 'green',
            boxes: [faceBox]
        });
        updateLiveFrameFeedback('green', 'Wajah cocok. Tahan posisi ' + remainingSeconds + ' detik lagi.', progress);

        if (progress >= 1) {
            await captureLiveSelfie({
                distance,
                detectionScore,
                rollAngle
            });
        }
    }

    function beginLiveCameraVerification() {
        if (cameraDetectionIntervalId) {
            window.clearInterval(cameraDetectionIntervalId);
        }

        cameraDetectionIntervalId = window.setInterval(async function() {
            if (cameraIsProcessing || cameraVerificationLocked) {
                return;
            }

            cameraIsProcessing = true;

            try {
                await evaluateLiveCameraFrame();
            } catch (error) {
                cameraHoldStartedAt = null;
                updateLiveFrameFeedback('red', error.message || 'Analisis kamera gagal dijalankan.', 0);
            } finally {
                cameraIsProcessing = false;
            }
        }, liveDetectionIntervalMs);
    }

    async function startLiveCamera() {
        const video = document.getElementById('selfieCamera');

        if (!video || !faceReferencePath) {
            return;
        }

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            updateLiveFrameFeedback('red', 'Browser ini belum mendukung kamera live. Gunakan upload manual.', 0);
            return;
        }

        if (!window.isSecureContext && !['localhost', '127.0.0.1'].includes(window.location.hostname)) {
            updateLiveFrameFeedback('red', 'Kamera live membutuhkan HTTPS agar bisa aktif otomatis.', 0);
            return;
        }

        stopLiveCamera();
        cameraVerificationLocked = false;
        cameraHoldStartedAt = null;
        updateLiveFrameFeedback('neutral', 'Meminta akses kamera depan...', 0);

        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: {
                        ideal: 'user'
                    },
                    width: {
                        ideal: 720
                    },
                    height: {
                        ideal: 960
                    }
                },
                audio: false
            });

            video.srcObject = cameraStream;
            await video.play();
            setSelfieSurfaceMode('live');
            syncLiveCameraOverlaySize();
            drawLiveCameraOverlay({
                state: 'neutral'
            });
            updateLiveFrameFeedback('neutral', 'Kamera aktif. Arahkan wajah ke tengah frame.', 0);
            beginLiveCameraVerification();
        } catch (error) {
            setSelfieSurfaceMode('placeholder');
            updateLiveFrameFeedback('red', 'Akses kamera ditolak atau tidak tersedia. Gunakan upload manual bila perlu.', 0);
        }
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
        let latOffice = {{$lokasi->lat}};
        let longOffice = {{$lokasi->long}};
        let radius = {{$lokasi->radius}};
        let lastLat = null;
        let lastLong = null;
        let lastTime = null;
        let speed = null;
        let stableCounter = 0;
        const selfieInput = document.getElementById('selfie_capture');
        const retryCameraButton = document.getElementById('retryCameraButton');
        const manualSelfieButton = document.getElementById('manualSelfieButton');
        const wizardStepFace = document.getElementById('wizardStepFace');
        const wizardStepAttendance = document.getElementById('wizardStepAttendance');
        const wizardIndicatorFace = document.getElementById('wizardIndicatorFace');
        const wizardIndicatorAttendance = document.getElementById('wizardIndicatorAttendance');
        const attendanceStepHint = document.getElementById('attendanceStepHint');
        const wizardAttendanceContent = document.getElementById('wizardAttendanceContent');

        const refreshMapViewport = function() {
            if (!map || !window.google || !window.google.maps) {
                return;
            }

            google.maps.event.trigger(map, 'resize');

            if (markerUser) {
                map.panTo(markerUser.getPosition());
                return;
            }

            map.setCenter({
                lat: latOffice,
                lng: longOffice
            });
        };

        const setWizardStep = function(step, options = {}) {
            const scroll = options.scroll === true;
            const hasFaceStep = Boolean(wizardStepFace);
            const attendanceUnlocked = !hasFaceStep || step >= 2;

            if (wizardStepFace) {
                wizardStepFace.classList.toggle('is-active', step === 1);
                wizardStepFace.classList.toggle('is-done', step >= 2);
            }

            if (wizardIndicatorFace) {
                wizardIndicatorFace.classList.remove('is-active', 'is-done', 'is-locked');
                wizardIndicatorFace.classList.add(step >= 2 ? 'is-done' : 'is-active');
                wizardIndicatorFace.textContent = '1';
            }

            if (wizardStepAttendance) {
                wizardStepAttendance.classList.toggle('is-active', attendanceUnlocked);
                wizardStepAttendance.classList.toggle('is-locked', !attendanceUnlocked);
                wizardStepAttendance.classList.toggle('is-done', false);
            }

            if (wizardIndicatorAttendance) {
                wizardIndicatorAttendance.classList.remove('is-active', 'is-done', 'is-locked');
                wizardIndicatorAttendance.classList.add(attendanceUnlocked ? 'is-active' : 'is-locked');
            }

            if (attendanceStepHint) {
                attendanceStepHint.classList.toggle('is-success', attendanceUnlocked);
                attendanceStepHint.innerHTML = attendanceUnlocked
                    ? "<i class='fas fa-check-circle'></i> Tahap presensi sudah terbuka. Pastikan GPS stabil lalu lanjutkan presensi."
                    : "<i class='fas fa-lock'></i> Selesaikan selfie dan tunggu matching berhasil untuk membuka tahap presensi.";
            }

            if (wizardAttendanceContent) {
                wizardAttendanceContent.classList.toggle('d-none', hasFaceStep && !attendanceUnlocked);
            }

            if (attendanceUnlocked && scroll && wizardStepAttendance) {
                window.setTimeout(function() {
                    wizardStepAttendance.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    window.setTimeout(refreshMapViewport, 260);
                }, 120);
            }
        };

        window.setAttendanceWizardStep = setWizardStep;

        initMap(latOffice, longOffice, radius);
        updateAttendanceButtonState();
        setSelfieSurfaceMode(wizardStepFace ? 'placeholder' : 'preview');
        setWizardStep(wizardStepFace ? 1 : 2);
        loadFaceModels();
        startLiveCamera();

        if (retryCameraButton) {
            retryCameraButton.addEventListener('click', function() {
                clearSelfiePreview();
                resetFaceVerificationState();
                setWizardStep(1);
                setSelfieSurfaceMode('placeholder');
                updateLiveFrameFeedback('neutral', 'Menyiapkan ulang kamera depan...', 0);
                startLiveCamera();
            });
        }

        if (manualSelfieButton && selfieInput) {
            manualSelfieButton.addEventListener('click', function() {
                selfieInput.click();
            });
        }

        if (selfieInput) {
            selfieInput.addEventListener('change', async function(event) {
                if (selfieInput.dataset.skipNextChange === '1') {
                    delete selfieInput.dataset.skipNextChange;
                    return;
                }

                const file = event.target.files && event.target.files[0];

                clearSelfiePreview();
                resetFaceVerificationState();
                setWizardStep(1);

                if (!file) {
                    updateLiveFrameFeedback('neutral', 'Pilih selfie manual atau aktifkan kembali kamera.', 0);
                    startLiveCamera();
                    return;
                }

                await runFaceVerificationFromFile(file);
            });
        }

        window.addEventListener('resize', function() {
            syncLiveCameraOverlaySize();
        });

        window.addEventListener('beforeunload', function() {
            stopLiveCamera();
        });

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
                        text: 'Arahkan wajah ke frame hijau sampai selfie tersimpan, atau gunakan upload manual.'
                    });
                    return;
                }

                let type = this.dataset.type;
                let form = document.getElementById("formAbsen");
                form.action = `/absen/${type}`;
                form.submit();
            });
        });
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
