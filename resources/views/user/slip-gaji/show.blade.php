@extends('layouts.app')

@push('styles')
<style>
    :root {
        --vp-primary: #0d6efd;
        --vp-success: #198754;
        --vp-danger: #dc3545;
        --vp-warning: #f59f00;
        --vp-dark: #102033;
        --vp-muted: #6c757d;
        --vp-border: #e3eaf3;
        --vp-card: rgba(255, 255, 255, .92);
    }

    .salary-slip-page {
        min-height: 100vh;
        background:
            radial-gradient(circle at top left, rgba(13, 110, 253, .14), transparent 34%),
            radial-gradient(circle at bottom right, rgba(25, 135, 84, .12), transparent 32%),
            linear-gradient(135deg, #f7fbff 0%, #eef5f3 100%);
        padding-bottom: 34px;
    }

    .salary-slip-inner {
        padding-top: 24px;
    }

    .salary-hero {
        position: relative;
        overflow: hidden;
        border-radius: 30px;
        padding: 32px;
        background: linear-gradient(135deg, rgba(13, 110, 253, .96), rgba(25, 135, 84, .92));
        color: #fff;
        box-shadow: 0 24px 70px rgba(16, 32, 51, .14);
    }

    .salary-hero::before,
    .salary-hero::after {
        content: "";
        position: absolute;
        border-radius: 999px;
        background: rgba(255, 255, 255, .16);
        pointer-events: none;
    }

    .salary-hero::before {
        width: 240px;
        height: 240px;
        top: -100px;
        right: -60px;
    }

    .salary-hero::after {
        width: 160px;
        height: 160px;
        bottom: -70px;
        left: 22%;
    }

    .salary-hero-content {
        position: relative;
        z-index: 2;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 24px;
    }

    .salary-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 13px;
        border-radius: 999px;
        background: rgba(255, 255, 255, .15);
        border: 1px solid rgba(255, 255, 255, .22);
        color: rgba(255, 255, 255, .92);
        font-size: .8rem;
        font-weight: 800;
        margin-bottom: 16px;
    }

    .salary-title {
        font-size: clamp(1.8rem, 3vw, 2.8rem);
        font-weight: 850;
        letter-spacing: -.04em;
        margin-bottom: 10px;
    }

    .salary-subtitle {
        color: rgba(255, 255, 255, .84);
        line-height: 1.7;
        margin-bottom: 0;
    }

    .salary-status {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border-radius: 999px;
        padding: 10px 15px;
        font-size: .82rem;
        font-weight: 850;
        white-space: nowrap;
    }

    .salary-status--paid {
        color: #13764b;
        background: #fff;
    }

    .salary-status--unpaid {
        color: #7a5200;
        background: rgba(255, 255, 255, .9);
    }

    .salary-total-card {
        border: 0;
        border-radius: 28px;
        background: var(--vp-card);
        box-shadow: 0 18px 50px rgba(16, 32, 51, .09);
        backdrop-filter: blur(16px);
        margin-top: -34px;
        position: relative;
        z-index: 3;
    }

    .salary-total-card .card-body {
        padding: 26px;
    }

    .salary-total-label {
        color: var(--vp-muted);
        font-size: .9rem;
        font-weight: 750;
        margin-bottom: 6px;
    }

    .salary-total-amount {
        color: var(--vp-success);
        font-size: clamp(1.8rem, 4vw, 2.7rem);
        font-weight: 900;
        letter-spacing: -.04em;
        margin-bottom: 0;
    }

    .salary-total-period {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 13px;
        border-radius: 999px;
        background: rgba(13, 110, 253, .08);
        color: var(--vp-primary);
        font-size: .82rem;
        font-weight: 850;
    }

    .salary-card {
        border: 0;
        border-radius: 26px;
        background: var(--vp-card);
        box-shadow: 0 18px 50px rgba(16, 32, 51, .09);
        backdrop-filter: blur(16px);
        overflow: hidden;
    }

    .salary-card .card-body {
        padding: 24px;
    }

    .salary-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        margin-bottom: 18px;
    }

    .section-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 7px 11px;
        border-radius: 999px;
        background: rgba(13, 110, 253, .09);
        color: var(--vp-primary);
        font-size: .74rem;
        font-weight: 850;
        margin-bottom: 10px;
    }

    .section-title {
        color: var(--vp-dark);
        font-weight: 850;
        letter-spacing: -.02em;
        margin-bottom: 0;
    }

    .btn-export-pdf {
        min-height: 42px;
        border-radius: 15px;
        border: 0;
        padding: 0 15px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #fff;
        background: linear-gradient(135deg, #dc3545, #f0626f);
        font-size: .84rem;
        font-weight: 850;
        text-decoration: none;
        box-shadow: 0 12px 24px rgba(220, 53, 69, .2);
    }

    .btn-export-pdf:hover {
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 16px 30px rgba(220, 53, 69, .26);
    }

    .employee-info-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
    }

    .employee-info-item {
        padding: 16px;
        border-radius: 20px;
        background: #f8fafc;
        border: 1px solid var(--vp-border);
    }

    .employee-info-item small {
        display: block;
        color: var(--vp-muted);
        font-size: .74rem;
        font-weight: 750;
        margin-bottom: 5px;
    }

    .employee-info-item strong {
        display: block;
        color: var(--vp-dark);
        font-size: .9rem;
        font-weight: 850;
        word-break: break-word;
    }

    .salary-breakdown-card {
        height: 100%;
    }

    .breakdown-header {
        padding: 20px 22px;
        border-bottom: 1px solid var(--vp-border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
        background: #fff;
    }

    .breakdown-title {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 0;
        color: var(--vp-dark);
        font-weight: 850;
    }

    .breakdown-icon {
        width: 42px;
        height: 42px;
        border-radius: 15px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .breakdown-icon--income {
        color: var(--vp-success);
        background: rgba(25, 135, 84, .1);
    }

    .breakdown-icon--deduction {
        color: var(--vp-danger);
        background: rgba(220, 53, 69, .1);
    }

    .breakdown-total {
        text-align: right;
    }

    .breakdown-total small {
        display: block;
        color: var(--vp-muted);
        font-size: .72rem;
        font-weight: 750;
        margin-bottom: 3px;
    }

    .breakdown-total strong {
        display: block;
        color: var(--vp-dark);
        font-size: .95rem;
        font-weight: 900;
    }

    .salary-list {
        padding: 10px 18px 18px;
    }

    .salary-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        padding: 13px 0;
        border-bottom: 1px dashed #dce5ef;
    }

    .salary-row:last-child {
        border-bottom: 0;
    }

    .salary-row__label {
        color: #475569;
        font-size: .9rem;
        font-weight: 700;
    }

    .salary-row__amount {
        color: var(--vp-dark);
        font-size: .92rem;
        font-weight: 850;
        white-space: nowrap;
    }

    .salary-row__amount--income {
        color: var(--vp-success);
    }

    .salary-row__amount--deduction {
        color: var(--vp-danger);
    }

    .empty-row {
        padding: 20px;
        border-radius: 18px;
        background: #f8fafc;
        color: var(--vp-muted);
        font-size: .86rem;
        font-weight: 700;
        text-align: center;
    }

    .salary-note {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 15px 17px;
        border-radius: 20px;
        background: rgba(13, 110, 253, .08);
        border: 1px solid rgba(13, 110, 253, .12);
        color: #31506f;
        font-size: .86rem;
        line-height: 1.6;
    }

    .salary-note i {
        color: var(--vp-primary);
        margin-top: 3px;
    }

    @media (max-width: 1199.98px) {
        .employee-info-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 991.98px) {
        .salary-slip-inner {
            padding-top: 16px;
        }

        .salary-hero {
            border-radius: 26px;
            padding: 26px;
        }

        .salary-hero-content {
            flex-direction: column;
            align-items: flex-start;
        }

        .salary-status {
            align-self: flex-start;
        }

        .salary-card .card-body,
        .salary-total-card .card-body {
            padding: 20px;
        }
    }

    @media (max-width: 575.98px) {
        .salary-slip-page {
            padding-left: 0;
            padding-right: 0;
        }

        .salary-slip-inner {
            padding-top: 10px;
        }

        .salary-hero {
            border-radius: 22px;
            padding: 20px;
        }

        .salary-badge {
            font-size: .72rem;
            margin-bottom: 12px;
        }

        .salary-title {
            font-size: 1.55rem;
        }

        .salary-subtitle {
            font-size: .84rem;
            line-height: 1.55;
        }

        .salary-total-card {
            margin-top: -20px;
            border-radius: 22px;
        }

        .salary-total-card .card-body {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 14px;
        }

        .salary-total-amount {
            font-size: 1.8rem;
        }

        .salary-card {
            border-radius: 22px;
        }

        .salary-card-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .btn-export-pdf {
            width: 100%;
            justify-content: center;
        }

        .employee-info-grid {
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .employee-info-item {
            padding: 13px;
            border-radius: 16px;
        }

        .breakdown-header {
            padding: 16px;
        }

        .breakdown-total strong {
            font-size: .86rem;
        }

        .salary-list {
            padding: 8px 15px 15px;
        }

        .salary-row {
            padding: 11px 0;
        }

        .salary-row__label,
        .salary-row__amount {
            font-size: .82rem;
        }
    }
</style>
@endpush

@section('content')
@php
$isPaid = ($slip->status_gaji ?? 'UNPAID') === 'PAID';

$incomeItems = collect([
[
'label' => 'Gaji Pokok',
'value' => $slip->gaji_pokok ?? 0,
'show' => true,
],
[
'label' => 'Tunjangan UM',
'value' => $slip->tunj_um ?? 0,
'show' => filled($slip->tunj_um),
],
[
'label' => 'Tunjangan Transport',
'value' => $slip->tunj_transport ?? 0,
'show' => filled($slip->tunj_transport),
],
[
'label' => 'Tunjangan Fungsional',
'value' => $slip->tunj_fungsional ?? 0,
'show' => filled($slip->tunj_fungsional),
],
[
'label' => 'Lembur / OT',
'value' => $slip->ot ?? 0,
'show' => filled($slip->ot),
],
[
'label' => 'Insentif / Bonus',
'value' => ($slip->insentif ?? 0) + ($slip->bonus ?? 0),
'show' => filled($slip->insentif) || filled($slip->bonus),
],
[
'label' => 'THR',
'value' => $slip->thr ?? 0,
'show' => filled($slip->thr),
],
])->filter(fn ($item) => $item['show']);

$deductionItems = collect([
[
'label' => 'BPJS Kesehatan',
'value' => $slip->pot_bpjskes ?? 0,
'show' => true,
],
[
'label' => 'JHT',
'value' => $slip->jht ?? 0,
'show' => true,
],
[
'label' => 'JP',
'value' => $slip->jp ?? 0,
'show' => true,
],
[
'label' => 'PPh 21',
'value' => $slip->deduction_pph21 ?? 0,
'show' => filled($slip->deduction_pph21),
],
[
'label' => 'Alpa',
'value' => $slip->deduction_alpa ?? 0,
'show' => filled($slip->deduction_alpa),
],
[
'label' => 'Deduction Lainnya',
'value' => $slip->deduction ?? 0,
'show' => filled($slip->deduction),
],
])->filter(fn ($item) => $item['show']);

$totalIncome = $incomeItems->sum('value');
$totalDeduction = $deductionItems->sum('value');

$employeeName = $slip->karyawan->nama ?? $slip->karyawan->nama_karyawan ?? '-';
@endphp

<div class="container-fluid salary-slip-page">
    <div class="page-inner salary-slip-inner">

        {{-- HERO --}}
        <div class="salary-hero mb-4">
            <div class="salary-hero-content">
                <div>
                    <span class="salary-badge">
                        <i class="fas fa-file-invoice-dollar"></i>
                        V-Payslip
                    </span>

                    <h4 class="salary-title">
                        Slip Gaji Karyawan
                    </h4>

                    <p class="salary-subtitle">
                        Ringkasan penghasilan, potongan, dan total gaji diterima untuk periode
                        <strong>{{ $slip->periode }}</strong>.
                    </p>
                </div>

                <span class="salary-status {{ $isPaid ? 'salary-status--paid' : 'salary-status--unpaid' }}">
                    <i class="fas {{ $isPaid ? 'fa-check-circle' : 'fa-clock' }}"></i>
                    {{ $slip->status_gaji ?? 'UNPAID' }}
                </span>
            </div>
        </div>

        {{-- TOTAL --}}
        <div class="card salary-total-card mb-4 mt-2">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="salary-total-label">
                        Total Gaji Diterima
                    </div>
                    <h3 class="salary-total-amount">
                        Rp {{ number_format($slip->tot_diterima ?? 0, 0, ',', '.') }}
                    </h3>
                </div>

                <div class="salary-total-period">
                    <i class="fas fa-calendar-alt"></i>
                    Periode {{ $slip->periode }}
                </div>
            </div>
        </div>

        {{-- INFORMASI KARYAWAN --}}
        <div class="card salary-card mb-4">
            <div class="card-body">
                <div class="salary-card-header">
                    <div>
                        <span class="section-kicker">
                            <i class="fas fa-id-card"></i>
                            Informasi Karyawan
                        </span>
                        <h5 class="section-title">Detail Karyawan</h5>
                    </div>

                    <a href="{{ route('slipgaji.pdf', $slip->id) }}"
                        class="btn-export-pdf"
                        target="_blank">
                        <i class="fas fa-file-pdf"></i>
                        Export PDF
                    </a>
                </div>

                <div class="employee-info-grid">
                    <div class="employee-info-item">
                        <small>NIK</small>
                        <strong>{{ $slip->karyawan->nik ?? '-' }}</strong>
                    </div>

                    <div class="employee-info-item">
                        <small>Nama</small>
                        <strong>{{ $employeeName }}</strong>
                    </div>

                    <div class="employee-info-item">
                        <small>Departemen</small>
                        <strong>{{ $slip->departemen ?? '-' }}</strong>
                    </div>

                    <div class="employee-info-item">
                        <small>Divisi</small>
                        <strong>{{ $slip->divisi ?? '-' }}</strong>
                    </div>

                    <div class="employee-info-item">
                        <small>Posisi</small>
                        <strong>{{ $slip->posisi ?? '-' }}</strong>
                    </div>

                    <div class="employee-info-item">
                        <small>Durasi SP</small>
                        <strong>{{ empty($slip->durasi_sp) || $slip->durasi_sp == '0000-00-00' ? '-' : $slip->durasi_sp }}</strong>
                    </div>

                    <div class="employee-info-item">
                        <small>Hour Machine</small>
                        <strong>{{ $slip->jml_hour_machine ?? '-' }}</strong>
                    </div>

                    <div class="employee-info-item">
                        <small>Jumlah Hari Kerja</small>
                        <strong>{{ $slip->jml_hari_kerja ?? '-' }}</strong>
                    </div>
                </div>
            </div>
        </div>

        {{-- DETAIL GAJI --}}
        <div class="row g-4 mb-4">
            {{-- PENDAPATAN --}}
            <div class="col-lg-6">
                <div class="card salary-card salary-breakdown-card">
                    <div class="breakdown-header">
                        <div class="d-flex align-items-center gap-3">
                            <span class="breakdown-icon breakdown-icon--income">
                                <i class="fas fa-arrow-down"></i>
                            </span>
                            <h5 class="breakdown-title">A. Pendapatan</h5>
                        </div>

                        <div class="breakdown-total">
                            <small>Total</small>
                            <strong>Rp {{ number_format($totalIncome, 0, ',', '.') }}</strong>
                        </div>
                    </div>

                    <div class="salary-list">
                        @forelse($incomeItems as $item)
                        <div class="salary-row">
                            <span class="salary-row__label">{{ $item['label'] }}</span>
                            <span class="salary-row__amount salary-row__amount--income">
                                Rp {{ number_format($item['value'] ?? 0, 0, ',', '.') }}
                            </span>
                        </div>
                        @empty
                        <div class="empty-row">
                            Tidak ada komponen pendapatan.
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- POTONGAN --}}
            <div class="col-lg-6">
                <div class="card salary-card salary-breakdown-card">
                    <div class="breakdown-header">
                        <div class="d-flex align-items-center gap-3">
                            <span class="breakdown-icon breakdown-icon--deduction">
                                <i class="fas fa-arrow-up"></i>
                            </span>
                            <h5 class="breakdown-title">B. Potongan</h5>
                        </div>

                        <div class="breakdown-total">
                            <small>Total</small>
                            <strong>Rp {{ number_format($totalDeduction, 0, ',', '.') }}</strong>
                        </div>
                    </div>

                    <div class="salary-list">
                        @forelse($deductionItems as $item)
                        <div class="salary-row">
                            <span class="salary-row__label">{{ $item['label'] }}</span>
                            <span class="salary-row__amount salary-row__amount--deduction">
                                Rp {{ number_format($item['value'] ?? 0, 0, ',', '.') }}
                            </span>
                        </div>
                        @empty
                        <div class="empty-row">
                            Tidak ada komponen potongan.
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- NOTE --}}
        <div class="salary-note">
            <i class="fas fa-info-circle"></i>
            <span>
                Slip gaji ini ditampilkan berdasarkan data payroll pada sistem. Jika terdapat perbedaan data,
                silakan lakukan konfirmasi kepada tim HR atau payroll terkait.
            </span>
        </div>

    </div>
</div>
@endsection