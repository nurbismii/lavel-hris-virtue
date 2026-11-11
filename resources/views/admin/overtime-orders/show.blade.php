@extends('layouts.app')

@section('title', __('Detail Perintah Lembur'))

@section('content')
<div class="container-fluid">
    <div class="page-inner salary-page">
        <div class="d-flex justify-content-between align-items-center pt-2 pb-4">
            <div>
                <h4 class="fw-bold mb-1">{{ __('Detail Perintah Lembur') }}</h4>
                <small class="text-muted">{{ __('Pantau respons karyawan dan hasil kehadirannya.') }}</small>
            </div>
            <a href="{{ route('overtime-orders.index') }}" class="btn btn-light">{{ __('Kembali') }}</a>
        </div>

        @if(!empty($overtimePayEstimate) || !empty($salaryReviewMessage))
        <section class="salary-card"><div class="salary-card-header"><div><h2>Estimasi upah perintah lembur</h2><p>Informasi upah khusus HR / Super Admin.</p></div></div><div class="salary-card-body">
            @if(!empty($overtimePayEstimate))
            <strong class="fs-4">Rp {{ number_format($overtimePayEstimate['amount'], 0, ',', '.') }}</strong>
            <p class="salary-note mt-2">{{ $overtimePayEstimate['overtime_hours'] }} jam sesuai perintah · Dasar upah Rp {{ number_format($overtimePayEstimate['monthly_wage'], 0, ',', '.') }} · Versi upah #{{ $overtimePayEstimate['salary_history_id'] }}.</p>
            <p class="salary-note">{{ $overtimePayEstimate['day_type_label'] }}. Estimasi mengikuti durasi perintah. Realisasi jam kerja, istirahat, dan persetujuan pembayaran tetap harus diperiksa HR. Kategori hari kerja terpendek dibaca dari jadwal hari keenam jika tersedia.</p>
            @foreach($overtimePayEstimate['warnings'] as $warning)<p class="text-warning mb-1">{{ $warning }}</p>@endforeach
            @else
            <p class="salary-note">{{ $salaryReviewMessage }}</p>
            @endif
            <a class="btn btn-light mt-3" href="{{ route('employee-salaries.index') }}">Riwayat upah</a>
        </div></section>
        @endif

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="small text-muted">{{ __('Karyawan') }}</div>
                                <div class="fw-semibold">{{ optional($overtimeOrder->employee)->nama_karyawan ?? '-' }}</div>
                                <div class="small text-muted">{{ $overtimeOrder->nik_karyawan }}</div>
                                <div class="small text-muted">ID perintah lembur #{{ $overtimeOrder->id }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="small text-muted">{{ __('Pembuat Perintah') }}</div>
                                <div class="fw-semibold">{{ optional($overtimeOrder->requester)->name ?? '-' }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="small text-muted">{{ __('Tipe Lembur') }}</div>
                                <div class="fw-semibold">{{ $overtimeOrder->type_label }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="small text-muted">{{ __('Tanggal') }}</div>
                                <div class="fw-semibold">{{ $overtimeOrder->overtime_date->translatedFormat('d F Y') }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="small text-muted">{{ __('Jam') }}</div>
                                <div class="fw-semibold">{{ $overtimeOrder->time_range_text }}</div>
                            </div>
                            <div class="col-md-12">
                                <div class="small text-muted">{{ __('Alasan / Dasar Perintah') }}</div>
                                <div class="fw-semibold">{{ $overtimeOrder->reason }}</div>
                            </div>
                            <div class="col-md-12">
                                <div class="small text-muted">{{ __('Catatan Perintah') }}</div>
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
                            <div class="small text-muted">{{ __('Respons Karyawan') }}</div>
                            <span class="badge bg-{{ $overtimeOrder->response_badge_class }}">{{ $overtimeOrder->response_label }}</span>
                        </div>
                        <div>
                            <div class="small text-muted">{{ __('Waktu Respons') }}</div>
                            <div class="fw-semibold">{{ $overtimeOrder->employee_response_at ? $overtimeOrder->employee_response_at->translatedFormat('d M Y H:i') : '-' }}</div>
                        </div>
                        <div>
                            <div class="small text-muted">{{ __('Catatan Karyawan') }}</div>
                            <div class="fw-semibold">{{ $overtimeOrder->employee_response_notes ?: '-' }}</div>
                        </div>
                        <div>
                            <div class="small text-muted">{{ __('Hasil Kehadiran') }}</div>
                            <span class="badge bg-{{ $attendanceOutcome['badge_class'] ?? 'secondary' }}">
                                {{ $attendanceOutcome['label'] ?? '-' }}
                            </span>
                        </div>
                        @if($attendanceRecord)
                            <div>
                                <div class="small text-muted">{{ __('Presensi Aktual') }}</div>
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
