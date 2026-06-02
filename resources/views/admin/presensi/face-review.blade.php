@extends('layouts.app')

@push('styles')
<style>
    .face-review-page {
        --face-primary: #2563eb;
        --face-soft: #f8fafc;
        --face-border: #e5e7eb;
        --face-text: #0f172a;
        --face-muted: #64748b;
        --face-radius: 16px;
    }

    .face-review-hero {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid var(--face-border);
        border-radius: var(--face-radius);
        padding: 22px;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
    }

    .face-review-card,
    .face-review-filter,
    .face-review-empty {
        background: #ffffff;
        border: 1px solid var(--face-border);
        border-radius: var(--face-radius);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
    }

    .face-review-stat {
        background: #ffffff;
        border: 1px solid var(--face-border);
        border-radius: 14px;
        padding: 14px 16px;
        min-height: 92px;
    }

    .face-review-stat small,
    .face-review-muted {
        color: var(--face-muted);
    }

    .face-review-selfie {
        width: 100%;
        max-width: 180px;
        aspect-ratio: 4 / 5;
        object-fit: cover;
        border-radius: 14px;
        background: var(--face-soft);
        border: 1px solid var(--face-border);
    }

    .face-review-selfie-placeholder {
        width: 100%;
        max-width: 180px;
        aspect-ratio: 4 / 5;
        border-radius: 14px;
        background: var(--face-soft);
        border: 1px dashed #cbd5e1;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--face-muted);
        text-align: center;
        padding: 16px;
    }

    .face-review-info {
        background: var(--face-soft);
        border-radius: 14px;
        padding: 14px;
        height: 100%;
    }

    .face-review-info-label {
        color: var(--face-muted);
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .face-review-info-value {
        color: var(--face-text);
        font-weight: 600;
        word-break: break-word;
    }

    .face-review-note {
        resize: vertical;
        min-height: 78px;
    }

    @media (max-width: 767.98px) {
        .face-review-hero,
        .face-review-filter,
        .face-review-card {
            border-radius: 14px;
        }

        .face-review-selfie,
        .face-review-selfie-placeholder {
            max-width: none;
        }
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-inner face-review-page">
        <div class="face-review-hero mb-4">
            <div class="d-flex flex-column flex-lg-row gap-3 align-items-lg-center">
                <div>
                    <a href="{{ route('data-presensi.index') }}" class="btn btn-light btn-sm border mb-3">
                        <i class="fas fa-arrow-left me-1"></i>
                        Data Presensi
                    </a>
                    <h4 class="fw-bold mb-1">
                        <i class="fas fa-user-check text-primary me-2"></i>
                        Review Presensi Wajah
                    </h4>
                    <p class="text-muted mb-0">
                        Tinjau presensi pending atau ditolak berdasarkan selfie, lokasi GPS, perangkat, dan catatan keamanan sebelum HR memberi keputusan.
                    </p>
                </div>

                <div class="ms-lg-auto w-100" style="max-width: 540px;">
                    <div class="row g-2">
                        <div class="col-4">
                            <div class="face-review-stat">
                                <small>Pending</small>
                                <div class="h4 fw-bold mb-0 text-warning">{{ number_format($summary['pending'] ?? 0) }}</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="face-review-stat">
                                <small>Ditolak</small>
                                <div class="h4 fw-bold mb-0 text-danger">{{ number_format($summary['rejected'] ?? 0) }}</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="face-review-stat">
                                <small>Verified</small>
                                <div class="h4 fw-bold mb-0 text-success">{{ number_format($summary['verified'] ?? 0) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <form method="GET" action="{{ route('data-presensi.face-review.index') }}" class="face-review-filter p-3 mb-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select form-control">
                        <option value="queue" {{ ($filters['status'] ?? 'queue') === 'queue' ? 'selected' : '' }}>Queue pending dan rejected</option>
                        <option value="{{ \App\Models\Presensi::STATUS_ABSEN_PENDING_REVIEW }}" {{ ($filters['status'] ?? '') === \App\Models\Presensi::STATUS_ABSEN_PENDING_REVIEW ? 'selected' : '' }}>Pending review</option>
                        <option value="{{ \App\Models\Presensi::STATUS_ABSEN_REJECTED }}" {{ ($filters['status'] ?? '') === \App\Models\Presensi::STATUS_ABSEN_REJECTED ? 'selected' : '' }}>Rejected</option>
                        <option value="{{ \App\Models\Presensi::STATUS_ABSEN_VERIFIED }}" {{ ($filters['status'] ?? '') === \App\Models\Presensi::STATUS_ABSEN_VERIFIED ? 'selected' : '' }}>Verified</option>
                        <option value="all" {{ ($filters['status'] ?? '') === 'all' ? 'selected' : '' }}>Semua status</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Dari Tanggal</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sampai Tanggal</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cari Karyawan</label>
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="NIK atau nama">
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i>
                        Terapkan
                    </button>
                </div>
            </div>
        </form>

        @forelse($verifications as $verification)
            @php
                $presensi = $verification->presensi;
                $employee = optional($presensi)->employee;
                $gps = $verification->gps_log;
                $meta = $verification->face_meta_summary ?? [];
                $statusClass = \App\Models\Presensi::statusAbsenBadgeClass($verification->status);
                $statusLabel = \App\Models\Presensi::statusAbsenLabel($verification->status);
                $selfieUrl = $verification->selfie_available
                    ? route('data-presensi.face-review.selfie', $verification)
                    : null;
            @endphp

            <div class="face-review-card p-3 p-lg-4 mb-3">
                <div class="d-flex flex-column flex-lg-row gap-3">
                    <div class="text-center">
                        @if($selfieUrl)
                            <img src="{{ $selfieUrl }}" alt="Selfie presensi {{ $verification->nik_karyawan }}" class="face-review-selfie">
                        @else
                            <div class="face-review-selfie-placeholder">
                                <span>
                                    <i class="fas fa-image d-block mb-2"></i>
                                    Selfie tidak tersedia
                                </span>
                            </div>
                        @endif
                    </div>

                    <div class="flex-grow-1">
                        <div class="d-flex flex-column flex-xl-row gap-2 align-items-xl-start mb-3">
                            <div>
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                    <h5 class="fw-bold mb-0">{{ optional($employee)->nama_karyawan ?? 'Karyawan tidak ditemukan' }}</h5>
                                    <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                    <span class="badge bg-light text-dark border">
                                        {{ \App\Models\PresensiVerification::typeLabel($verification->attendance_type) }}
                                    </span>
                                </div>
                                <div class="text-muted small">
                                    NIK {{ $verification->nik_karyawan }}
                                    @if(optional(optional($employee)->departemen)->departemen)
                                        <span class="mx-1">/</span>{{ optional(optional($employee)->departemen)->departemen }}
                                    @endif
                                    @if(optional(optional($employee)->divisi)->nama_divisi)
                                        <span class="mx-1">/</span>{{ optional(optional($employee)->divisi)->nama_divisi }}
                                    @endif
                                </div>
                            </div>

                            <div class="ms-xl-auto text-xl-end">
                                <div class="fw-semibold">{{ optional($verification->tanggal)->format('d M Y') ?? '-' }}</div>
                                <div class="text-muted small">
                                    Submit {{ optional($verification->submitted_at)->format('d M Y H:i') ?? optional($verification->created_at)->format('d M Y H:i') ?? '-' }}
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-lg-4">
                                <div class="face-review-info">
                                    <div class="face-review-info-label mb-2">Lokasi GPS</div>
                                    @if($gps && filled($gps->lat) && filled($gps->long))
                                        <div class="face-review-info-value">{{ $gps->lat }}, {{ $gps->long }}</div>
                                        <div class="small text-muted mt-1">
                                            Akurasi {{ filled($gps->accuracy) ? round((float) $gps->accuracy) . ' m' : '-' }}
                                            @if(filled($gps->speed))
                                                <span class="mx-1">/</span> Speed {{ round((float) $gps->speed, 1) }} m/s
                                            @endif
                                        </div>
                                        <div class="small text-muted mt-1">{{ optional($gps->created_at)->format('d M Y H:i:s') }}</div>
                                        <a href="https://www.google.com/maps?q={{ $gps->lat }},{{ $gps->long }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm mt-2">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            Buka Maps
                                        </a>
                                    @else
                                        <div class="face-review-info-value">Belum ada bukti GPS</div>
                                        <div class="small text-muted mt-1">Tidak ditemukan log lokasi pada waktu submit presensi ini.</div>
                                    @endif
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="face-review-info">
                                    <div class="face-review-info-label mb-2">Device dan Jaringan</div>
                                    <div class="small text-muted">Device</div>
                                    <div class="face-review-info-value">{{ optional($presensi)->device_info ?: '-' }}</div>
                                    <div class="small text-muted mt-2">IP Address</div>
                                    <div class="face-review-info-value">{{ optional($presensi)->ip_address ?: optional($gps)->ip_address ?: '-' }}</div>
                                    <div class="small text-muted mt-2">User Agent</div>
                                    <div class="face-review-info-value small">{{ \Illuminate\Support\Str::limit(optional($presensi)->user_agent ?: optional($gps)->user_agent ?: '-', 120) }}</div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="face-review-info">
                                    <div class="face-review-info-label mb-2">Verifikasi Wajah</div>
                                    <div class="small text-muted">Method</div>
                                    <div class="face-review-info-value">{{ $verification->face_verification_method ?: ($meta['method'] ?? '-') }}</div>
                                    <div class="small text-muted mt-2">Distance</div>
                                    <div class="face-review-info-value">
                                        {{ filled($verification->face_verification_distance) ? number_format((float) $verification->face_verification_distance, 4) : ($meta['client_distance'] ?? '-') }}
                                    </div>
                                    <div class="small text-muted mt-2">Catatan Sistem</div>
                                    <div class="face-review-info-value small">{{ \Illuminate\Support\Str::limit($meta['message'] ?? '-', 120) }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 align-items-start mt-1">
                            <div class="col-lg-5">
                                <div class="face-review-info">
                                    <div class="face-review-info-label mb-2">Keputusan Terakhir</div>
                                    <div class="face-review-info-value">
                                        {{ \App\Models\PresensiVerification::reviewDecisionLabel($verification->review_decision) }}
                                    </div>
                                    <div class="small text-muted mt-1">
                                        {{ optional($verification->reviewer)->name ?: 'Belum ada reviewer' }}
                                        @if($verification->reviewed_at)
                                            <span class="mx-1">/</span>{{ $verification->reviewed_at->format('d M Y H:i') }}
                                        @endif
                                    </div>
                                    @if($verification->review_note)
                                        <div class="small mt-2">{{ $verification->review_note }}</div>
                                    @endif
                                </div>
                            </div>

                            <div class="col-lg-7">
                                <form method="POST" action="{{ route('data-presensi.face-review.decide', $verification) }}" class="face-review-info"
                                      data-swal-confirm="Simpan keputusan review presensi wajah ini?"
                                      data-swal-title="Konfirmasi Review"
                                      data-swal-icon="warning"
                                      data-swal-confirm-button="Ya, simpan">
                                    @csrf
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <label class="form-label">Keputusan HR</label>
                                            <select name="decision" class="form-select form-control" required>
                                                <option value="{{ \App\Models\PresensiVerification::REVIEW_APPROVED }}">Setujui</option>
                                                <option value="{{ \App\Models\PresensiVerification::REVIEW_REJECTED }}">Tolak</option>
                                            </select>
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label">Catatan HR</label>
                                            <textarea name="review_note" class="form-control face-review-note" maxlength="2000" placeholder="Wajib diisi saat menolak presensi">{{ old('review_note') }}</textarea>
                                        </div>
                                        <div class="col-12 d-flex justify-content-end">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-1"></i>
                                                Simpan Keputusan
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="face-review-empty text-center p-5">
                <i class="fas fa-check-circle text-success fa-2x mb-3"></i>
                <h5 class="fw-bold">Queue review kosong</h5>
                <p class="text-muted mb-0">Tidak ada presensi wajah yang sesuai dengan filter saat ini.</p>
            </div>
        @endforelse

        <div class="mt-3">
            {{ $verifications->links() }}
        </div>
    </div>
</div>
@endsection
