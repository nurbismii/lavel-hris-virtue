@extends('layouts.app')

@section('title', 'Closing Presensi')

@php
    $summaryLabels = [
        'active_employees' => 'Karyawan aktif',
        'attendance_records' => 'Baris presensi',
        'incomplete_attendance_records' => 'Presensi belum lengkap',
        'pending_face_reviews' => 'Review wajah pending',
        'rejected_face_reviews' => 'Review wajah ditolak',
        'pending_cuti_hod' => 'Cuti pending HOD',
        'pending_cuti_hrd' => 'Cuti pending HR',
        'pending_izin_hod' => 'Izin pending HOD',
        'pending_izin_hrd' => 'Izin pending HR',
        'pending_roster_hod' => 'Roster pending HOD',
        'pending_roster_hrd' => 'Roster pending HR',
        'pending_roster_off_hod' => 'OFF roster pending HOD',
        'pending_roster_off_hrd' => 'OFF roster pending HR',
        'pending_attendance_correction_hod' => 'Koreksi pending HOD',
        'pending_attendance_correction_hrd' => 'Koreksi pending HR',
        'pending_overtime_responses' => 'Respons lembur pending',
    ];
    $displaySummary = session('closing_summary') ?: $summary;
    $blockerLookup = array_flip($blockerKeys);
@endphp

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4 gap-2">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-lock text-primary me-2"></i>
                    Closing Presensi
                </h4>
                <small class="text-muted">
                    Kunci periode presensi agar data payroll tidak berubah tanpa proses buka ulang yang tercatat.
                </small>
            </div>
        </div>

        @if(!$isTableReady)
            <div class="alert alert-warning">
                Fitur closing presensi belum aktif karena tabel <code>attendance_period_locks</code> belum tersedia. Jalankan <code>php artisan migrate</code> terlebih dahulu.
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <strong>Validasi gagal.</strong>
                <ul class="mb-0 mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Cutoff Payroll</label>
                        <input type="month" name="period_month" class="form-control" value="{{ $periodMonth }}">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Rentang Periode</label>
                        <div class="form-control bg-light">
                            {{ $period['label'] }}
                        </div>
                    </div>
                    <div class="col-md-4 d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i> Tampilkan
                        </button>
                        <a href="{{ route('attendance-period-locks.index') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-undo me-1"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        @if($isTableReady)
            <div class="row g-3 mb-3">
                @foreach($summaryLabels as $key => $label)
                    @php
                        $value = (int) ($displaySummary[$key] ?? 0);
                        $isBlocker = isset($blockerLookup[$key]) && $value > 0;
                    @endphp
                    <div class="col-6 col-md-3 col-xl-2">
                        <div class="border rounded p-3 h-100 {{ $isBlocker ? 'border-danger bg-danger-subtle' : 'bg-light' }}">
                            <div class="small text-muted">{{ $label }}</div>
                            <div class="fs-5 fw-bold {{ $isBlocker ? 'text-danger' : '' }}">
                                {{ number_format($value) }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($hasBlockers || session('closing_summary'))
                <div class="alert alert-warning">
                    Selesaikan item pending yang berwarna merah sebelum periode dikunci. Closing tidak akan memaksa approval atau review yang belum selesai.
                </div>
            @endif

            @if($currentLock && $currentLock->is_locked)
                <div class="alert alert-danger">
                    Periode {{ $currentLock->period_label }} sedang terkunci sejak
                    {{ optional($currentLock->closed_at)->format('d M Y H:i') }}.
                </div>
            @elseif($currentLock && !$currentLock->is_locked)
                <div class="alert alert-secondary">
                    Periode ini pernah dikunci dan sudah dibuka ulang pada
                    {{ optional($currentLock->reopened_at)->format('d M Y H:i') }}.
                </div>
            @endif

            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body">
                    <h5 class="mb-1">Tutup Periode</h5>
                    <p class="text-muted small mb-3">
                        Setelah dikunci, perubahan presensi, cuti, izin, roster, koreksi presensi, review wajah, dan lembur pada periode ini akan ditolak oleh backend.
                    </p>
                    <form method="POST" action="{{ route('attendance-period-locks.store') }}" class="js-lock-action">
                        @csrf
                        <input type="hidden" name="period_month" value="{{ $period['period_key'] }}">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-8">
                                <label class="form-label">Catatan Closing</label>
                                <textarea name="close_note" class="form-control" rows="2" maxlength="500" placeholder="Contoh: Data periode sudah direview HR dan siap dipakai payroll.">{{ old('close_note') }}</textarea>
                            </div>
                            <div class="col-md-4 d-grid">
                                <button
                                    type="submit"
                                    class="btn btn-danger"
                                    data-loading-text="Mengunci..."
                                    {{ ($currentLock && $currentLock->is_locked) || $hasBlockers ? 'disabled' : '' }}>
                                    <i class="fas fa-lock me-1"></i> Kunci Periode
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                        <div>
                            <h5 class="mb-1">Riwayat Closing</h5>
                            <small class="text-muted">Periode terbaru ditampilkan paling atas.</small>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 100px;">Periode</th>
                                    <th style="width: 210px;">Rentang</th>
                                    <th style="width: 120px;">Status</th>
                                    <th>Closing</th>
                                    <th>Buka Ulang</th>
                                    <th style="width: 280px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($locks as $lock)
                                    <tr>
                                        <td>{{ $lock->period_key }}</td>
                                        <td>{{ $lock->period_label }}</td>
                                        <td>
                                            <span class="badge {{ $lock->status_badge_class }}">
                                                {{ $lock->status_label }}
                                            </span>
                                        </td>
                                        <td>
                                            <div>{{ optional($lock->closer)->name ?: '-' }}</div>
                                            <small class="text-muted">{{ optional($lock->closed_at)->format('d M Y H:i') ?: '-' }}</small>
                                            @if($lock->close_note)
                                                <div class="small mt-1">{{ $lock->close_note }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div>{{ optional($lock->reopener)->name ?: '-' }}</div>
                                            <small class="text-muted">{{ optional($lock->reopened_at)->format('d M Y H:i') ?: '-' }}</small>
                                            @if($lock->reopen_note)
                                                <div class="small mt-1">{{ $lock->reopen_note }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            @if($lock->is_locked)
                                                <form method="POST" action="{{ route('attendance-period-locks.reopen', $lock) }}" class="js-lock-action">
                                                    @csrf
                                                    <label class="form-label small mb-1">Alasan buka ulang</label>
                                                    <textarea name="reopen_note" class="form-control form-control-sm mb-2" rows="2" maxlength="500" required placeholder="Wajib diisi"></textarea>
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-loading-text="Membuka...">
                                                        <i class="fas fa-unlock me-1"></i> Buka Ulang
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-muted small">Tidak ada aksi.</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            Belum ada periode yang dikunci.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if(method_exists($locks, 'links'))
                        <div class="mt-3">
                            {{ $locks->links() }}
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).on('submit', '.js-lock-action', function () {
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const loadingText = $button.data('loading-text') || 'Memproses...';

        $button.prop('disabled', true).data('original-html', $button.html()).html(loadingText);
    });
</script>
@endpush
