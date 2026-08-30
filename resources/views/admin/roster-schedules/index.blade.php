@extends('layouts.app')

@section('title', 'Jadwal Roster')

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4 gap-2">
            <div>
                <h4 class="fw-bold mb-1">Jadwal Roster</h4>
                <small class="text-muted">Siklus 10 minggu bekerja dan 2 minggu off. Periode I–V dihitung otomatis berdasarkan tanggal mulai off dalam tahun yang sama.</small>
            </div>
            <div class="ms-md-auto">
                <a href="{{ route('roster-schedules.history') }}" class="btn btn-outline-primary me-1">
                    <i class="fas fa-history me-1"></i> Riwayat Excel
                </a>
                <a href="{{ route('roster-schedules.import.create') }}" class="btn btn-outline-secondary me-1">
                    <i class="fas fa-file-import me-1"></i> Import Riwayat
                </a>
                <a href="{{ route('roster-schedules.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Generate Jadwal
                </a>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label">Cari karyawan</label>
                        <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Nama atau NIK">
                    </div>
                    <div class="col-lg-2 col-md-3">
                        <label class="form-label">Tahun</label>
                        <select name="year" class="form-select">
                            <option value="">Semua tahun</option>
                            @foreach($yearOptions as $year)
                            <option value="{{ $year }}" @selected((string)($filters['year'] ?? '') === (string)$year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3">
                        <label class="form-label">Realisasi</label>
                        <select name="realization_type" class="form-select">
                            <option value="">Semua</option>
                            @foreach($realizationOptions as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['realization_type'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3">
                        <label class="form-label">Status</label>
                        <select name="active" class="form-select">
                            <option value="">Semua</option>
                            <option value="1" @selected(($filters['active'] ?? '') === '1')>Aktif</option>
                            <option value="0" @selected(($filters['active'] ?? '') === '0')>Nonaktif</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3 d-flex gap-2">
                        <button class="btn btn-primary flex-fill">Filter</button>
                        <a href="{{ route('roster-schedules.index') }}" class="btn btn-light border">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Karyawan</th>
                                <th>Periode</th>
                                <th>Masa Kerja</th>
                                <th>Jadwal Off</th>
                                <th>Realisasi</th>
                                <th>Reminder</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($schedules as $schedule)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ optional($schedule->employee)->nama_karyawan ?: 'Karyawan tidak ditemukan' }}</div>
                                    <small class="text-muted">{{ $schedule->employee_nik }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-primary">{{ $schedule->period_label }}</span>
                                    <div><small class="text-muted">Siklus #{{ $schedule->cycle_number ?: '-' }}</small></div>
                                </td>
                                <td>{{ $schedule->work_start->format('d M Y') }}<br><small class="text-muted">s.d. {{ $schedule->work_end->format('d M Y') }} · Hak {{ $schedule->earned_off_days }} OFF</small></td>
                                <td>
                                    {{ $schedule->off_start->format('d M Y') }}<br><small class="text-muted">s.d. {{ $schedule->off_end->format('d M Y') }}</small>
                                    @if($schedule->isOverduePending($today))
                                        <div class="mt-1">
                                            <span class="badge bg-danger">Terlambat Mengajukan</span>
                                            <small class="d-block text-danger mt-1">
                                                Terlambat {{ $schedule->off_start->diffInDays($today) }} hari
                                            </small>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $realizationClass = $schedule->realization_type === 'cuti_roster'
                                            ? 'success'
                                            : ($schedule->realization_type === 'insentif' ? 'info' : 'warning');
                                    @endphp
                                    <span class="badge bg-{{ $realizationClass }}">{{ $schedule->realization_label }}</span>
                                    @if(!$schedule->is_active)<span class="badge bg-secondary">Nonaktif</span>@endif
                                </td>
                                <td>
                                    @if($schedule->reminder_queued_at)
                                    <span class="badge bg-info">Dalam antrean</span>
                                    @elseif($schedule->reminder_failed_at && (!$schedule->reminder_sent_at || $schedule->reminder_failed_at->gt($schedule->reminder_sent_at)))
                                    <span class="badge bg-danger" title="{{ $schedule->reminder_error }}">Gagal</span>
                                    @elseif($schedule->reminder_sent_at)
                                    <span class="badge bg-success">Terkirim</span>
                                    <div><small class="text-muted">{{ $schedule->reminder_sent_at->format('d M Y H:i') }}</small></div>
                                    @else
                                    <span class="badge bg-light text-dark border">Belum jatuh tempo</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex flex-column flex-sm-row align-items-end gap-1">
                                        @if($schedule->isOverduePending($today))
                                            @php
                                                $cooldownHours = max(1, (int) config('roster.overdue_reminder_cooldown_hours', 24));
                                                $nextReminderAt = $schedule->reminder_sent_at
                                                    ? $schedule->reminder_sent_at->copy()->addHours($cooldownHours)
                                                    : null;
                                                $isCoolingDown = $nextReminderAt && $nextReminderAt->gt(now());
                                                $isReminderEligible = in_array(
                                                    (int) $schedule->id,
                                                    $overdueReminderEligibleIds,
                                                    true
                                                );
                                                $hasActiveApplication = in_array(
                                                    (int) $schedule->id,
                                                    $overdueReminderActiveApplicationIds,
                                                    true
                                                );
                                                $reminderUnavailableReason = null;
                                                if (optional($schedule->employee)->status_resign !== 'AKTIF') {
                                                    $reminderUnavailableReason = 'Karyawan tidak aktif';
                                                } elseif ($hasActiveApplication) {
                                                    $reminderUnavailableReason = 'Pengajuan digital aktif';
                                                }
                                            @endphp
                                            <form method="POST"
                                                  action="{{ route('roster-schedules.reminder.overdue', $schedule) }}"
                                                  class="js-roster-action-form">
                                                @csrf
                                                <button type="submit"
                                                        class="btn btn-sm btn-outline-danger"
                                                        @disabled($schedule->reminder_queued_at || $isCoolingDown || !$isReminderEligible)>
                                                    @if($reminderUnavailableReason)
                                                        <i class="fas fa-ban me-1"></i> {{ $reminderUnavailableReason }}
                                                    @elseif($schedule->reminder_queued_at)
                                                        <i class="fas fa-clock me-1"></i> Dalam antrean
                                                    @elseif($isCoolingDown)
                                                        <i class="fas fa-hourglass-half me-1"></i> Dalam cooldown
                                                    @elseif(!$isReminderEligible)
                                                        <i class="fas fa-ban me-1"></i> Reminder tidak tersedia
                                                    @else
                                                        <i class="fas fa-paper-plane me-1"></i> Kirim Reminder Lagi
                                                    @endif
                                                </button>
                                                @if($isCoolingDown && !$reminderUnavailableReason)
                                                    <small class="d-block text-muted mt-1">
                                                        Dapat dikirim lagi {{ $nextReminderAt->format('d M Y H:i') }}
                                                    </small>
                                                @endif
                                            </form>
                                        @endif
                                        <a href="{{ route('roster-schedules.edit', $schedule) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center py-5 text-muted">Belum ada jadwal roster sesuai filter.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($schedules->hasPages())
            <div class="card-footer bg-white">{{ $schedules->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('submit', function (event) {
    const form = event.target.closest('.js-roster-action-form');

    if (!form) {
        return;
    }

    const button = form.querySelector('button[type="submit"]');

    if (!button || button.disabled) {
        event.preventDefault();
        return;
    }

    button.dataset.originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Memasukkan ke antrean...';
});
</script>
@endpush
