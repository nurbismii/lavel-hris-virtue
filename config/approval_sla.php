<?php

return [
    'enabled' => env('APPROVAL_SLA_ENABLED', true),

    'stages' => [
        'delegate' => [
            'label' => 'Delegasi',
            'hours' => env('APPROVAL_SLA_DELEGATE_HOURS', 12),
        ],
        'hod' => [
            'label' => 'HOD',
            'hours' => env('APPROVAL_SLA_HOD_HOURS', 24),
        ],
        'hrd' => [
            'label' => 'HR',
            'hours' => env('APPROVAL_SLA_HR_HOURS', 24),
        ],
    ],

    'warning_percent' => env('APPROVAL_SLA_WARNING_PERCENT', 80),
    'critical_multiplier' => env('APPROVAL_SLA_CRITICAL_MULTIPLIER', 2),
    'dashboard_limit' => env('APPROVAL_SLA_DASHBOARD_LIMIT', 500),
    'escalation_limit' => env('APPROVAL_SLA_ESCALATION_LIMIT', 500),
];
