<?php

namespace App\Services\Vhire;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class VhirePayloadSanitizer
{
    public function maskNoKtp(?string $noKtp): ?string
    {
        $value = (string) $noKtp;

        if ($value === '') {
            return null;
        }

        if (strlen($value) < 8) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, 4) . str_repeat('*', max(strlen($value) - 8, 0)) . substr($value, -4);
    }

    public function summary(array $payload): string
    {
        if (isset($payload['no_ktp'])) {
            $payload['no_ktp'] = $this->maskNoKtp($payload['no_ktp']);
        }

        foreach (['contract_content', 'contract_file_base64', 'manual_note'] as $key) {
            if (isset($payload[$key]) && is_string($payload[$key])) {
                $payload[$key] = Str::limit($payload[$key], 500);
            }
        }

        $summary = Arr::only($payload, [
            'hris_contract_id',
            'vhire_candidate_id',
            'candidate_code',
            'no_ktp',
            'nama',
            'kode_kontrak',
            'no_pkwt',
            'signing_method',
            'signature_status',
            'visible_in_vhire',
            'employee_nik',
            'activated_as_employee_at',
            'status_tanda_tangan',
            'error',
        ]);

        return json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
