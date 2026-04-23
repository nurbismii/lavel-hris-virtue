@extends('layouts.app')

@section('title', 'Detail Perintah Lembur')

@section('content')
@php
    $attendanceOutcome = 'Menunggu hari lembur';

    if ($overtimeOrder->employee_response_status === \App\Models\OvertimeOrder::RESPONSE_REJECTED) {
        $attendanceOutcome = 'Tidak berlaku karena ditolak karyawan';
    } elseif ($overtimeOrder->employee_response_status === \App\Models\OvertimeOrder::RESPONSE_ACCEPTED) {
        if ($attendanceRecord && ($attendanceRecord->jam_masuk || $attendanceRecord->jam_pulang)) {
            $attendanceOutcome = 'Hadir';
        } elseif ($overtimeOrder->isPastDate()) {
            $attendanceOutcome = 'Alpa';
        } else {
            $attendanceOutcome = 'Menunggu kehadiran';
        }
    }
@endphp

<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex justify-content-between align-items-center pt-2 pb-4">
            <div>
                <h4 class="fw-bold mb-1">Detail Perintah Lembur</h4>
                <small class="text-muted">Pantau respons karyawan dan hasil kehadirannya.</small>
            </div>
            <a href="{{ route('overtime-orders.index') }}" class="btn btn-light">Kembali</a>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="small text-muted">Karyawan</div>
                                <div class="fw-semibold">{{ optional($overtimeOrder->employee)->nama_karyawan ?? '-' }}</div>
                                <div class="small text-muted">{{ $overtimeOrder->nik_karyawan }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="small text-muted">Pembuat Perintah</div>
                                <div class="fw-semibold">{{ optional($overtimeOrder->requester)->name ?? '-' }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="small text-muted">Tipe Lembur</div>
                                <div class="fw-semibold">{{ $overtimeOrder->type_label }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="small text-muted">Tanggal</div>
                                <div class="fw-semibold">{{ $overtimeOrder->overtime_date->translatedFormat('d F Y') }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="small text-muted">Jam</div>
                                <div class="fw-semibold">{{ $overtimeOrder->time_range_text }}</div>
                            </div>
                            <div class="col-md-12">
                                <div class="small text-muted">Alasan / Dasar Perintah</div>
                                <div class="fw-semibold">{{ $overtimeOrder->reason }}</div>
                            </div>
                            <div class="col-md-12">
                                <div class="small text-muted">Catatan Perintah</div>
                                <div class="fw-semibold">{{ $overtimeOrder->instruction_notes ?: '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body d-flex flex-column gap-3">
                        <div>
                            <div class="small text-muted">Respons Karyawan</div>
                            <span class="badge bg-{{ $overtimeOrder->response_badge_class }}">{{ $overtimeOrder->response_label }}</span>
                        </div>
                        <div>
                            <div class="small text-muted">Waktu Respons</div>
                            <div class="fw-semibold">{{ $overtimeOrder->employee_response_at ? $overtimeOrder->employee_response_at->translatedFormat('d M Y H:i') : '-' }}</div>
                        </div>
                        <div>
                            <div class="small text-muted">Catatan Karyawan</div>
                            <div class="fw-semibold">{{ $overtimeOrder->employee_response_notes ?: '-' }}</div>
                        </div>
                        <div>
                            <div class="small text-muted">Hasil Kehadiran</div>
                            <div class="fw-semibold">{{ $attendanceOutcome }}</div>
                        </div>
                        @if($attendanceRecord)
                            <div>
                                <div class="small text-muted">Presensi Aktual</div>
                                <div class="fw-semibold">
                                    Masuk {{ $attendanceRecord->jam_masuk ?: '--:--' }} |
                                    Pulang {{ $attendanceRecord->jam_pulang ?: '--:--' }}
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
