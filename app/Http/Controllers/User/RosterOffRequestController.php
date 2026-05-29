<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Roster\RosterOffRequestRequest;
use App\Models\ApprovalDelegation;
use App\Models\Employee;
use App\Models\RosterOffRequest;
use App\Services\Approvals\ApprovalDelegationService;
use App\Services\Notifications\ApprovalNotificationService;
use App\Services\Presensi\AttendancePeriodLockService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RosterOffRequestController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeRosterOffUser($request);

        $offRequests = RosterOffRequest::query()
            ->where('nik_karyawan', $request->user()->nik_karyawan)
            ->latest('tanggal_off')
            ->latest('id')
            ->get();

        return view('user.roster-off.index', [
            'employee' => $request->user()->employee,
            'offRequests' => $offRequests,
            'canSubmitOffRequest' => filled($request->user()->nik_karyawan),
        ]);
    }

    public function store(RosterOffRequestRequest $request)
    {
        $validated = $request->validated();
        $nikKaryawan = $request->user()->nik_karyawan;
        $tanggalOff = Carbon::parse($validated['tanggal_off'])->toDateString();

        $periodLockMessage = app(AttendancePeriodLockService::class)->guardDate(
            $tanggalOff,
            'Pengajuan OFF roster'
        );

        if ($periodLockMessage) {
            toast()->warning('Peringatan', $periodLockMessage);
            return back()->withInput();
        }

        $result = DB::transaction(function () use ($request, $validated, $nikKaryawan, $tanggalOff) {
            $activeRequestExists = RosterOffRequest::query()
                ->where('nik_karyawan', $nikKaryawan)
                ->whereDate('tanggal_off', $tanggalOff)
                ->when(Schema::hasColumn('roster_off_requests', 'delegate_status'), function ($query) {
                    $query->where(fn($delegateQuery) => $delegateQuery->whereNull('delegate_status')->orWhere('delegate_status', '!=', RosterOffRequest::STATUS_REJECTED));
                })
                ->where('status_hod', '!=', RosterOffRequest::STATUS_REJECTED)
                ->where('status_hrd', '!=', RosterOffRequest::STATUS_REJECTED)
                ->lockForUpdate()
                ->exists();

            if ($activeRequestExists) {
                return [
                    'status' => false,
                    'message' => 'Tanggal OFF tersebut sudah pernah diajukan dan masih aktif dalam proses approval.',
                ];
            }

            $employee = Employee::query()
                ->where('nik', $nikKaryawan)
                ->lockForUpdate()
                ->firstOrFail();
            $delegationService = app(ApprovalDelegationService::class);
            $delegations = $delegationService->activeDelegationsForEmployee(
                $employee,
                ApprovalDelegation::MODULE_ROSTER_OFF,
                $request->user()
            );

            $offRequest = RosterOffRequest::create(array_merge([
                'nik_karyawan' => $nikKaryawan,
                'requested_by' => $request->user()->id,
                'tanggal_off' => $validated['tanggal_off'],
                'alasan' => $validated['alasan'] ?? null,
                'status_hod' => RosterOffRequest::STATUS_PENDING,
                'status_hrd' => RosterOffRequest::STATUS_PENDING,
            ], $delegationService->submissionPayload('roster_off_requests', $delegations)));

            $delegationService->createAssignments($offRequest, $delegations, ApprovalDelegation::MODULE_ROSTER_OFF);

            return [
                'status' => true,
                'message' => 'Pengajuan OFF roster berhasil dikirim.',
                'off_request' => $offRequest->fresh(['employee']),
            ];
        });

        if (!$result['status']) {
            toast()->warning('Peringatan', $result['message']);
            return back()->withInput();
        }

        if (!empty($result['off_request'])) {
            app(ApprovalNotificationService::class)->notifyRosterOffSubmitted($result['off_request']);
        }

        toast()->success('Berhasil', $result['message']);
        return redirect()->route('roster-off.index');
    }

    public function destroy(Request $request, RosterOffRequest $rosterOff)
    {
        $this->authorizeRosterOffUser($request);

        abort_unless($rosterOff->nik_karyawan === $request->user()->nik_karyawan, 403);

        if (!$rosterOff->can_be_managed_by_employee) {
            toast()->warning('Peringatan', 'Pengajuan OFF yang sudah diproses tidak dapat dihapus.');
            return redirect()->route('roster-off.index');
        }

        $periodLockMessage = app(AttendancePeriodLockService::class)->guardDate(
            $rosterOff->tanggal_off,
            'Penghapusan pengajuan OFF roster'
        );

        if ($periodLockMessage) {
            toast()->warning('Peringatan', $periodLockMessage);
            return redirect()->route('roster-off.index');
        }

        $rosterOff->delete();

        toast()->success('Berhasil', 'Pengajuan OFF roster berhasil dihapus.');
        return redirect()->route('roster-off.index');
    }

    public function effectiveDates(Request $request)
    {
        $this->authorizeRosterOffUser($request);

        $validated = $request->validate([
            'periode_awal' => ['required', 'date'],
            'periode_akhir' => ['required', 'date', 'after_or_equal:periode_awal'],
        ]);

        $dates = RosterOffRequest::query()
            ->effectiveForAttendance()
            ->where('nik_karyawan', $request->user()->nik_karyawan)
            ->whereBetween('tanggal_off', [
                Carbon::parse($validated['periode_awal'])->toDateString(),
                Carbon::parse($validated['periode_akhir'])->toDateString(),
            ])
            ->orderBy('tanggal_off')
            ->get(['id', 'tanggal_off', 'alasan', 'status_hod', 'status_hrd'])
            ->map(function (RosterOffRequest $offRequest) {
                return [
                    'id' => $offRequest->id,
                    'date' => $offRequest->tanggal_off->toDateString(),
                    'reason' => $offRequest->alasan,
                    'status_hod' => RosterOffRequest::statusText((int) $offRequest->status_hod),
                    'status_hrd' => RosterOffRequest::statusText((int) $offRequest->status_hrd),
                ];
            })
            ->values();

        return response()->json([
            'data' => $dates,
        ]);
    }

    private function authorizeRosterOffUser(Request $request): void
    {
        abort_unless(
            $request->user() && $request->user()->hasRole(['Staff Roster', 'Super Admin']),
            403,
            'Fitur pengajuan OFF hanya tersedia untuk karyawan roster.'
        );
    }
}
