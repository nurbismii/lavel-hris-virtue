<?php

namespace App\Services\Overtime;

use App\Models\OvertimeOrder;
use App\Models\OvertimePayRule;
use App\Models\WorkPattern;
use Illuminate\Support\Facades\Schema;

class OvertimePayCalculatorService
{
    public const MONTHLY_HOUR_DIVISOR = 173;

    public function calculate(string $scheduleType, string $dayType, int $overtimeMinutes, float $monthlyWage): array
    {
        $scheduleType = $this->normalizeScheduleType($scheduleType);
        $dayType = $this->normalizeDayType($scheduleType, $dayType);
        $overtimeMinutes = max(0, $overtimeMinutes);
        $monthlyWage = max(0, $monthlyWage);
        $hourlyWage = $monthlyWage / self::MONTHLY_HOUR_DIVISOR;
        $rules = $this->rulesFor($scheduleType, $dayType);

        $items = [];
        $multiplierUnits = 0.0;
        $coveredMinutes = 0;
        $warnings = [];

        foreach ($rules as $rule) {
            $hourFrom = (int) $rule['hour_from'];
            $hourTo = $rule['hour_to'] === null ? null : (int) $rule['hour_to'];
            $startMinute = max(0, ($hourFrom - 1) * 60);
            $endMinute = $hourTo === null ? $overtimeMinutes : $hourTo * 60;
            $minutes = max(0, min($overtimeMinutes, $endMinute) - $startMinute);

            if ($minutes <= 0) {
                continue;
            }

            $hours = $minutes / 60;
            $multiplier = (float) $rule['multiplier'];
            $lineMultiplierUnits = $hours * $multiplier;
            $lineAmount = $hourlyWage * $lineMultiplierUnits;
            $multiplierUnits += $lineMultiplierUnits;
            $coveredMinutes += $minutes;

            $items[] = [
                'name' => $rule['name'],
                'hour_from' => $hourFrom,
                'hour_to' => $hourTo,
                'hour_range_label' => $this->formatHourRange($hourFrom, $hourTo),
                'minutes' => $minutes,
                'hours' => round($hours, 4),
                'multiplier' => $multiplier,
                'multiplier_units' => round($lineMultiplierUnits, 4),
                'amount' => round($lineAmount),
                'legal_basis' => $rule['legal_basis'],
            ];
        }

        $overflowMinutes = max(0, $overtimeMinutes - $coveredMinutes);

        if ($overflowMinutes > 0) {
            $warnings[] = 'Durasi melebihi rentang master PP 35/2021 untuk kategori ini. Menit di luar rentang tidak dihitung otomatis.';
        }

        if ($dayType === OvertimePayRule::DAY_WORKDAY && $overtimeMinutes > 240) {
            $warnings[] = 'PP 35/2021 Pasal 26 membatasi lembur hari kerja paling lama 4 jam per hari dan 18 jam per minggu.';
        }

        if ($monthlyWage <= 0) {
            $warnings[] = 'Dasar upah belum diisi, nominal rupiah akan bernilai 0.';
        }

        return [
            'schedule_type' => $scheduleType,
            'schedule_type_label' => OvertimePayRule::scheduleTypeOptions()[$scheduleType] ?? $scheduleType,
            'day_type' => $dayType,
            'day_type_label' => OvertimePayRule::dayTypeOptions()[$dayType] ?? $dayType,
            'monthly_wage' => round($monthlyWage, 2),
            'hourly_wage' => round($hourlyWage, 4),
            'hour_divisor' => self::MONTHLY_HOUR_DIVISOR,
            'overtime_minutes' => $overtimeMinutes,
            'overtime_hours' => round($overtimeMinutes / 60, 4),
            'covered_minutes' => $coveredMinutes,
            'overflow_minutes' => $overflowMinutes,
            'multiplier_units' => round($multiplierUnits, 4),
            'amount' => round($hourlyWage * $multiplierUnits),
            'items' => $items,
            'warnings' => $warnings,
            'legal_basis' => [
                'PP 35/2021 Pasal 31',
                'PP 35/2021 Pasal 32 ayat (2): upah sejam = 1/173 x upah sebulan',
            ],
        ];
    }

    public function calculateForOrder(
        OvertimeOrder $order,
        float $monthlyWage,
        ?WorkPattern $workPattern = null,
        bool $isShortestWorkdayHoliday = false
    ): array {
        $scheduleType = $this->resolveScheduleTypeFromWorkPattern($workPattern ?: optional($order->employee)->workPattern);
        $dayType = $this->resolveDayTypeFromOvertimeType(
            (string) $order->overtime_type,
            $scheduleType,
            $isShortestWorkdayHoliday
        );

        return $this->calculate(
            $scheduleType,
            $dayType,
            (int) $order->required_minutes,
            $monthlyWage
        );
    }

    public function resolveScheduleTypeFromWorkPattern(?WorkPattern $workPattern): string
    {
        if (!$workPattern) {
            return OvertimePayRule::SCHEDULE_FIVE_TWO;
        }

        if ($workPattern->isWeeklyPattern()) {
            $workDays = count($workPattern->normalizeWeeklyWorkDays());

            if ($workDays >= 6) {
                return OvertimePayRule::SCHEDULE_SIX_ONE;
            }

            return OvertimePayRule::SCHEDULE_FIVE_TWO;
        }

        $workValue = (int) $workPattern->work_duration_value;
        $offValue = (int) $workPattern->off_duration_value;

        if ($workValue === 6 && $offValue === 1) {
            return OvertimePayRule::SCHEDULE_SIX_ONE;
        }

        return OvertimePayRule::SCHEDULE_FIVE_TWO;
    }

    public function resolveDayTypeFromOvertimeType(string $overtimeType, string $scheduleType, bool $isShortestWorkdayHoliday = false): string
    {
        if ($overtimeType === OvertimeOrder::TYPE_EXTRA_HOURS) {
            return OvertimePayRule::DAY_WORKDAY;
        }

        if ($scheduleType === OvertimePayRule::SCHEDULE_SIX_ONE && $isShortestWorkdayHoliday) {
            return OvertimePayRule::DAY_SHORTEST_WORKDAY_HOLIDAY;
        }

        return OvertimePayRule::DAY_OFF_OR_HOLIDAY;
    }

    public function rulesFor(string $scheduleType, string $dayType): array
    {
        $scheduleType = $this->normalizeScheduleType($scheduleType);
        $dayType = $this->normalizeDayType($scheduleType, $dayType);

        try {
            $hasRuleTable = Schema::hasTable('overtime_pay_rules');
        } catch (\Throwable $exception) {
            $hasRuleTable = false;
        }

        if ($hasRuleTable) {
            $rules = OvertimePayRule::query()
                ->active()
                ->where('day_type', $dayType)
                ->where(function ($query) use ($scheduleType) {
                    $query->where('schedule_type', $scheduleType)
                        ->orWhere('schedule_type', OvertimePayRule::SCHEDULE_ANY);
                })
                ->orderBy('sort_order')
                ->get()
                ->map(fn(OvertimePayRule $rule) => $this->ruleToArray($rule))
                ->all();

            if (!empty($rules)) {
                return $rules;
            }
        }

        return array_values(array_filter(
            $this->defaultRules(),
            fn(array $rule) => $rule['day_type'] === $dayType
                && in_array($rule['schedule_type'], [$scheduleType, OvertimePayRule::SCHEDULE_ANY], true)
        ));
    }

    public function defaultRules(): array
    {
        return [
            $this->rule('Hari kerja - jam pertama', OvertimePayRule::SCHEDULE_ANY, OvertimePayRule::DAY_WORKDAY, 1, 1, 1.5, 'PP 35/2021 Pasal 31 ayat (1) huruf a'),
            $this->rule('Hari kerja - jam berikutnya', OvertimePayRule::SCHEDULE_ANY, OvertimePayRule::DAY_WORKDAY, 2, null, 2, 'PP 35/2021 Pasal 31 ayat (1) huruf b'),
            $this->rule('6:1 off/libur resmi - jam 1 sampai 7', OvertimePayRule::SCHEDULE_SIX_ONE, OvertimePayRule::DAY_OFF_OR_HOLIDAY, 1, 7, 2, 'PP 35/2021 Pasal 31 ayat (2) huruf a angka 1'),
            $this->rule('6:1 off/libur resmi - jam ke-8', OvertimePayRule::SCHEDULE_SIX_ONE, OvertimePayRule::DAY_OFF_OR_HOLIDAY, 8, 8, 3, 'PP 35/2021 Pasal 31 ayat (2) huruf a angka 2'),
            $this->rule('6:1 off/libur resmi - jam 9 sampai 11', OvertimePayRule::SCHEDULE_SIX_ONE, OvertimePayRule::DAY_OFF_OR_HOLIDAY, 9, 11, 4, 'PP 35/2021 Pasal 31 ayat (2) huruf a angka 3'),
            $this->rule('6:1 libur resmi pada hari kerja terpendek - jam 1 sampai 5', OvertimePayRule::SCHEDULE_SIX_ONE, OvertimePayRule::DAY_SHORTEST_WORKDAY_HOLIDAY, 1, 5, 2, 'PP 35/2021 Pasal 31 ayat (2) huruf b angka 1'),
            $this->rule('6:1 libur resmi pada hari kerja terpendek - jam ke-6', OvertimePayRule::SCHEDULE_SIX_ONE, OvertimePayRule::DAY_SHORTEST_WORKDAY_HOLIDAY, 6, 6, 3, 'PP 35/2021 Pasal 31 ayat (2) huruf b angka 2'),
            $this->rule('6:1 libur resmi pada hari kerja terpendek - jam 7 sampai 9', OvertimePayRule::SCHEDULE_SIX_ONE, OvertimePayRule::DAY_SHORTEST_WORKDAY_HOLIDAY, 7, 9, 4, 'PP 35/2021 Pasal 31 ayat (2) huruf b angka 3'),
            $this->rule('5:2 off/libur resmi - jam 1 sampai 8', OvertimePayRule::SCHEDULE_FIVE_TWO, OvertimePayRule::DAY_OFF_OR_HOLIDAY, 1, 8, 2, 'PP 35/2021 Pasal 31 ayat (3) huruf a'),
            $this->rule('5:2 off/libur resmi - jam ke-9', OvertimePayRule::SCHEDULE_FIVE_TWO, OvertimePayRule::DAY_OFF_OR_HOLIDAY, 9, 9, 3, 'PP 35/2021 Pasal 31 ayat (3) huruf b'),
            $this->rule('5:2 off/libur resmi - jam 10 sampai 12', OvertimePayRule::SCHEDULE_FIVE_TWO, OvertimePayRule::DAY_OFF_OR_HOLIDAY, 10, 12, 4, 'PP 35/2021 Pasal 31 ayat (3) huruf c'),
        ];
    }

    private function normalizeScheduleType(string $scheduleType): string
    {
        return in_array($scheduleType, array_keys(OvertimePayRule::scheduleTypeOptions()), true)
            ? $scheduleType
            : OvertimePayRule::SCHEDULE_FIVE_TWO;
    }

    private function normalizeDayType(string $scheduleType, string $dayType): string
    {
        if ($dayType === OvertimePayRule::DAY_SHORTEST_WORKDAY_HOLIDAY && $scheduleType !== OvertimePayRule::SCHEDULE_SIX_ONE) {
            return OvertimePayRule::DAY_OFF_OR_HOLIDAY;
        }

        return array_key_exists($dayType, OvertimePayRule::dayTypeOptions())
            ? $dayType
            : OvertimePayRule::DAY_WORKDAY;
    }

    private function ruleToArray(OvertimePayRule $rule): array
    {
        return [
            'name' => $rule->name,
            'schedule_type' => $rule->schedule_type,
            'day_type' => $rule->day_type,
            'hour_from' => (int) $rule->hour_from,
            'hour_to' => $rule->hour_to === null ? null : (int) $rule->hour_to,
            'multiplier' => (float) $rule->multiplier,
            'legal_basis' => $rule->legal_basis,
        ];
    }

    private function rule(string $name, string $scheduleType, string $dayType, int $hourFrom, ?int $hourTo, float $multiplier, string $legalBasis): array
    {
        return [
            'name' => $name,
            'schedule_type' => $scheduleType,
            'day_type' => $dayType,
            'hour_from' => $hourFrom,
            'hour_to' => $hourTo,
            'multiplier' => $multiplier,
            'legal_basis' => $legalBasis,
        ];
    }

    private function formatHourRange(int $hourFrom, ?int $hourTo): string
    {
        if ($hourTo === null) {
            return 'Mulai jam ke-' . $hourFrom;
        }

        if ($hourFrom === $hourTo) {
            return 'Jam ke-' . $hourFrom;
        }

        return 'Jam ' . $hourFrom . '-' . $hourTo;
    }
}
