<?php

namespace App\Services\ElectronicContracts;

use App\Models\ElectronicContractAuditLog;
use App\Models\EmployeeContract;
use Illuminate\Http\Request;

class ElectronicContractAuditService
{
    public function record(
        ?EmployeeContract $contract,
        string $event,
        Request $request,
        array $metadata = []
    ): ElectronicContractAuditLog {
        $user = $request->user();

        return ElectronicContractAuditLog::create([
            'employee_contract_id' => optional($contract)->id,
            'nik' => optional($contract)->nik,
            'event' => $event,
            'actor_user_id' => optional($user)->id,
            'actor_name' => optional($user)->name,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'metadata' => $metadata,
        ]);
    }
}
