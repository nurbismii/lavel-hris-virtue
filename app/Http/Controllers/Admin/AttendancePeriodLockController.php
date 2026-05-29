<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Presensi\CloseAttendancePeriodRequest;
use App\Http\Requests\Presensi\ReopenAttendancePeriodRequest;
use App\Models\AttendancePeriodLock;
use App\Services\Presensi\AttendancePeriodLockService;
use Illuminate\Http\Request;

class AttendancePeriodLockController extends Controller
{
    public function index(Request $request, AttendancePeriodLockService $service)
    {
        $periodMonth = $request->input('period_month', now()->format('Y-m'));

        if (
            !is_string($periodMonth)
            || !preg_match('/^\d{4}-\d{2}$/', (string) $periodMonth)
            || !checkdate((int) substr($periodMonth, 5, 2), 1, (int) substr($periodMonth, 0, 4))
        ) {
            $periodMonth = now()->format('Y-m');
        }

        $period = $service->periodForMonth($periodMonth);
        $isTableReady = $service->tableReady();
        $summary = $isTableReady
            ? $service->buildSummary($period['start_date'], $period['end_date'])
            : [];

        $locks = $isTableReady
            ? AttendancePeriodLock::query()
                ->with(['closer:id,name', 'reopener:id,name'])
                ->latest('start_date')
                ->latest('id')
                ->paginate(20)
                ->withQueryString()
            : collect();

        $currentLock = $isTableReady
            ? AttendancePeriodLock::query()->where('period_key', $period['period_key'])->first()
            : null;

        return view('admin.attendance-period-locks.index', [
            'isTableReady' => $isTableReady,
            'periodMonth' => $periodMonth,
            'period' => $period,
            'summary' => $summary,
            'hasBlockers' => $isTableReady ? $service->summaryHasBlockers($summary) : false,
            'blockerKeys' => $service->blockerKeys(),
            'locks' => $locks,
            'currentLock' => $currentLock,
        ]);
    }

    public function store(CloseAttendancePeriodRequest $request, AttendancePeriodLockService $service)
    {
        $validated = $request->validated();
        $result = $service->closePeriod(
            $request->user(),
            $validated['period_month'],
            $validated['close_note'] ?? null
        );

        if (!$result['status']) {
            toast()->warning('Peringatan', $result['message']);

            return back()
                ->withInput()
                ->with('closing_summary', $result['summary'] ?? []);
        }

        toast()->success('Berhasil', $result['message']);

        return redirect()->route('attendance-period-locks.index', [
            'period_month' => $result['lock']->period_key,
        ]);
    }

    public function reopen(
        ReopenAttendancePeriodRequest $request,
        AttendancePeriodLock $attendancePeriodLock,
        AttendancePeriodLockService $service
    ) {
        $result = $service->reopenPeriod(
            $attendancePeriodLock,
            $request->user(),
            $request->validated()['reopen_note']
        );

        if (!$result['status']) {
            toast()->warning('Peringatan', $result['message']);
            return back();
        }

        toast()->success('Berhasil', $result['message']);

        return redirect()->route('attendance-period-locks.index', [
            'period_month' => $result['lock']->period_key,
        ]);
    }
}
