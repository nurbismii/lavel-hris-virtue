<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\Roster\RosterScheduleService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateRosterSchedules extends Command
{
    protected $signature = 'roster:generate-schedules
        {--years-ahead=2 : Batas tahun jadwal ke depan}
        {--limit=5000 : Maksimal karyawan aktif}
        {--dry-run : Hanya hitung kandidat karyawan}';

    protected $description = 'Membuat jadwal 10 minggu kerja dan 2 minggu off secara idempotent.';

    public function handle(RosterScheduleService $service): int
    {
        $yearsAhead = max(1, min(5, (int) $this->option('years-ahead')));
        $limit = max(1, min(10000, (int) $this->option('limit')));
        $until = Carbon::today()->addYears($yearsAhead)->endOfYear();
        $query = Employee::query()
            ->where('status_resign', 'AKTIF')
            ->where(function ($employeeQuery) {
                $employeeQuery->whereHas('rosterSchedules')
                    ->orWhere(function ($patternEmployee) {
                        $patternEmployee->whereNotNull('work_pattern_start_date')
                            ->whereHas('workPattern', function ($pattern) {
                                $pattern->where('work_duration_unit', 'week')
                                    ->where('off_duration_unit', 'week')
                                    ->where('work_duration_value', (int) config('roster.work_weeks', 10))
                                    ->where('off_duration_value', (int) config('roster.off_weeks', 2));
                            });
                    });
            });

        if ($this->option('dry-run')) {
            $candidateCount = (clone $query)
                ->orderBy('nik')
                ->limit($limit)
                ->pluck('nik')
                ->count();
            $this->info($candidateCount . ' karyawan aktif dapat dibuatkan jadwal hingga ' . $until->toDateString() . '.');
            return self::SUCCESS;
        }

        $employeeCount = 0;
        $scheduleCount = 0;
        $remaining = $limit;
        $lastNik = null;

        while ($remaining > 0) {
            $employees = (clone $query)
                ->when($lastNik !== null, fn ($employeeQuery) => $employeeQuery->where('nik', '>', $lastNik))
                ->orderBy('nik')
                ->limit(min(200, $remaining))
                ->get();

            if ($employees->isEmpty()) {
                break;
            }

            $employeeNiks = $employees->pluck('nik')->map('strval')->values()->all();
            $scheduleCount += DB::transaction(function () use ($service, $employees, $employeeNiks, $until): int {
                $generated = $service->generateUntilMany($employees, $until);
                $service->synchronizeSequences($employeeNiks);

                return $generated;
            });
            $employeeCount += $employees->count();
            $remaining -= $employees->count();
            $lastNik = (string) $employees->last()->nik;
        }

        $this->info($scheduleCount . ' jadwal diproses untuk ' . $employeeCount . ' karyawan.');

        return self::SUCCESS;
    }
}
