<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\RosterSchedule;
use App\Models\User;
use App\Notifications\RosterScheduleReminderNotification;
use Carbon\Carbon;
use Tests\TestCase;

class RosterScheduleReminderNotificationTest extends TestCase
{
    public function test_reminder_uses_email_and_database_channels_with_period_context(): void
    {
        $schedule = new RosterSchedule([
            'id' => 10,
            'employee_nik' => 'EMP001',
            'period_year' => 2026,
            'period_number' => 3,
            'work_start' => Carbon::parse('2026-04-01'),
            'work_end' => Carbon::parse('2026-06-09'),
            'off_start' => Carbon::parse('2026-06-10'),
            'off_end' => Carbon::parse('2026-06-23'),
        ]);
        $schedule->setRelation('employee', new Employee(['nama_karyawan' => 'Budi']));
        $user = new User(['name' => 'Budi', 'email' => 'budi@example.test']);
        $notification = new RosterScheduleReminderNotification($schedule);
        $expectedUrl = route('roster.create', ['roster_schedule' => $schedule->id]);

        $this->assertSame(['mail', 'database'], $notification->via($user));
        $mail = $notification->toMail($user);
        $this->assertSame('Reminder Jadwal Roster H-14', $mail->subject);
        $this->assertSame($expectedUrl, $mail->actionUrl);
        $this->assertStringContainsString('2026 / III', $notification->toArray($user)['pesan']);
        $this->assertSame('2026-06-10', $notification->toArray($user)['metadata']['off_start']);
        $this->assertSame($expectedUrl, $notification->toArray($user)['url']);
    }
}
