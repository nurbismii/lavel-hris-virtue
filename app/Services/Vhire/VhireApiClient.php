<?php

namespace App\Services\Vhire;

use App\Models\EmployeeContract;
use App\Models\OnboardingCandidate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class VhireApiClient
{
    public function sendContract(EmployeeContract $contract, string $idempotencyKey): array
    {
        $endpoint = $this->endpoint('/api/vhire/contracts');
        $payload = $this->contractPayload($contract);

        return $this->post($endpoint, $payload, $idempotencyKey);
    }

    public function sendActivation(OnboardingCandidate $candidate, string $idempotencyKey): array
    {
        $path = $candidate->vhire_candidate_id
            ? '/api/vhire/candidates/' . rawurlencode($candidate->vhire_candidate_id) . '/activated'
            : '/api/vhire/candidates/activated';
        $endpoint = $this->endpoint($path);
        $payload = [
            'vhire_candidate_id' => $candidate->vhire_candidate_id,
            'candidate_code' => $candidate->candidate_code,
            'no_ktp' => $candidate->no_ktp,
            'employee_nik' => $candidate->employee_nik,
            'activated_as_employee_at' => optional($candidate->activated_as_employee_at)->format('Y-m-d H:i:s'),
        ];

        return $this->post($endpoint, $payload, $idempotencyKey);
    }

    public function contractPayload(EmployeeContract $contract): array
    {
        $contract->loadMissing('onboardingCandidate');

        return [
            'hris_contract_id' => $contract->id,
            'vhire_candidate_id' => $contract->vhire_candidate_id,
            'candidate_code' => $contract->candidate_code,
            'no_ktp' => $contract->no_ktp,
            'nama' => $contract->display_employee_name,
            'kode_kontrak' => $contract->contract_code,
            'no_pkwt' => $contract->pkwt_number,
            'jabatan' => $contract->position,
            'departemen' => $contract->departemen,
            'lokasi' => $contract->lokasi,
            'tanggal_mulai_kontrak' => optional($contract->contract_start_date)->format('Y-m-d'),
            'tanggal_akhir_kontrak' => optional($contract->contract_end_date)->format('Y-m-d'),
            'duration_value' => $contract->duration_value,
            'duration_unit' => $contract->duration_unit,
            'durasi_kontrak' => $contract->contract_duration,
            'gaji' => $contract->salary !== null ? (float) $contract->salary : null,
            'signature_status' => $contract->signature_status,
            'status_tanda_tangan' => $contract->signature_status_label,
            'signing_method' => $contract->signing_method,
            'visible_in_vhire' => (bool) $contract->visible_in_vhire,
            'contract_content' => $contract->rendered_html,
        ];
    }

    public function endpoint(string $path): string
    {
        $baseUrl = rtrim((string) config('services.vhire.base_url'), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('Konfigurasi VHIRE_API_BASE_URL belum tersedia.');
        }

        return $baseUrl . $path;
    }

    private function post(string $endpoint, array $payload, string $idempotencyKey): array
    {
        $token = (string) config('services.vhire.outbound_token');

        if ($token === '') {
            throw new RuntimeException('Konfigurasi VHIRE_API_TOKEN belum tersedia.');
        }

        try {
            $response = Http::withToken($token)
                ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                ->acceptJson()
                ->timeout((int) config('services.vhire.timeout', 15))
                ->post($endpoint, $payload);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('API V-Hire tidak bisa dihubungi: ' . $exception->getMessage(), 0, $exception);
        }

        $body = $this->sanitizeResponseBody((string) $response->body());

        if (!$response->successful()) {
            throw new RuntimeException(sprintf(
                'API V-Hire gagal merespons. Status: %s. Body: %s',
                $response->status(),
                Str::limit($body, 500)
            ));
        }

        if ($this->isBlockedByHostingProtection($body)) {
            throw new RuntimeException(sprintf(
                'API V-Hire diblokir proteksi hosting. Status: %s. Body: %s',
                $response->status(),
                Str::limit($body, 500)
            ));
        }

        return [
            'http_status' => $response->status(),
            'body' => Str::limit($body, 1000),
            'json' => $response->json(),
        ];
    }

    private function isBlockedByHostingProtection(string $body): bool
    {
        $body = Str::lower($body);

        return Str::contains($body, ['imunify360', 'bot-protection']);
    }

    private function sanitizeResponseBody(string $body): string
    {
        return preg_replace('/\b[0-9]{16}\b/', '****MASKED_KTP****', $body) ?: '';
    }
}
