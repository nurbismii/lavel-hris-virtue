@extends('layouts.app')

@section('content')
<div class="container">
    <div class="page-inner">

        {{-- HEADER --}}
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1 fw-bold">{{ __('Slip Gaji Karyawan') }}</h4>
                    <small class="text-muted">
                        Periode: {{ $slip->periode }}
                    </small>
                </div>

                <span class="badge 
                {{ $slip->status_gaji == 'PAID' ? 'bg-success' : 'bg-warning text-dark' }}">
                    {{ $slip->status_gaji ?? 'UNPAID' }}
                </span>
            </div>
        </div>

        {{-- INFORMASI KARYAWAN --}}
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-header bg-light fw-semibold">
                {{ __('Informasi Karyawan') }}
                <a href="{{ route('slip-gaji.pdf', $slip->id) }}"
                    class="btn btn-sm btn-danger float-end"
                    target="_blank">
                    <i class="fa fa-file-pdf"></i> {{ __('Export PDF') }}
                </a>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <small class="text-muted">NIK</small>
                        <div class="fw-semibold">{{ $slip->karyawan->nik ?? '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">{{ __('Nama') }}</small>
                        <div class="fw-semibold">{{ $slip->karyawan->nama ?? '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">{{ __('Departemen') }}</small>
                        <div class="fw-semibold">{{ $slip->departemen ?? '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">{{ __('Divisi') }}</small>
                        <div class="fw-semibold">{{ $slip->divisi ?? '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">{{ __('Posisi') }}</small>
                        <div class="fw-semibold">{{ $slip->posisi ?? '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">{{ __('Durasi SP') }}</small>
                        <div class="fw-semibold">{{ empty($slip->durasi_sp) || $slip->durasi_sp == '0000-00-00' ? '-'  : $slip->durasi_sp }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">{{ __('Hour Machine') }}</small>
                        <div class="fw-semibold">{{ $slip->jml_hour_machine ?? '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">{{ __('Jumlah Hari Kerja') }}</small>
                        <div class="fw-semibold">{{ $slip->jml_hari_kerja ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- DETAIL GAJI --}}
        <div class="row">
            {{-- PENDAPATAN --}}
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-opacity-10 fw-semibold">
                        {{ __('A. Pendapatan') }}
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-striped mb-0">
                            <tbody>
                                <tr>
                                    <td>{{ __('Gaji Pokok') }}</td>
                                    <td class="text-end">{{ number_format($slip->gaji_pokok ?? 0, 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    @if($slip->tunj_um)
                                    <td>{{ __('Tunjangan UM') }}</td>
                                    <td class="text-end">{{ number_format($slip->tunj_um ?? 0, 0, ',', '.') }}</td>
                                    @endif
                                </tr>
                                <tr>
                                    @if($slip->tunj_transport)
                                    <td>{{ __('Tunjangan Transport') }}</td>
                                    <td class="text-end">{{ number_format($slip->tunj_transport ?? 0, 0, ',', '.') }}</td>
                                    @endif
                                </tr>
                                <tr>
                                    @if($slip->tunj_fungsional)
                                    <td>{{ __('Tunjangan Fungsional') }}</td>
                                    <td class="text-end">{{ number_format($slip->tunj_fungsional ?? 0, 0, ',', '.') }}</td>
                                    @endif
                                </tr>
                                <tr>
                                    @if($slip->ot)
                                    <td>{{ __('Lembur / OT') }}</td>
                                    <td class="text-end">{{ number_format($slip->ot ?? 0, 0, ',', '.') }}</td>
                                    @endif
                                </tr>
                                <tr>
                                    @if($slip->insentif)
                                    <td>{{ __('Insentif / Bonus') }}</td>
                                    <td class="text-end">{{ number_format(($slip->insentif ?? 0) + ($slip->bonus ?? 0), 0, ',', '.') }}</td>
                                    @endif
                                </tr>
                                <tr class="fw-semibold">
                                    @if($slip->thr)
                                    <td>{{ __('THR') }}</td>
                                    <td class="text-end">{{ number_format($slip->thr ?? 0, 0, ',', '.') }}</td>
                                    @endif
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- POTONGAN --}}
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-opacity-10 fw-semibold">
                        {{ __('B. Potongan') }}
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-striped mb-0">
                            <tbody>
                                <tr>
                                    <td>{{ __('BPJS Kesehatan') }}</td>
                                    <td class="text-end">{{ number_format($slip->pot_bpjskes ?? 0, 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td>{{ __('JHT') }}</td>
                                    <td class="text-end">{{ number_format($slip->jht ?? 0, 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td>{{ __('JP') }}</td>
                                    <td class="text-end">{{ number_format($slip->jp ?? 0, 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    @if($slip->deduction_pph21)
                                    <td>{{ __('PPh 21') }}</td>
                                    <td class="text-end">{{ number_format($slip->deduction_pph21 ?? 0, 0, ',', '.') }}</td>
                                    @endif
                                </tr>
                                <tr>
                                    @if($slip->deduction_pph21)
                                    <td>{{ __('Alpa') }}</td>
                                    <td class="text-end">{{ number_format($slip->deduction_alpa ?? 0, 0, ',', '.') }}</td>
                                    @endif
                                </tr>
                                <tr class="fw-semibold">
                                    @if($slip->deduction)
                                    <td>{{ __('Deduction Lainnya') }}</td>
                                    <td class="text-end">{{ number_format($slip->deduction ?? 0, 0, ',', '.') }}</td>
                                    @endif
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- TOTAL --}}
        <div class="card shadow-sm border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <span class="fw-bold fs-5">{{ __('Total Gaji Diterima') }}</span>
                <span class="fw-bold fs-4 text-success">
                    Rp {{ number_format($slip->tot_diterima ?? 0, 0, ',', '.') }}
                </span>
            </div>
        </div>
    </div>
</div>
@endsection