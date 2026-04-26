<?php

namespace Tests\Unit;

use App\Models\Presensi;
use App\Models\WorkPattern;
use App\Services\Presensi\AttendanceFulfillmentService;
use Tests\TestCase;

class AttendanceFulfillmentServiceTest extends TestCase
{
    public function test_late_break_start_and_late_checkout_do_not_add_counted_work_minutes(): void
    {
        $result = $this->evaluate([
            'jam_masuk' => '2026-04-26 08:00:00',
            'jam_istirahat' => '2026-04-26 11:10:00',
            'jam_kembali_istirahat' => '2026-04-26 12:00:00',
            'jam_pulang' => '2026-04-26 17:10:00',
        ]);

        $this->assertSame(480, $result['actual_minutes']);
        $this->assertTrue($result['is_complete']);
    }

    public function test_late_checkin_and_late_break_return_within_tolerance_do_not_reduce_counted_work_minutes(): void
    {
        $result = $this->evaluate([
            'jam_masuk' => '2026-04-26 08:10:00',
            'jam_istirahat' => '2026-04-26 11:00:00',
            'jam_kembali_istirahat' => '2026-04-26 12:10:00',
            'jam_pulang' => '2026-04-26 17:00:00',
        ]);

        $this->assertSame(480, $result['actual_minutes']);
        $this->assertTrue($result['is_complete']);
    }

    public function test_late_break_return_beyond_tolerance_reduces_counted_work_minutes(): void
    {
        $result = $this->evaluate([
            'jam_masuk' => '2026-04-26 08:00:00',
            'jam_istirahat' => '2026-04-26 11:00:00',
            'jam_kembali_istirahat' => '2026-04-26 12:11:00',
            'jam_pulang' => '2026-04-26 17:10:00',
        ]);

        $this->assertSame(469, $result['actual_minutes']);
        $this->assertSame(11, $result['shortage_minutes']);
        $this->assertSame('Kurang 11 menit', $result['label']);
    }

    private function evaluate(array $attendanceTimes): array
    {
        $service = new AttendanceFulfillmentService();
        $presensi = new Presensi(array_merge([
            'tanggal' => '2026-04-26',
        ], $attendanceTimes));
        $pattern = new WorkPattern([
            'start_time' => '08:00:00',
            'break_start_time' => '11:00:00',
            'break_end_time' => '12:00:00',
            'end_time' => '17:00:00',
        ]);

        return $service->evaluate($presensi, $pattern, '2026-04-26');
    }
}
