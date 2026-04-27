@extends('layouts.app')

@php
$periode = optional($roster->periodeKerjaRoster);
$employee = $roster->employee;
$division = optional(optional($employee)->divisi);
$department = optional($division->departemen);
$attachmentUrl = $roster->file ? route('approval.roster.hrd.attachment', $roster->id) : null;

$weeks = [
['label' => 'Minggu ke-1', 'status' => 'satu', 'date' => 'tanggal_satu'],
['label' => 'Minggu ke-2', 'status' => 'dua', 'date' => 'tanggal_dua'],
['label' => 'Minggu ke-3', 'status' => 'tiga', 'date' => 'tanggal_tiga'],
['label' => 'Minggu ke-4', 'status' => 'empat', 'date' => 'tanggal_empat'],
['label' => 'Minggu ke-5', 'status' => 'lima', 'date' => 'tanggal_lima'],
];

$dateText = fn ($date) => $date ? formatDateIndonesia($date) : '-';
$rangeText = function ($start, $end) use ($dateText) {
    if (!$start && !$end) {
        return '-';
    }

    if ($start && $end) {
        return $dateText($start) . ' s/d ' . $dateText($end);
    }

    return $dateText($start ?: $end);
};

$daysBetween = function ($start, $end) {
    if (!$start || !$end) {
        return 0;
    }

    return \Carbon\Carbon::parse($start)->diffInDays(\Carbon\Carbon::parse($end)) + 1;
};

$cutiRosterHari = $daysBetween($roster->tgl_mulai_cuti, $roster->tgl_mulai_cuti_berakhir);
$cutiTahunanHari = $daysBetween($roster->tgl_mulai_cuti_tahunan, $roster->tgl_mulai_cuti_tahunan_berakhir);
$offHari = $daysBetween($roster->tgl_mulai_off, $roster->tgl_mulai_off_berakhir);
$insentifRentang = $daysBetween($roster->tgl_awal_kerja, $roster->tgl_akhir_kerja);

$jumlahBekerja = 0;
foreach ($weeks as $week) {
    if (strtoupper((string) ($periode->{$week['status']} ?? '')) === 'BEKERJA') {
        $jumlahBekerja++;
    }
}

$insentifHari = (int) $periode->tipe_rencana === 2 ? $insentifRentang + $jumlahBekerja : 0;
$totalKeseluruhan = $cutiRosterHari + $cutiTahunanHari + $insentifHari;
$isPendingHrd = (int) $roster->status_pengajuan === 1 && (int) $roster->status_pengajuan_hrd === 0;
$hasTravel = $roster->tgl_keberangkatan || $roster->jam_keberangkatan || $roster->kota_awal_keberangkatan || $roster->kota_tujuan_keberangkatan || $roster->tgl_kepulangan || $roster->jam_kepulangan || $roster->kota_awal_kepulangan || $roster->kota_tujuan_kepulangan;
@endphp

@push('styles')
<style>
    .roster-show-wrap {
        max-width: 1280px;
        margin: 0 auto
    }

    .review-hero,
    .review-card,
    .side-card {
        border: 0;
        border-radius: 24px;
        box-shadow: 0 18px 40px rgba(15, 23, 42, .08)
    }

    .review-hero {
        background: linear-gradient(135deg, #0f172a, #1e3a8a);
        color: #fff;
        overflow: hidden
    }

    .review-hero .hero-meta {
        background: rgba(255, 255, 255, .08);
        border: 1px solid rgba(255, 255, 255, .1);
        border-radius: 18px
    }

    .mini-stat {
        border: 1px solid rgba(148, 163, 184, .14);
        border-radius: 20px;
        background: #fff;
        height: 100%
    }

    .mini-stat .icon {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #eff6ff;
        color: #2563eb
    }

    .label-soft,
    .detail-box small,
    .mini-stat small,
    .week-card small {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: #94a3b8
    }

    .detail-box,
    .week-card,
    .travel-box {
        border: 1px solid rgba(148, 163, 184, .14);
        border-radius: 18px;
        background: #f8fafc
    }

    .week-card.off {
        background: linear-gradient(180deg, rgba(37, 99, 235, .08), #fff)
    }

    .week-card.work {
        background: linear-gradient(180deg, rgba(22, 163, 74, .08), #fff)
    }

    .section-title {
        font-weight: 800;
        color: #0f172a
    }

    .side-card {
        position: sticky;
        top: 92px
    }

    .decision-box {
        border: 1px solid rgba(148, 163, 184, .14);
        border-radius: 18px;
        background: #f8fafc
    }

    .route-box {
        border-radius: 16px;
        background: #eff6ff;
        color: #1d4ed8;
        font-weight: 700
    }

    .empty-box {
        border: 1px dashed rgba(148, 163, 184, .3);
        border-radius: 18px;
        background: #f8fafc;
        color: #64748b
    }

    .badge {
        border-radius: 999px
    }

    .note-box {
        border-radius: 18px;
        background: #fff7ed;
        border: 1px solid rgba(249, 115, 22, .16);
        color: #9a3412
    }

    .action-btn {
        min-height: 48px;
        border-radius: 16px;
        font-weight: 700
    }

    @media (max-width:1199.98px) {
        .side-card {
            position: static
        }
    }

    @media (max-width:767.98px) {

        .review-hero,
        .review-card,
        .side-card {
            border-radius: 20px
        }
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="roster-show-wrap">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                <div>
                    <h3 class="fw-bold text-primary mb-1">Review Approval Roster HRD</h3>
                    <small class="text-muted">Tampilan detail approval HR dibuat konsisten dengan versi HOD agar proses review terasa lebih familiar.</small>
                </div>
                <a href="{{ route('approval.roster.hrd') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Kembali
                </a>
            </div>

            <div class="card review-hero mb-3">
                <div class="card-body p-4">
                    <div class="row g-3 align-items-start">
                        <div class="col-lg-7">
                            <span class="badge bg-light text-primary px-3 py-2 mb-3">Approval HRD</span>
                            <h2 class="fw-bold mb-2">{{ optional($employee)->nama_karyawan ?? 'Karyawan tidak ditemukan' }}</h2>
                            <p class="mb-3 text-white-50">Halaman ini membantu review akhir di level HRD setelah pengajuan roster lolos persetujuan HOD.</p>
                            <div class="d-flex flex-wrap gap-2">
                                {!! $roster->status_rencana_label !!}
                                {!! $roster->status_hod_label !!}
                                {!! $roster->status_hrd_label !!}
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <div class="hero-meta p-3 h-100">
                                        <small class="label-soft d-block mb-1 text-white-50">Nomor Surat</small>
                                        <div class="fw-semibold">{{ $roster->nomor_surat ?? '-' }}</div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="hero-meta p-3 h-100">
                                        <small class="label-soft d-block mb-1 text-white-50">Tanggal Pengajuan</small>
                                        <div class="fw-semibold">{{ $dateText($roster->tanggal_pengajuan) }}</div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="hero-meta p-3 h-100">
                                        <small class="label-soft d-block mb-1 text-white-50">Periode Kerja</small>
                                        <div class="fw-semibold">{{ $rangeText($periode->periode_awal, $periode->periode_akhir) }}</div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="hero-meta p-3 h-100">
                                        <small class="label-soft d-block mb-1 text-white-50">Total Sistem</small>
                                        <div class="fw-semibold">{{ $totalKeseluruhan }} Hari</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6 col-xl-3">
                    <div class="mini-stat p-3">
                        <span class="icon mb-3"><i class="fas fa-id-badge"></i></span>
                        <small class="d-block mb-1">NIK</small>
                        <div class="fw-bold">{{ $roster->nik_karyawan }}</div>
                        <div class="text-muted small mt-1">{{ $department->departemen ?? '-' }} / {{ $division->nama_divisi ?? '-' }}</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="mini-stat p-3">
                        <span class="icon mb-3"><i class="fas fa-user-check"></i></span>
                        <small class="d-block mb-1">Status HOD</small>
                        <div class="fw-bold">{!! $roster->status_hod_label !!}</div>
                        <div class="text-muted small mt-1">Approval HRD dilanjutkan setelah status HOD diterima.</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="mini-stat p-3">
                        <span class="icon mb-3"><i class="fas fa-phone-alt"></i></span>
                        <small class="d-block mb-1">Kontak</small>
                        <div class="fw-bold">{{ $roster->no_telp ?: '-' }}</div>
                        <div class="text-muted small mt-1">{{ $roster->email ?: 'Email tidak tersedia' }}</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="mini-stat p-3">
                        <span class="icon mb-3"><i class="fas fa-paperclip"></i></span>
                        <small class="d-block mb-1">Lampiran</small>
                        <div class="fw-bold">{{ $attachmentUrl ? 'Tersedia' : 'Tidak ada' }}</div>
                        <div class="text-muted small mt-1">{{ $attachmentUrl ? 'Dokumen bisa dibuka dari panel samping.' : 'Pengajuan ini tidak menyertakan file.' }}</div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-xl-8">
                    <div class="d-grid gap-3">
                        <div class="card review-card">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                                    <div>
                                        <h5 class="section-title mb-1">Informasi Karyawan</h5>
                                        <p class="text-muted mb-0">Identitas dasar pemohon untuk memastikan keputusan HRD diberikan pada pengajuan yang tepat.</p>
                                    </div>
                                    <span class="badge bg-primary-subtle text-primary px-3 py-2">Profil Pemohon</span>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6"><div class="detail-box p-3"><small class="d-block mb-1">Nama</small><div class="fw-semibold">{{ optional($employee)->nama_karyawan ?? '-' }}</div></div></div>
                                    <div class="col-md-6"><div class="detail-box p-3"><small class="d-block mb-1">NIK</small><div class="fw-semibold">{{ $roster->nik_karyawan }}</div></div></div>
                                    <div class="col-md-6"><div class="detail-box p-3"><small class="d-block mb-1">Departemen</small><div class="fw-semibold">{{ $department->departemen ?? '-' }}</div></div></div>
                                    <div class="col-md-6"><div class="detail-box p-3"><small class="d-block mb-1">Divisi</small><div class="fw-semibold">{{ $division->nama_divisi ?? '-' }}</div></div></div>
                                    <div class="col-md-6"><div class="detail-box p-3"><small class="d-block mb-1">Email</small><div class="fw-semibold">{{ $roster->email ?: '-' }}</div></div></div>
                                    <div class="col-md-6"><div class="detail-box p-3"><small class="d-block mb-1">No. HP</small><div class="fw-semibold">{{ $roster->no_telp ?: '-' }}</div></div></div>
                                </div>
                            </div>
                        </div>

                        <div class="card review-card">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                                    <div>
                                        <h5 class="section-title mb-1">Periode Roster dan Minggu Kerja</h5>
                                        <p class="text-muted mb-0">Susunan minggu kerja tetap ditampilkan agar HRD bisa memverifikasi pola kerja dan ketepatan pengajuan.</p>
                                    </div>
                                    <span class="badge bg-info-subtle text-info px-3 py-2">{{ $rangeText($periode->periode_awal, $periode->periode_akhir) }}</span>
                                </div>
                                <div class="row g-3">
                                    @foreach ($weeks as $week)
                                    @php
                                    $status = strtoupper((string) ($periode->{$week['status']} ?? '-'));
                                    $weekClass = 'week-card';
                                    $badgeClass = 'bg-secondary';
                                    if ($status === 'BEKERJA') {
                                        $weekClass .= ' work';
                                        $badgeClass = 'bg-success';
                                    } elseif ($status === 'OFF') {
                                        $weekClass .= ' off';
                                        $badgeClass = 'bg-primary';
                                    }
                                    @endphp
                                    <div class="col-md-6">
                                        <div class="{{ $weekClass }} p-3 h-100">
                                            <small class="d-block mb-2">{{ $week['label'] }}</small>
                                            <span class="badge {{ $badgeClass }} px-3 py-2">{{ $status !== '' && $status !== '-' ? $status : 'Belum diisi' }}</span>
                                            <div class="fw-semibold mt-3">{{ $dateText($periode->{$week['date']} ?? null) }}</div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                @if ((int) $periode->tipe_rencana === 2)
                                <div class="alert alert-success border-0 rounded-4 mt-3 mb-0">
                                    Total minggu berstatus <strong>BEKERJA</strong> yang masuk hitungan insentif: <strong>{{ $jumlahBekerja }} minggu</strong>.
                                </div>
                                @endif
                            </div>
                        </div>

                        <div class="card review-card">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                                    <div>
                                        <h5 class="section-title mb-1">Ringkasan Rencana</h5>
                                        <p class="text-muted mb-0">Total hari dan rentang tanggal ditampilkan jelas untuk membantu validasi akhir di level HRD.</p>
                                    </div>
                                    <span class="badge bg-success-subtle text-success px-3 py-2">Total {{ $totalKeseluruhan }} Hari</span>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="detail-box p-3 h-100">
                                            <small class="d-block mb-2">Cuti Roster</small>
                                            <div class="fw-semibold">{{ $rangeText($roster->tgl_mulai_cuti, $roster->tgl_mulai_cuti_berakhir) }}</div>
                                            <span class="badge bg-primary mt-3 px-3 py-2">{{ $cutiRosterHari }} Hari</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-box p-3 h-100">
                                            <small class="d-block mb-2">Cuti Tahunan</small>
                                            <div class="fw-semibold">{{ $rangeText($roster->tgl_mulai_cuti_tahunan, $roster->tgl_mulai_cuti_tahunan_berakhir) }}</div>
                                            <span class="badge bg-info mt-3 px-3 py-2">{{ $cutiTahunanHari }} Hari</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-box p-3 h-100">
                                            <small class="d-block mb-2">OFF Tambahan</small>
                                            <div class="fw-semibold">{{ $rangeText($roster->tgl_mulai_off, $roster->tgl_mulai_off_berakhir) }}</div>
                                            <span class="badge bg-secondary mt-3 px-3 py-2">{{ $offHari }} Hari</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-box p-3 h-100">
                                            <small class="d-block mb-2">Insentif</small>
                                            <div class="fw-semibold">{{ $rangeText($roster->tgl_awal_kerja, $roster->tgl_akhir_kerja) }}</div>
                                            @if ((int) $periode->tipe_rencana === 2)
                                            <span class="badge bg-success mt-3 px-3 py-2">{{ $insentifHari }} Hari</span>
                                            @else
                                            <span class="badge bg-light text-secondary border mt-3 px-3 py-2">Tidak dihitung</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @if ($periode->alasan)
                                <div class="note-box p-3 mt-3">
                                    <small class="d-block mb-2 fw-bold text-uppercase" style="letter-spacing:.06em;">Alasan Pengajuan</small>
                                    {{ $periode->alasan }}
                                </div>
                                @endif
                            </div>
                        </div>

                        <div class="card review-card">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                                    <div>
                                        <h5 class="section-title mb-1">Detail Perjalanan</h5>
                                        <p class="text-muted mb-0">Informasi perjalanan tetap ditampilkan untuk membantu review administratif dan kebutuhan mobilitas karyawan.</p>
                                    </div>
                                    <span class="badge bg-warning-subtle text-warning px-3 py-2">Perjalanan</span>
                                </div>
                                @if ($hasTravel)
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="travel-box p-3 h-100">
                                            <small class="d-block mb-2">Keberangkatan</small>
                                            <div class="fw-semibold">{{ $dateText($roster->tgl_keberangkatan) }}{{ $roster->jam_keberangkatan ? ' | ' . $roster->jam_keberangkatan : '' }}</div>
                                            <div class="route-box p-3 mt-3">{{ $roster->kota_awal_keberangkatan ?: '-' }} <i class="fas fa-arrow-right mx-2"></i> {{ $roster->kota_tujuan_keberangkatan ?: '-' }}</div>
                                            <div class="text-muted small mt-3">{{ $roster->catatan_penting_keberangkatan ?: 'Tidak ada catatan tambahan.' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="travel-box p-3 h-100">
                                            <small class="d-block mb-2">Kepulangan</small>
                                            <div class="fw-semibold">{{ $dateText($roster->tgl_kepulangan) }}{{ $roster->jam_kepulangan ? ' | ' . $roster->jam_kepulangan : '' }}</div>
                                            <div class="route-box p-3 mt-3">{{ $roster->kota_awal_kepulangan ?: '-' }} <i class="fas fa-arrow-right mx-2"></i> {{ $roster->kota_tujuan_kepulangan ?: '-' }}</div>
                                            <div class="text-muted small mt-3">{{ $roster->catatan_penting_kepulangan ?: 'Tidak ada catatan tambahan.' }}</div>
                                        </div>
                                    </div>
                                </div>
                                @else
                                <div class="empty-box p-3">Detail perjalanan belum diisi. Review tetap dapat dilanjutkan berdasarkan data roster yang tersedia.</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="side-card card">
                        <div class="card-body p-4">
                            <h5 class="section-title mb-1">Panel Keputusan HRD</h5>
                            <p class="text-muted mb-3">Gunakan panel ini untuk melakukan review akhir setelah approval HOD selesai diproses.</p>

                            <div class="decision-box p-3 mb-3">
                                <small class="d-block mb-2 label-soft">Status Saat Ini</small>
                                <div>{!! $roster->status_hrd_label !!}</div>
                            </div>

                            @if ($attachmentUrl)
                            <a href="{{ $attachmentUrl }}" target="_blank" class="btn btn-outline-primary w-100 action-btn mb-3">
                                <i class="fas fa-file-alt me-2"></i>Buka Lampiran Pendukung
                            </a>
                            @endif

                            @if ($isPendingHrd)
                            <div class="d-grid gap-2">
                                <form action="{{ route('approval.roster.hrd.process', $roster->id) }}" method="POST" onsubmit="return confirm('Setujui pengajuan roster ini di level HRD?')">
                                    @csrf
                                    <input type="hidden" name="action" value="1">
                                    <button type="submit" class="btn btn-success w-100 action-btn">
                                        <i class="fas fa-check-circle me-2"></i>Setujui Pengajuan
                                    </button>
                                </form>
                                <form action="{{ route('approval.roster.hrd.process', $roster->id) }}" method="POST" onsubmit="return confirm('Tolak pengajuan roster ini di level HRD?')">
                                    @csrf
                                    <input type="hidden" name="action" value="2">
                                    <button type="submit" class="btn btn-outline-danger w-100 action-btn">
                                        <i class="fas fa-times-circle me-2"></i>Tolak Pengajuan
                                    </button>
                                </form>
                            </div>
                            @else
                            <div class="empty-box p-3">Pengajuan ini sudah diproses di level HRD, sehingga tombol tindakan tidak ditampilkan lagi.</div>
                            @endif

                            <div class="decision-box p-3 mt-3">
                                <small class="d-block mb-2 label-soft">Catatan Review Cepat</small>
                                <div class="text-muted small">Pastikan status HOD sudah diterima, total hari sesuai, dan dokumen pendukung sudah cukup sebelum keputusan HRD disimpan.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
