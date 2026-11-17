<?php

namespace App\Services\Severance;

class SeveranceResultFormatter
{
    private const LABELS = [
        'severance' => 'A. PESANGON',
        'separation' => 'A. UANG PISAH',
        'compensation' => 'A. KOMPENSASI',
        'service_award' => 'B. PENGHARGAAN MASA KERJA',
        'leave' => 'C. PENGGANTIAN HAK CUTI',
        'return_fare' => 'D. ONGKOS PULANG',
    ];

    public function format(array $result): array
    {
        foreach ($result['lines'] as $index => $line) {
            $result['lines'][$index]['label'] = self::LABELS[$line['code']] ?? $line['label'];
        }
        $result['total_label'] = (string) $result['article'] === '16' ? 'B. TOTAL' : 'E. TOTAL';
        return $result;
    }
}
