<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SafeExceptionLogger
{
    public function warning(string $action, Throwable $exception): string
    {
        $contextId = (string) Str::uuid();
        $safeAction = preg_replace('/[^a-z0-9_.:-]/i', '_', $action) ?: 'application.failure';

        Log::warning('Sensitive workflow failure.', [
            'action' => Str::limit($safeAction, 100, ''),
            'context_id' => $contextId,
            'exception_class' => get_class($exception),
        ]);

        return $contextId;
    }
}
