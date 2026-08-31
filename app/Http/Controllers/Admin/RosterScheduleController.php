<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Roster\StoreRosterScheduleRequest;
use App\Http\Requests\Roster\StoreManualRosterSubmissionRequest;
use App\Http\Requests\Roster\ReviewRosterScheduleHistoryRequest;
use App\Http\Requests\Roster\UpdateRosterScheduleRequest;
use App\Models\Employee;
use App\Models\RosterSchedule;
use App\Models\RosterScheduleHistory;
use App\Services\Roster\RosterScheduleHistoryService;
use App\Services\Roster\RosterScheduleManualSubmissionService;
use App\Services\Roster\RosterScheduleReminderEligibilityService;
use App\Services\Roster\RosterScheduleService;
use App\Services\Audit\AuditTrailService;
use App\Support\SafeExceptionLogger;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class RosterScheduleController extends Controller
{
    public function index(Request $request, RosterScheduleReminderEligibilityService $reminderEligibility)
    {
        $today = Carbon::today();
        $query = RosterSchedule::query()
            ->with([
                'employee:nik,nama_karyawan,departemen_id,divisi_id,status_resign',
                'manualSubmitter:id,name',
            ])
            ->priorityForToday($today);

        if ($request->filled('year')) {
            $query->where('period_year', (int) $request->input('year'));
        }

        if ($request->filled('realization_type')) {
            $query->where('realization_type', $request->input('realization_type'));
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->input('active') === '1');
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function (Builder $filter) use ($search) {
                $filter->where('employee_nik', 'like', $search . '%')
                    ->orWhereHas('employee', fn(Builder $employee) => $employee
                        ->where('nama_karyawan', 'like', '%' . $search . '%'));
            });
        }

        $perPage = (int) $request->input('per_page', 50);
        $perPage = in_array($perPage, [20, 50, 100], true) ? $perPage : 50;
        $schedules = $query->paginate($perPage)->withQueryString();
        $reminderAvailability = $reminderEligibility->overdueReminderAvailability(
            $schedules->pluck('id')->all(),
            $today
        );

        return view('admin.roster-schedules.index', [
            'schedules' => $schedules,
            'today' => $today,
            'overdueReminderEligibleIds' => $reminderAvailability['eligible_ids'],
            'overdueReminderActiveApplicationIds' => $reminderAvailability['active_application_ids'],
            'realizationOptions' => RosterSchedule::realizationOptions(),
            'filters' => $request->only(['year', 'realization_type', 'active', 'search', 'per_page']),
            'yearOptions' => RosterSchedule::query()
                ->select('period_year')
                ->distinct()
                ->orderByDesc('period_year')
                ->pluck('period_year'),
        ]);
    }

    public function create(Request $request)
    {
        $selectedEmployee = null;
        $selectedNik = old('employee_nik', $request->input('employee_nik'));

        if ($selectedNik) {
            $selectedEmployee = Employee::query()
                ->where('status_resign', 'AKTIF')
                ->find($selectedNik);
        }

        return view('admin.roster-schedules.create', compact('selectedEmployee'));
    }

    public function history(Request $request)
    {
        $query = RosterScheduleHistory::query()
            ->with(['employee:nik,nama_karyawan,status_resign', 'schedule:id,source,realization_type'])
            ->orderByDesc('scheduled_off_start')
            ->orderBy('employee_nik');

        if ($request->filled('year')) {
            $query->where('period_year', (int) $request->input('year'));
        }

        if ($request->filled('classification')) {
            $query->where('classification', $request->input('classification'));
        }

        if ($request->filled('review_status')) {
            $query->where('review_status', $request->input('review_status'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function (Builder $filter) use ($search) {
                $filter->where('employee_nik', 'like', $search . '%')
                    ->orWhereHas('employee', fn(Builder $employee) => $employee
                        ->where('nama_karyawan', 'like', '%' . $search . '%'));
            });
        }

        return view('admin.roster-schedules.history', [
            'histories' => $query->paginate(50)->withQueryString(),
            'classificationOptions' => RosterScheduleHistory::classificationOptions(),
            'filters' => $request->only(['year', 'classification', 'review_status', 'search']),
            'yearOptions' => RosterScheduleHistory::query()
                ->select('period_year')
                ->distinct()
                ->orderByDesc('period_year')
                ->pluck('period_year'),
        ]);
    }

    public function reviewHistory(RosterScheduleHistory $history)
    {
        $history->load(['employee:nik,nama_karyawan,status_resign', 'schedule']);

        return view('admin.roster-schedules.review-history', [
            'history' => $history,
            'classificationOptions' => collect(RosterScheduleHistory::classificationOptions())
                ->except(RosterScheduleHistory::CLASSIFICATION_NEED_REVIEW)
                ->all(),
        ]);
    }

    public function updateHistoryReview(
        ReviewRosterScheduleHistoryRequest $request,
        RosterScheduleHistory $history,
        RosterScheduleHistoryService $service
    ) {
        try {
            $service->confirm($history, $request->validated(), $request->user());
        } catch (Throwable $exception) {
            report($exception);
            toast()->error('Gagal', 'Review riwayat roster gagal disimpan. Silakan coba lagi.');
            return back()->withInput();
        }

        toast()->success('Berhasil', 'Riwayat roster berhasil dikonfirmasi dan tercatat pada audit trail.');

        return redirect()->route('roster-schedules.history', ['search' => $history->employee_nik]);
    }

    public function store(StoreRosterScheduleRequest $request, RosterScheduleService $service)
    {
        $employee = Employee::query()->findOrFail($request->validated()['employee_nik']);

        try {
            $schedules = $service->generateFromAnchor(
                $employee,
                Carbon::parse($request->validated()['work_start']),
                (int) $request->validated()['cycles'],
                (string) $request->user()->getAuthIdentifier()
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            toast()->error('Gagal', 'Jadwal roster gagal dibuat. Periksa data lalu coba lagi.');
            return back()->withInput();
        }

        toast()->success('Berhasil', $schedules->count() . ' jadwal roster diproses tanpa membuat data duplikat.');

        app(AuditTrailService::class)->record([
            'event' => 'roster_schedule.generated',
            'module' => 'roster_schedule',
            'reference_table' => 'roster_schedules',
            'employee_nik' => $employee->nik,
            'actor' => $request->user(),
            'new_values' => [
                'work_start' => $request->validated()['work_start'],
                'cycles' => (int) $request->validated()['cycles'],
                'processed' => $schedules->count(),
            ],
        ]);

        return redirect()->route('roster-schedules.index', ['search' => $employee->nik]);
    }

    public function edit(RosterSchedule $rosterSchedule)
    {
        $rosterSchedule->load('employee:nik,nama_karyawan,status_resign');

        return view('admin.roster-schedules.edit', [
            'schedule' => $rosterSchedule,
            'realizationOptions' => RosterSchedule::realizationOptions(),
        ]);
    }

    public function sendOverdueReminder(
        Request $request,
        RosterSchedule $rosterSchedule,
        RosterScheduleReminderEligibilityService $service
    ) {
        $contextId = (string) Str::uuid();
        $audit = app(AuditTrailService::class)->record([
            'event' => 'roster_schedule.overdue_reminder_requested',
            'module' => 'roster_schedule',
            'auditable_type' => RosterSchedule::class,
            'auditable_id' => (string) $rosterSchedule->id,
            'reference_table' => 'roster_schedules',
            'reference_id' => (string) $rosterSchedule->id,
            'employee_nik' => $rosterSchedule->employee_nik,
            'actor' => $request->user(),
            'metadata' => [
                'status' => 'requested',
                'context_id' => $contextId,
            ],
        ]);

        if ($audit === null) {
            toast()->error(
                'Gagal',
                'Permintaan reminder tidak diproses karena audit trail gagal dicatat.'
            );

            return back();
        }

        if (!$service->dispatchOverdue($rosterSchedule)) {
            $cooldownHours = max(1, (int) config('roster.overdue_reminder_cooldown_hours', 24));
            toast()->warning(
                'Belum Diproses',
                'Reminder belum dapat dikirim. Periksa status antrean, realisasi, dan cooldown ' . $cooldownHours . ' jam.'
            );

            return back();
        }

        toast()->success('Masuk Antrean', 'Reminder ulang telah masuk antrean pengiriman.');

        return back();
    }

    public function storeManualSubmission(
        StoreManualRosterSubmissionRequest $request,
        RosterSchedule $rosterSchedule,
        RosterScheduleManualSubmissionService $service
    ) {
        try {
            $updated = $service->record(
                $rosterSchedule,
                $request->validated(),
                $request->user()
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            app(SafeExceptionLogger::class)->warning(
                'roster_schedule.manual_submission',
                $exception
            );
            toast()->error('Gagal', 'Pengajuan manual gagal dicatat. Silakan coba lagi.');

            return back()->withInput();
        }

        toast()->success(
            'Berhasil',
            'Pengajuan manual dicatat sebagai ' . $updated->realization_label . '.'
        );

        return back();
    }

    public function update(
        UpdateRosterScheduleRequest $request,
        RosterSchedule $rosterSchedule,
        RosterScheduleService $service
    ) {
        $oldValues = $rosterSchedule->only([
            'work_start', 'work_end', 'off_start', 'off_end',
            'realization_type', 'notes', 'is_active',
        ]);

        try {
            $updatedSchedule = $service->updateSchedule(
                $rosterSchedule,
                $request->validated(),
                (string) $request->user()->getAuthIdentifier()
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            toast()->error('Gagal', 'Jadwal roster gagal diperbarui. Periksa urutan tanggal dan jadwal lain.');
            return back()->withInput();
        }

        toast()->success('Berhasil', 'Jadwal roster berhasil diperbarui. Status reminder dihitung ulang.');

        app(AuditTrailService::class)->record([
            'event' => 'roster_schedule.updated',
            'module' => 'roster_schedule',
            'auditable_type' => RosterSchedule::class,
            'auditable_id' => (string) $updatedSchedule->id,
            'reference_table' => 'roster_schedules',
            'reference_id' => (string) $updatedSchedule->id,
            'employee_nik' => $updatedSchedule->employee_nik,
            'actor' => $request->user(),
            'old_values' => $oldValues,
            'new_values' => $updatedSchedule->only([
                'work_start', 'work_end', 'off_start', 'off_end',
                'period_year', 'period_number', 'realization_type', 'notes', 'is_active',
            ]),
            'metadata' => ['regenerate_following' => (bool) ($request->validated()['regenerate_following'] ?? false)],
        ]);

        return redirect()->route('roster-schedules.index', ['search' => $rosterSchedule->employee_nik]);
    }

    public function searchEmployees(Request $request)
    {
        $term = trim((string) $request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 20;

        if (mb_strlen($term) < 2) {
            return response()->json(['results' => [], 'pagination' => ['more' => false]]);
        }

        $employees = Employee::query()
            ->where('status_resign', 'AKTIF')
            ->where(function (Builder $query) use ($term) {
                $query->where('nik', 'like', $term . '%')
                    ->orWhere('nama_karyawan', 'like', '%' . $term . '%');
            })
            ->orderBy('nama_karyawan')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage + 1)
            ->get(['nik', 'nama_karyawan', 'posisi', 'work_pattern_start_date']);

        return response()->json([
            'results' => $employees->take($perPage)->map(fn(Employee $employee) => [
                'id' => $employee->nik,
                'text' => $employee->nama_karyawan . ' - ' . $employee->nik . ($employee->posisi ? ' | ' . $employee->posisi : ''),
                'work_pattern_start_date' => optional($employee->work_pattern_start_date)->toDateString(),
            ])->values(),
            'pagination' => ['more' => $employees->count() > $perPage],
        ]);
    }
}
