@extends('layouts.app')

@section('title', 'Jadwal Roster')

@section('content')
<div id="rosterActionFeedback"
     class="alert d-none position-fixed top-0 end-0 m-3 shadow"
     role="alert"
     aria-live="assertive"
     style="z-index: 2000; max-width: 420px;"></div>
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
                                    @if($schedule->manual_submitted_at)
                                        <div class="mt-1">
                                            <span class="badge bg-dark">Pengajuan Manual</span>
                                            @if($schedule->manual_reference_number)
                                                <small class="d-block text-muted mt-1">Referensi: {{ $schedule->manual_reference_number }}</small>
                                            @endif
                                            <small class="d-block text-muted">
                                                {{ $schedule->manual_submitted_at->format('d M Y H:i') }} ·
                                                @if($schedule->manualSubmitter)
                                                    {{ $schedule->manualSubmitter->name }} ({{ $schedule->manual_submitted_by }})
                                                @else
                                                    {{ $schedule->manual_submitted_by ?: 'Akun tidak ditemukan' }}
                                                @endif
                                            </small>
                                        </div>
                                    @endif
                                </td>
                                <td id="roster-reminder-status-{{ $schedule->id }}">
                                    @if($schedule->reminder_queued_at)
                                    <span class="badge bg-info">Dalam antrean</span>
                                    @elseif($schedule->reminder_failed_at && (!$schedule->reminder_sent_at || $schedule->reminder_failed_at->gt($schedule->reminder_sent_at)))
                                    <span class="badge bg-danger" title="{{ $schedule->reminder_error }}">Gagal</span>
                                    @elseif($schedule->reminder_sent_at)
                                    <span class="badge bg-success">Terkirim</span>
                                    <div><small class="text-muted">{{ $schedule->reminder_sent_at->format('d M Y H:i') }}</small></div>
                                    @elseif($schedule->isOverduePending($today))
                                    <span class="badge bg-warning text-dark">Belum dikirim</span>
                                    <div><small class="text-muted">Reminder perlu diproses</small></div>
                                    @else
                                    <span class="badge bg-light text-dark border">Belum jatuh tempo</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex flex-column flex-sm-row align-items-end gap-1">
                                        @if($schedule->is_active && $schedule->realization_type === \App\Models\RosterSchedule::REALIZATION_PENDING)
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-success js-manual-submission-button"
                                                    data-manual-schedule-id="{{ $schedule->id }}"
                                                    data-manual-employee="{{ optional($schedule->employee)->nama_karyawan ?: 'Karyawan tidak ditemukan' }} · {{ $schedule->employee_nik }}"
                                                    data-manual-action="{{ route('roster-schedules.manual-submission.store', $schedule) }}"
                                                    aria-haspopup="dialog"
                                                    aria-controls="manualSubmissionModal">
                                                <i class="fas fa-file-signature me-1"></i> Catat Pengajuan Manual
                                            </button>
                                        @endif
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
                                                $isReminderDisabled = $schedule->reminder_queued_at
                                                    || $isCoolingDown
                                                    || !$isReminderEligible;
                                                if (!$reminderUnavailableReason && $schedule->reminder_queued_at) {
                                                    $reminderUnavailableReason = 'Reminder sudah berada dalam antrean pengiriman.';
                                                } elseif (!$reminderUnavailableReason && $isCoolingDown) {
                                                    $reminderUnavailableReason = 'Reminder masih dalam cooldown. Dapat dikirim lagi pada ' . $nextReminderAt->format('d M Y H:i') . '.';
                                                } elseif (!$reminderUnavailableReason && !$isReminderEligible) {
                                                    $reminderUnavailableReason = 'Reminder tidak tersedia karena jadwal tidak lagi memenuhi syarat pengiriman.';
                                                }
                                            @endphp
                                            @if($isReminderDisabled)
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-secondary js-reminder-unavailable-button"
                                                    data-reminder-unavailable-reason="{{ $reminderUnavailableReason }}">
                                                <i class="fas fa-info-circle me-1"></i> Lihat Status Reminder
                                            </button>
                                            @else
                                            <form method="POST"
                                                  action="{{ route('roster-schedules.reminder.overdue', $schedule) }}"
                                                  class="js-roster-action-form">
                                                @csrf
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-danger js-roster-reminder-button"
                                                        data-reminder-employee="{{ optional($schedule->employee)->nama_karyawan ?: 'Karyawan tidak ditemukan' }} ({{ $schedule->employee_nik }})"
                                                        data-reminder-period="{{ optional($schedule->off_start)->format('d M Y') ?: '-' }} s.d. {{ optional($schedule->off_end)->format('d M Y') ?: '-' }}">
                                                    <i class="fas fa-paper-plane me-1"></i> Kirim Reminder Lagi
                                                </button>
                                            </form>
                                            @endif
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

<div class="modal fade" id="manualSubmissionModal" tabindex="-1" aria-labelledby="manualSubmissionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="" id="manualSubmissionForm" class="js-manual-submission-form">
                @csrf
                <input type="hidden" name="manual_schedule_id" id="manualScheduleId" value="{{ old('manual_schedule_id') }}">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="manualSubmissionModalLabel">Catat Pengajuan Manual</h5>
                        <small class="text-muted" id="manualSubmissionEmployee"></small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2">
                        Form ini mencatat penerimaan pengajuan offline dan tidak membuat approval digital.
                    </div>
                    <div class="mb-3">
                        <label for="manualRealizationType" class="form-label">Realisasi <span class="text-danger">*</span></label>
                        <select name="realization_type" id="manualRealizationType" class="form-select @error('realization_type') is-invalid @enderror" required>
                            <option value="">Pilih realisasi</option>
                            <option value="{{ \App\Models\RosterSchedule::REALIZATION_CUTI }}" @if(old('realization_type') === \App\Models\RosterSchedule::REALIZATION_CUTI) selected @endif>Cuti Roster</option>
                            <option value="{{ \App\Models\RosterSchedule::REALIZATION_INSENTIF }}" @if(old('realization_type') === \App\Models\RosterSchedule::REALIZATION_INSENTIF) selected @endif>Insentif</option>
                        </select>
                        @error('realization_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label for="manualReferenceNumber" class="form-label">Nomor referensi</label>
                        <input type="text" name="manual_reference_number" id="manualReferenceNumber" maxlength="100" value="{{ old('manual_reference_number') }}" class="form-control @error('manual_reference_number') is-invalid @enderror">
                        @error('manual_reference_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="manualSubmissionNote" class="form-label">Catatan</label>
                        <textarea name="manual_submission_note" id="manualSubmissionNote" maxlength="500" class="form-control @error('manual_submission_note') is-invalid @enderror" rows="3">{{ old('manual_submission_note') }}</textarea>
                        @error('manual_submission_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i> Simpan Pengajuan Manual
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function initializeRosterScheduleActions() {
    const modalElement = document.getElementById('manualSubmissionModal');
    const form = document.getElementById('manualSubmissionForm');
    const employee = document.getElementById('manualSubmissionEmployee');
    const scheduleId = document.getElementById('manualScheduleId');
    const feedback = document.getElementById('rosterActionFeedback');
    const oldScheduleId = @json((string) old('manual_schedule_id', ''));

    if (!modalElement || !form || modalElement.dataset.rosterInitialized === '1') {
        return;
    }

    modalElement.dataset.rosterInitialized = '1';
    const buttons = Array.from(document.querySelectorAll('.js-manual-submission-button'));
    let feedbackTimer = null;

    const showFeedback = function (message, type) {
        if (!feedback) {
            return;
        }

        const alertType = ['success', 'danger', 'warning', 'info'].includes(type) ? type : 'info';
        feedback.className = 'alert alert-' + alertType + ' position-fixed top-0 end-0 m-3 shadow';
        feedback.textContent = message;
        feedback.classList.remove('d-none');

        if (feedbackTimer) {
            window.clearTimeout(feedbackTimer);
        }

        feedbackTimer = window.setTimeout(function () {
            feedback.classList.add('d-none');
        }, 5000);
    };

    const showInformationAlert = function (title, message) {
        if (typeof window.swal === 'function') {
            return window.swal({
                title: title,
                text: message,
                icon: 'info',
                button: 'OK'
            });
        }

        showFeedback(title + ': ' + message, 'info');
        return Promise.resolve(true);
    };

    const openManualModal = function () {
        if (!window.bootstrap || !bootstrap.Modal) {
            showFeedback('Form gagal dibuka karena komponen modal belum siap. Muat ulang halaman lalu coba lagi.', 'danger');
            return;
        }

        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    };

    const prepareManualForm = function (button) {
        form.action = button.dataset.manualAction || '';
        scheduleId.value = button.dataset.manualScheduleId || '';
        employee.textContent = button.dataset.manualEmployee || '';

        if (oldScheduleId === '' || oldScheduleId !== button.dataset.manualScheduleId) {
            form.querySelector('[name="realization_type"]').value = '';
            form.querySelector('[name="manual_reference_number"]').value = '';
            form.querySelector('[name="manual_submission_note"]').value = '';
        }
    };

    buttons.forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            prepareManualForm(button);

            if (window.AppDialog && typeof window.AppDialog.confirm === 'function') {
                window.AppDialog.confirm({
                    title: 'Catat Pengajuan Manual?',
                    text: 'Form pencatatan pengajuan offline akan dibuka untuk ' + (button.dataset.manualEmployee || 'karyawan ini') + '.',
                    icon: 'info',
                    confirmButtonText: 'Buka Form',
                    cancelButtonText: 'Batal'
                }).then(function (confirmed) {
                    if (confirmed) {
                        showFeedback('Form pengajuan manual dibuka. Lengkapi data lalu tekan Simpan.', 'info');
                        openManualModal();
                    }
                });
                return;
            }

            showFeedback('Form pengajuan manual dibuka. Lengkapi data lalu tekan Simpan.', 'info');
            openManualModal();
        });
    });

    if (oldScheduleId !== '') {
        const restoreButton = buttons.find(function (button) {
            return button.dataset.manualScheduleId === oldScheduleId;
        });

        if (restoreButton) {
            prepareManualForm(restoreButton);

            showFeedback('Validasi belum berhasil. Periksa kembali data pengajuan manual.', 'warning');
            openManualModal();
        }
    }

    document.querySelectorAll('.js-reminder-unavailable-button').forEach(function (button) {
        button.addEventListener('click', function () {
            const message = button.dataset.reminderUnavailableReason || 'Reminder belum dapat diproses.';

            showInformationAlert('Status Reminder', message);
        });
    });

    document.querySelectorAll('.js-roster-reminder-button').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();

            const submittedForm = button.closest('.js-roster-action-form');

            if (!submittedForm || button.disabled) {
                showFeedback('Reminder tidak dapat diproses. Muat ulang halaman lalu coba lagi.', 'danger');
                return;
            }

            const submitReminder = function () {
                button.dataset.originalHtml = button.innerHTML;
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Memasukkan ke antrean...';
                showFeedback('Permintaan reminder diterima dan sedang dimasukkan ke antrean.', 'info');
                HTMLFormElement.prototype.submit.call(submittedForm);
            };

            const employeeName = button.dataset.reminderEmployee || 'karyawan ini';
            const period = button.dataset.reminderPeriod || 'jadwal roster terkait';

            if (window.AppDialog && typeof window.AppDialog.confirm === 'function') {
                window.AppDialog.confirm({
                    title: 'Kirim Reminder Lagi?',
                    text: 'Reminder periode ' + period + ' akan dikirim ke email ' + employeeName + '.',
                    icon: 'warning',
                    confirmButtonText: 'Ya, Kirim',
                    cancelButtonText: 'Batal'
                }).then(function (confirmed) {
                    if (confirmed) {
                        submitReminder();
                    }
                });
                return;
            }

            submitReminder();
        });
    });

    document.addEventListener('submit', function (event) {
        const manualForm = event.target.closest('.js-manual-submission-form');

        if (!manualForm) {
            return;
        }

        const button = manualForm.querySelector('button[type="submit"]');

        if (!button || button.disabled || !manualForm.action || !scheduleId.value) {
            event.preventDefault();
            return;
        }

        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Menyimpan...';
        showFeedback('Pengajuan manual sedang disimpan. Mohon tunggu.', 'info');
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeRosterScheduleActions, { once: true });
} else {
    initializeRosterScheduleActions();
}
</script>
@endpush
