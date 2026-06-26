<?php

namespace App\Services\Presensi;

use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\EmployeeAttendanceSetting;
use App\Models\NationalHoliday;
use App\Models\OvertimePayRule;
use App\Models\Presensi;
use App\Models\Roster;
use App\Models\RosterOffRequest;
use App\Models\User;
use App\Services\Overtime\OvertimePayCalculatorService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceHrSummaryExportService
{
    private const ATTENDANCE_COMPANY_CODES = ['VDNI', 'VDNIP'];
    private const FIRST_DATA_ROW = 9;
    private const SMELTER_HOLIDAY_UNITS = 16;

    private WorkScheduleService $workScheduleService;
    private OvertimeOrderService $overtimeOrderService;
    private OvertimePayCalculatorService $overtimeCalculator;

    public function __construct(
        WorkScheduleService $workScheduleService,
        OvertimeOrderService $overtimeOrderService,
        OvertimePayCalculatorService $overtimeCalculator
    ) {
        $this->workScheduleService = $workScheduleService;
        $this->overtimeOrderService = $overtimeOrderService;
        $this->overtimeCalculator = $overtimeCalculator;
    }

    public function download(User $user, array $filters): StreamedResponse
    {
        [$start, $end] = $this->generateCutoff($filters['cutoff_month']);
        $filename = sprintf(
            'Presensi_HR_%s_sd_%s_%s.xlsx',
            $start->format('Ymd'),
            $end->format('Ymd'),
            now()->format('His')
        );

        return response()->streamDownload(function () use ($user, $filters, $start, $end) {
            $spreadsheet = $this->buildWorkbook($user, $filters, $start, $end);
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function buildWorkbook(User $user, array $filters, Carbon $start, Carbon $end): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator(config('app.name', 'HRIS'))
            ->setTitle('Export Presensi HR');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sheet1');
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $this->buildHeader($sheet, $start, $end);

        $rowNumber = self::FIRST_DATA_ROW;
        $employeeQuery = $this->employeeQuery($user, $filters);
        $holidayDates = $this->holidayDates($start, $end);

        $employeeQuery
            ->with([
                'departemen:id,departemen',
                'divisi:id,nama_divisi',
                'workPattern',
            ])
            ->orderBy('nik')
            ->chunk(300, function (Collection $employees) use (&$rowNumber, $sheet, $start, $end, $holidayDates) {
                $this->writeEmployeeRows($sheet, $employees, $start, $end, $holidayDates, $rowNumber);
            });

        $lastDataRow = max(self::FIRST_DATA_ROW, $rowNumber - 1);
        $this->styleDataRows($sheet, self::FIRST_DATA_ROW, $lastDataRow);
        $this->addClosingBlackRow($sheet, $rowNumber);

        return $spreadsheet;
    }

    private function writeEmployeeRows(
        Worksheet $sheet,
        Collection $employees,
        Carbon $start,
        Carbon $end,
        array $holidayDates,
        int &$rowNumber
    ): void {
        $niks = $employees->pluck('nik')->filter()->values();

        if ($niks->isEmpty()) {
            return;
        }

        $manualOverrides = EmployeeAttendanceSetting::query()
            ->whereIn('employee_id', $niks)
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->get();
        $scheduleMap = $this->workScheduleService->buildScheduleMap($employees, $manualOverrides, $start, $end);
        $approvedRosterOffDateMap = $this->approvedRosterOffDateMap($niks, $start, $end);
        $approvedCutiRosterDateMap = $this->approvedCutiRosterDateMap($niks, $start, $end);
        $attendanceRows = $this->attendanceRows($niks, $start, $end);
        $employeeByNik = $employees->keyBy('nik');
        $presensiMap = [];
        $actualPresensiMap = [];

        foreach ($attendanceRows as $attendance) {
            $date = Carbon::parse($attendance->tanggal)->toDateString();
            $employee = $employeeByNik->get((string) $attendance->nik_karyawan)
                ?: $employeeByNik->get($attendance->nik_karyawan);
            $statusPresensi = $this->normalizeStatusPresensiForSchedule(
                $attendance->status_presensi,
                $employee,
                $date,
                $scheduleMap,
                $approvedRosterOffDateMap,
                $approvedCutiRosterDateMap
            );

            $payload = [
                'status_label' => $statusPresensi,
                'status' => $statusPresensi ? Presensi::shortStatus($statusPresensi) : null,
                'has_clock' => $this->hasClock($attendance),
                'gross_minutes' => $this->grossAttendanceMinutes($attendance, $date),
                'partial_permission_type' => $attendance->partial_permission_type ?? null,
            ];

            $presensiMap[$attendance->nik_karyawan][$date] = $payload;
            $actualPresensiMap[$attendance->nik_karyawan][$date] = $payload;
        }

        foreach ($this->workScheduleService->buildOffStatusMap($employees, $start, $end, $presensiMap, $scheduleMap) as $nik => $dates) {
            foreach ($dates as $date => $payload) {
                $presensiMap[$nik][$date] = [
                    'status_label' => $this->expandShortStatus($payload['status'] ?? null),
                    'status' => $payload['status'] ?? null,
                    'has_clock' => false,
                    'gross_minutes' => 0,
                    'partial_permission_type' => null,
                ];
            }
        }

        foreach ($this->overtimeOrderService->buildAcceptedAlphaMap($niks, $start, $end, $actualPresensiMap) as $nik => $dates) {
            foreach ($dates as $date => $payload) {
                $presensiMap[$nik][$date] = [
                    'status_label' => $this->expandShortStatus($payload['status'] ?? null),
                    'status' => $payload['status'] ?? null,
                    'has_clock' => false,
                    'gross_minutes' => 0,
                    'partial_permission_type' => null,
                ];
            }
        }

        foreach ($employees as $employee) {
            $summary = $this->summarizeEmployeeAttendance(
                $employee,
                $start,
                $end,
                $holidayDates,
                $scheduleMap[$employee->nik] ?? [],
                $presensiMap[$employee->nik] ?? [],
                $actualPresensiMap[$employee->nik] ?? []
            );

            $this->writeEmployeeRow($sheet, $rowNumber, $employee, $summary);
            $rowNumber++;
        }
    }

    private function summarizeEmployeeAttendance(
        Employee $employee,
        Carbon $start,
        Carbon $end,
        array $holidayDates,
        array $employeeScheduleMap,
        array $employeePresensiMap,
        array $actualPresensiMap
    ): array {
        $summary = [
            'alpa' => 0.0,
            'izin_paid' => 0.0,
            'izin_unpaid' => 0.0,
            'sakit' => 0.0,
            'off' => 0.0,
            'cuti' => 0.0,
            'libur' => 0.0,
            'work_days' => 0.0,
            'overtime_off' => 0.0,
            'overtime_holiday' => 0.0,
        ];
        $scheduleType = $this->overtimeCalculator->resolveScheduleTypeFromWorkPattern($employee->workPattern);
        $isSmelterFiveTwo = $scheduleType === OvertimePayRule::SCHEDULE_FIVE_TWO
            && $this->isSmelterEmployee($employee);

        if ($isSmelterFiveTwo) {
            $summary['overtime_holiday'] = count($this->effectiveHolidayDatesForEmployee($employee, $holidayDates)) * self::SMELTER_HOLIDAY_UNITS;
        }

        foreach (CarbonPeriod::create($start, $end) as $date) {
            $dateString = $date->toDateString();

            if (!$this->employeeHasStarted($employee, $date)) {
                continue;
            }

            $record = $employeePresensiMap[$dateString] ?? null;
            $actual = $actualPresensiMap[$dateString] ?? null;
            $status = $this->statusLabelFromRecord($record);

            if ($status) {
                $this->incrementStatusSummary($summary, $status, $record);
            } elseif ($actual && !empty($actual['has_clock'])) {
                $summary['work_days']++;
            } elseif ($this->isPastScheduledWorkday($date, $employeeScheduleMap[$dateString] ?? null)) {
                $summary['alpa']++;
            }

            if (!$actual || empty($actual['gross_minutes'])) {
                continue;
            }

            if (isset($holidayDates[$dateString])) {
                if (!$isSmelterFiveTwo) {
                    $summary['overtime_holiday'] += $this->calculateOffOrHolidayOvertimeUnits(
                        $scheduleType,
                        (int) $actual['gross_minutes']
                    );
                }

                continue;
            }

            if (($employeeScheduleMap[$dateString]['final_status'] ?? null) === WorkScheduleService::STATUS_OFF) {
                $summary['overtime_off'] += $this->calculateOffOrHolidayOvertimeUnits(
                    $scheduleType,
                    (int) $actual['gross_minutes']
                );
            }
        }

        return $summary;
    }

    private function writeEmployeeRow(Worksheet $sheet, int $row, Employee $employee, array $summary): void
    {
        $sheet->setCellValue("A{$row}", $row - self::FIRST_DATA_ROW);
        $sheet->setCellValueExplicit("B{$row}", (string) $employee->nik, DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("C{$row}", (string) ($employee->no_ktp ?? ''), DataType::TYPE_STRING);
        $sheet->setCellValue("D{$row}", $employee->nama_karyawan);
        $sheet->setCellValue("E{$row}", $this->formatGender($employee->jenis_kelamin ?? null));
        $sheet->setCellValue("F{$row}", optional($employee->departemen)->departemen);
        $sheet->setCellValue("G{$row}", optional($employee->divisi)->nama_divisi);
        $sheet->setCellValue("H{$row}", $employee->posisi ?: ($employee->jabatan ?? null));
        $this->setExcelDate($sheet, "I{$row}", $employee->entry_date ?? null);
        $this->setExcelDate($sheet, "J{$row}", $employee->tgl_lahir ?? null);

        $sheet->setCellValue("K{$row}", $this->blankZero($summary['alpa']));
        $sheet->setCellValue("L{$row}", $this->blankZero($summary['izin_paid']));
        $sheet->setCellValue("M{$row}", $this->blankZero($summary['izin_unpaid']));
        $sheet->setCellValue("N{$row}", $this->blankZero($summary['sakit']));
        $sheet->setCellValue("O{$row}", $this->blankZero($summary['off']));
        $sheet->setCellValue("P{$row}", $this->blankZero($summary['cuti']));
        $sheet->setCellValue("Q{$row}", $this->blankZero($summary['libur']));
        $sheet->setCellValue("R{$row}", $this->blankZero($summary['work_days']));
        $sheet->setCellValue("S{$row}", $this->blankZero($this->normalizeNumber($summary['overtime_off'])));
        $sheet->setCellValue("T{$row}", $this->blankZero($this->normalizeNumber($summary['overtime_holiday'])));
        $sheet->setCellValue("U{$row}", "=SUM(K{$row}:R{$row})");
    }

    private function buildHeader(Worksheet $sheet, Carbon $start, Carbon $end): void
    {
        $sheet->setCellValue('A1', 'ABSENSI INPUT');
        $sheet->setCellValue('A3', 'Period');
        $this->setExcelDate($sheet, 'B3', $start);
        $this->setExcelDate($sheet, 'C3', $end);

        $headers = [
            'A5' => "NO \n序号",
            'B5' => "NIK \n工号",
            'C5' => "No KTP \n身份证号",
            'D5' => "EMPLOYEE NAME \n员工姓名",
            'E5' => "SEX \n性别",
            'F5' => "DEPARTMENT \n部门",
            'G5' => "DIVISI \n科室",
            'H5' => "POSITION \n岗位",
            'I5' => "ENTRY DATE \n入厂日期",
            'J5' => "DATE OF BIRTH \n出生日期",
            'K6' => 'ABSENSI 考勤合计',
            'K7' => 'TOTAL ALPA 旷工',
            'L7' => 'TOTAL IJIN 事假',
            'L8' => "PAID LEAVE\n带薪休假",
            'M8' => "UNPAID LEAVE\n无薪休假",
            'N7' => 'TOTAL SAKIT  病假',
            'O7' => 'TOTAL OFF 休息',
            'P7' => 'TOTAL CUTI 年假',
            'Q7' => "TOTAL LIBUR \n公休日",
            'R7' => 'TOTAL WORK DAYS 出勤',
            'S5' => "OVER TIME (OFF) \n休息加班",
            'T5' => "OVER TIME (TANGGAL MERAH) \n红日子加班",
            'U5' => 'TOTAL ABSENSI',
            'V5' => "REMARK \n备注",
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'S', 'T', 'U', 'V'] as $column) {
            $sheet->mergeCells("{$column}5:{$column}8");
        }

        $sheet->mergeCells('K6:R6');
        $sheet->mergeCells('L7:M7');

        foreach (['K', 'N', 'O', 'P', 'Q', 'R'] as $column) {
            $sheet->mergeCells("{$column}7:{$column}8");
        }

        $this->styleHeader($sheet);
    }

    private function styleHeader(Worksheet $sheet): void
    {
        $widths = [
            'A' => 13.45,
            'B' => 10.27,
            'C' => 16.91,
            'D' => 25.73,
            'E' => 5.36,
            'F' => 21.45,
            'G' => 13.00,
            'H' => 58.09,
            'I' => 9.55,
            'J' => 9.64,
            'K' => 8.64,
            'L' => 8.00,
            'M' => 8.64,
            'N' => 8.82,
            'O' => 9.45,
            'P' => 8.36,
            'Q' => 7.36,
            'R' => 9.18,
            'S' => 11.45,
            'T' => 11.00,
            'U' => 8.82,
            'V' => 18.45,
        ];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getRowDimension(4)->setRowHeight(7);
        $sheet->getRowDimension(7)->setRowHeight(22);
        $sheet->getRowDimension(8)->setRowHeight(47);
        $sheet->freezePane('A9');

        $sheet->getStyle('A1')->getFont()->setBold(true);
        $sheet->getStyle('A3:C3')->getFont()->setBold(true);
        $sheet->getStyle('A3:C3')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('B3:C3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFF00');
        $sheet->getStyle('B3')->getNumberFormat()->setFormatCode('dd - mmm "s.d"');
        $sheet->getStyle('C3')->getNumberFormat()->setFormatCode('d - mmm - yyyy');

        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '000000'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'B4C6E7'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ];

        $sheet->getStyle('A5:V8')->applyFromArray($headerStyle);
        $sheet->getStyle('T5:T8')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FF0000');
    }

    private function styleDataRows(Worksheet $sheet, int $firstRow, int $lastRow): void
    {
        if ($lastRow < $firstRow) {
            return;
        }

        $range = "A{$firstRow}:V{$lastRow}";
        $sheet->getStyle($range)->applyFromArray([
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D9D9D9'],
                ],
            ],
        ]);
        $sheet->getStyle("A{$firstRow}:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("E{$firstRow}:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("I{$firstRow}:V{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D{$firstRow}:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("H{$firstRow}:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("B{$firstRow}:C{$lastRow}")->getNumberFormat()->setFormatCode('@');
        $sheet->getStyle("I{$firstRow}:J{$lastRow}")->getNumberFormat()->setFormatCode('d-mmm-yy');
        $sheet->getStyle("K{$firstRow}:U{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.##');
    }

    private function addClosingBlackRow(Worksheet $sheet, int $row): void
    {
        $sheet->getRowDimension($row)->setRowHeight(15.25);
        $sheet->getStyle("A{$row}:V{$row}")
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setRGB('000000');
    }

    private function employeeQuery(User $user, array $filters)
    {
        $query = Employee::query()
            ->select($this->employeeSelectColumns())
            ->where('status_resign', 'AKTIF')
            ->whereIn('area_kerja', self::ATTENDANCE_COMPANY_CODES)
            ->where('departemen_id', $filters['departemen']);

        $user->applyEmployeeScope($query);

        if (!empty($filters['divisi'])) {
            $query->where('divisi_id', $filters['divisi']);
        }

        return $query;
    }

    private function employeeSelectColumns(): array
    {
        $required = [
            'nik',
            'nama_karyawan',
            'departemen_id',
            'divisi_id',
            'area_kerja',
            'status_resign',
        ];
        $optional = [
            'work_pattern_id',
            'work_pattern_start_date',
            'no_ktp',
            'jenis_kelamin',
            'entry_date',
            'tgl_lahir',
            'posisi',
            'jabatan',
        ];

        return collect($required)
            ->merge(collect($optional)->map(function (string $column) {
                return Schema::hasColumn('employees', $column)
                    ? $column
                    : DB::raw('NULL as ' . $column);
            }))
            ->all();
    }

    private function attendanceRows(Collection $niks, Carbon $start, Carbon $end): Collection
    {
        $select = [
            'id',
            'nik_karyawan',
            'tanggal',
            'jam_masuk',
            'jam_istirahat',
            'jam_kembali_istirahat',
            'jam_pulang',
            'status_presensi',
        ];

        $select[] = Schema::hasColumn('absensis', 'partial_permission_type')
            ? 'partial_permission_type'
            : DB::raw('NULL as partial_permission_type');

        return DB::table('absensis')
            ->select($select)
            ->whereIn('nik_karyawan', $niks)
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->get();
    }

    private function holidayDates(Carbon $start, Carbon $end): array
    {
        if (!Schema::hasTable((new NationalHoliday())->getTable())) {
            return [];
        }

        return NationalHoliday::query()
            ->whereBetween('holiday_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn(NationalHoliday $holiday) => $holiday->holiday_date->toDateString())
            ->all();
    }

    private function generateCutoff(string $month): array
    {
        $start = Carbon::parse($month)->subMonth()->startOfMonth()->addDays(15);
        $end = Carbon::parse($month)->startOfMonth()->addDays(14);

        return [$start, $end];
    }

    private function approvedRosterOffDateMap($niks, Carbon $start, Carbon $end): array
    {
        $map = [];

        RosterOffRequest::query()
            ->effectiveForAttendance()
            ->whereIn('nik_karyawan', $niks)
            ->whereBetween('tanggal_off', [$start->toDateString(), $end->toDateString()])
            ->get(['nik_karyawan', 'tanggal_off'])
            ->each(function (RosterOffRequest $offRequest) use (&$map) {
                $map[$offRequest->nik_karyawan][$offRequest->tanggal_off->toDateString()] = true;
            });

        Roster::query()
            ->join('periode_kerja_roster', 'periode_kerja_roster.cuti_roster_id', '=', 'cuti_roster.id')
            ->whereIn('cuti_roster.nik_karyawan', $niks)
            ->where('cuti_roster.status_pengajuan', 1)
            ->where('cuti_roster.status_pengajuan_hrd', 1)
            ->where('periode_kerja_roster.tipe_rencana', 1)
            ->whereNotNull('cuti_roster.tgl_mulai_off')
            ->whereNotNull('cuti_roster.tgl_mulai_off_berakhir')
            ->whereDate('cuti_roster.tgl_mulai_off', '<=', $end->toDateString())
            ->whereDate('cuti_roster.tgl_mulai_off_berakhir', '>=', $start->toDateString())
            ->get([
                'cuti_roster.nik_karyawan',
                'cuti_roster.tgl_mulai_off',
                'cuti_roster.tgl_mulai_off_berakhir',
            ])
            ->each(function ($roster) use (&$map, $start, $end) {
                $rangeStart = Carbon::parse($roster->tgl_mulai_off)->startOfDay()->max($start->copy());
                $rangeEnd = Carbon::parse($roster->tgl_mulai_off_berakhir)->startOfDay()->min($end->copy());

                foreach (CarbonPeriod::create($rangeStart, $rangeEnd) as $date) {
                    $map[$roster->nik_karyawan][$date->toDateString()] = true;
                }
            });

        return $map;
    }

    private function approvedCutiRosterDateMap($niks, Carbon $start, Carbon $end): array
    {
        $map = [];

        Roster::query()
            ->join('periode_kerja_roster', 'periode_kerja_roster.cuti_roster_id', '=', 'cuti_roster.id')
            ->whereIn('cuti_roster.nik_karyawan', $niks)
            ->where('cuti_roster.status_pengajuan', 1)
            ->where('cuti_roster.status_pengajuan_hrd', 1)
            ->where('periode_kerja_roster.tipe_rencana', 1)
            ->whereNotNull('cuti_roster.tgl_mulai_cuti')
            ->whereNotNull('cuti_roster.tgl_mulai_cuti_berakhir')
            ->whereDate('cuti_roster.tgl_mulai_cuti', '<=', $end->toDateString())
            ->whereDate('cuti_roster.tgl_mulai_cuti_berakhir', '>=', $start->toDateString())
            ->get([
                'cuti_roster.nik_karyawan',
                'cuti_roster.tgl_mulai_cuti',
                'cuti_roster.tgl_mulai_cuti_berakhir',
            ])
            ->each(function ($roster) use (&$map, $start, $end) {
                $rangeStart = Carbon::parse($roster->tgl_mulai_cuti)->startOfDay()->max($start->copy());
                $rangeEnd = Carbon::parse($roster->tgl_mulai_cuti_berakhir)->startOfDay()->min($end->copy());

                foreach (CarbonPeriod::create($rangeStart, $rangeEnd) as $date) {
                    $map[$roster->nik_karyawan][$date->toDateString()] = true;
                }
            });

        return $map;
    }

    private function normalizeStatusPresensiForSchedule(
        ?string $statusPresensi,
        ?Employee $employee,
        string $tanggal,
        array $scheduleMap,
        array $approvedRosterOffDateMap,
        array $approvedCutiRosterDateMap
    ): ?string {
        if ($statusPresensi !== AttendanceStatusService::STATUS_OFF || !$employee) {
            return $statusPresensi;
        }

        if (!app(RosterCyclePlanService::class)->isDateInRosterOffSegment($employee, $tanggal)) {
            return $statusPresensi;
        }

        if (!empty($approvedCutiRosterDateMap[$employee->nik][$tanggal])) {
            return AttendanceStatusService::STATUS_CUTI_ROSTER;
        }

        if (!empty($approvedRosterOffDateMap[$employee->nik][$tanggal])) {
            return $statusPresensi;
        }

        $scheduleStatus = $scheduleMap[$employee->nik][$tanggal]['final_status'] ?? null;

        if ($scheduleStatus === WorkScheduleService::STATUS_OFF) {
            return $statusPresensi;
        }

        return null;
    }

    private function incrementStatusSummary(array &$summary, string $status, ?array $record): void
    {
        if (($record['partial_permission_type'] ?? null) === AttendanceCorrection::PARTIAL_SICK) {
            $summary['sakit']++;
            return;
        }

        switch ($status) {
            case 'Alpa':
                $summary['alpa']++;
                break;
            case AttendanceStatusService::STATUS_IZIN_BERBAYAR:
                $summary['izin_paid']++;
                break;
            case AttendanceStatusService::STATUS_IZIN_TIDAK_BERBAYAR:
                $summary['izin_unpaid']++;
                break;
            case AttendanceStatusService::STATUS_OFF:
                $summary['off']++;
                break;
            case AttendanceStatusService::STATUS_CUTI_TAHUNAN:
            case AttendanceStatusService::STATUS_CUTI_ROSTER:
                $summary['cuti']++;
                break;
            case AttendanceStatusService::STATUS_LIBUR_NASIONAL:
                $summary['libur']++;
                break;
            default:
                if (stripos($status, 'sakit') !== false) {
                    $summary['sakit']++;
                } elseif ($record && !empty($record['has_clock'])) {
                    $summary['work_days']++;
                }
                break;
        }
    }

    private function statusLabelFromRecord(?array $record): ?string
    {
        if (!$record) {
            return null;
        }

        if (($record['partial_permission_type'] ?? null) === AttendanceCorrection::PARTIAL_SICK) {
            return 'Sakit';
        }

        return $record['status_label']
            ?: $this->expandShortStatus($record['status'] ?? null);
    }

    private function expandShortStatus(?string $status): ?string
    {
        switch ($status) {
            case 'A':
                return 'Alpa';
            case 'L':
                return AttendanceStatusService::STATUS_LIBUR_NASIONAL;
            case 'OFF':
                return AttendanceStatusService::STATUS_OFF;
            case 'I/P':
                return AttendanceStatusService::STATUS_IZIN_BERBAYAR;
            case 'I/U':
                return AttendanceStatusService::STATUS_IZIN_TIDAK_BERBAYAR;
            case 'CT':
                return AttendanceStatusService::STATUS_CUTI_TAHUNAN;
            case 'CR':
                return AttendanceStatusService::STATUS_CUTI_ROSTER;
            default:
                return $status;
        }
    }

    private function calculateOffOrHolidayOvertimeUnits(string $scheduleType, int $minutes): float
    {
        if ($minutes <= 0) {
            return 0.0;
        }

        $result = $this->overtimeCalculator->calculate(
            $scheduleType,
            OvertimePayRule::DAY_OFF_OR_HOLIDAY,
            $minutes,
            0
        );

        return (float) ($result['multiplier_units'] ?? 0);
    }

    private function grossAttendanceMinutes($attendance, string $date): int
    {
        if (empty($attendance->jam_masuk) || empty($attendance->jam_pulang)) {
            return 0;
        }

        $start = $this->parseAttendanceDateTime($date, $attendance->jam_masuk);
        $end = $this->parseAttendanceDateTime($date, $attendance->jam_pulang);

        if (!$start || !$end) {
            return 0;
        }

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return $start->diffInMinutes($end);
    }

    private function parseAttendanceDateTime(string $date, $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->copy();
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return Carbon::parse($value);
        }

        return Carbon::parse($date . ' ' . $value);
    }

    private function hasClock($attendance): bool
    {
        return filled($attendance->jam_masuk)
            || filled($attendance->jam_istirahat)
            || filled($attendance->jam_kembali_istirahat)
            || filled($attendance->jam_pulang);
    }

    private function isPastScheduledWorkday(Carbon $date, ?array $schedule): bool
    {
        return $date->lt(Carbon::today())
            && (($schedule['final_status'] ?? WorkScheduleService::STATUS_HADIR) === WorkScheduleService::STATUS_HADIR);
    }

    private function isSmelterEmployee(Employee $employee): bool
    {
        $text = strtoupper(trim(
            (string) optional($employee->departemen)->departemen . ' ' .
            (string) optional($employee->divisi)->nama_divisi
        ));

        return strpos($text, 'SMELTER') !== false;
    }

    private function effectiveHolidayDatesForEmployee(Employee $employee, array $holidayDates): array
    {
        return collect(array_keys($holidayDates))
            ->filter(fn(string $date) => $this->employeeHasStarted($employee, Carbon::parse($date)))
            ->values()
            ->all();
    }

    private function employeeHasStarted(Employee $employee, Carbon $date): bool
    {
        if (!$employee->entry_date) {
            return true;
        }

        return $date->gte(Carbon::parse($employee->entry_date)->startOfDay());
    }

    private function formatGender(?string $value): ?string
    {
        $gender = strtoupper(trim((string) $value));

        if (in_array($gender, ['L', 'M', 'LAKI-LAKI'], true)) {
            return 'M 男';
        }

        if (in_array($gender, ['P', 'F', 'PEREMPUAN'], true)) {
            return 'F 女';
        }

        return $value ?: null;
    }

    private function setExcelDate(Worksheet $sheet, string $cell, $value): void
    {
        if (!$value) {
            return;
        }

        $date = $value instanceof Carbon
            ? $value
            : Carbon::parse($value);

        $sheet->setCellValue($cell, ExcelDate::PHPToExcel($date));
    }

    private function blankZero($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value) && abs((float) $value) < 0.0001) {
            return null;
        }

        return $value;
    }

    private function normalizeNumber(float $value)
    {
        $rounded = round($value, 2);

        if (abs($rounded - round($rounded)) < 0.0001) {
            return (int) round($rounded);
        }

        return $rounded;
    }
}
