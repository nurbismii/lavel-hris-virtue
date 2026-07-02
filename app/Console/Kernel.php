<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('update.resign:cron')->everyMinute();
        $schedule->command('vhire:retry-failed-syncs --limit=50')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('contracts:notify-renewal-due --days=30 --limit=200')->dailyAt('07:00')->withoutOverlapping();
        $schedule->command('contracts:notify-signature-reminders --days=14,7,3 --limit=500')->dailyAt('07:20')->withoutOverlapping();
        $schedule->command('roster:notify-cycle-plan-reminders --days=3 --limit=1000')->dailyAt('07:35')->withoutOverlapping();
        $schedule->command('contracts:sync-terminated-employees --limit=500')->dailyAt('00:10')->withoutOverlapping();
        $schedule->command('approvals:escalate-sla --limit=500')->hourly()->withoutOverlapping();
        $schedule->command('employee-movements:apply-due --limit=500')->dailyAt('00:20')->withoutOverlapping();
        $schedule->command('cv-maker:sync-progress --limit=500 --chunk=100')->hourly()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
