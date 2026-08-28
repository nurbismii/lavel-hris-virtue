<?php

return [
    'work_weeks' => (int) env('ROSTER_WORK_WEEKS', 10),
    'off_weeks' => (int) env('ROSTER_OFF_WEEKS', 2),
    'earned_off_days' => (int) env('ROSTER_EARNED_OFF_DAYS', 5),
    'reminder_days' => (int) env('ROSTER_REMINDER_DAYS', 14),
    'reminder_delay_seconds' => (int) env('ROSTER_REMINDER_DELAY_SECONDS', 2),
    'overdue_reminder_cooldown_hours' => (int) env('ROSTER_OVERDUE_REMINDER_COOLDOWN_HOURS', 24),
    'generate_years_ahead' => (int) env('ROSTER_GENERATE_YEARS_AHEAD', 2),
    'import' => [
        'max_kb' => (int) env('ROSTER_IMPORT_MAX_KB', 10240),
        'retention_hours' => (int) env('ROSTER_IMPORT_RETENTION_HOURS', 12),
        'directory' => 'roster-imports',
    ],
];
