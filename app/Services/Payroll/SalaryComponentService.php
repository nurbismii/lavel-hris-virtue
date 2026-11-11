<?php

namespace App\Services\Payroll;

use App\Models\{SalaryComponent, User};
use App\Services\Audit\AuditTrailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalaryComponentService
{
    public function save(array $data, User $actor, ?SalaryComponent $component = null): void
    {
        DB::transaction(function () use ($data, $actor, $component) {
            if ($component) {
                $component = SalaryComponent::whereKey($component->id)->lockForUpdate()->firstOrFail();
                if ((int) $data['revision'] !== (int) $component->revision) {
                    throw ValidationException::withMessages(['revision'=>'Komponen telah diubah. Muat ulang halaman sebelum menyimpan.']);
                }
                $component->update(['name'=>$data['name'], 'revision'=>$component->revision + 1]);
            } else {
                $component = SalaryComponent::create($data + ['created_by'=>(string) $actor->id]);
            }
            app(AuditTrailService::class)->record(['actor'=>$actor, 'module'=>'salary_components', 'event'=>'component.saved',
                'auditable_type'=>SalaryComponent::class, 'auditable_id'=>(string) $component->id,
                'metadata'=>['code'=>$component->code, 'revision'=>$component->revision]]);
        }, 3);
    }

    public function snapshots(array $amounts, bool $recurring): array
    {
        $amounts = array_filter($amounts, fn ($value) => $value !== null && $value !== '');
        if (!$amounts) { return []; }
        $components = SalaryComponent::whereIn('id', array_keys($amounts))->get()->keyBy('id');
        $result = [];
        foreach ($amounts as $id=>$amount) {
            $component = $components->get($id);
            if (!$component || ($recurring && !$component->fixed)) {
                throw ValidationException::withMessages(['custom'=>'Komponen tidak aktif atau tidak berlaku berulang. Muat ulang isian payroll.']);
            }
            $result[$id] = ['id'=>$component->id, 'code'=>$component->code, 'name'=>$component->name,
                'type'=>$component->type, 'fixed'=>$component->fixed, 'amount'=>(int) $amount];
        }
        return $result;
    }

    public function version(bool $lock = false): string
    {
        $query = SalaryComponent::withTrashed()->orderBy('id');
        if ($lock) { $query->lockForUpdate(); }
        // Older previews added fixed components on top of the wage history.
        return hash('sha256', 'fixed-in-wage:1|'.$query->get(['id', 'revision', 'deleted_at'])->toJson());
    }

    public function fixedIncomeTotal(array $components): int
    {
        $total = 0;
        foreach ($components as $component) {
            if ($component['type'] === 'income' && $component['fixed']) { $total += $component['amount']; }
        }
        return $total;
    }

    public function assertFixedBreakdown(array $components, int $available): void
    {
        if ($this->fixedIncomeTotal($components) > $available) {
            throw ValidationException::withMessages(['custom'=>'Jumlah rincian tunjangan tetap melebihi tunjangan tetap lain dalam Riwayat Upah (setelah masa kerja). Perbaiki rincian atau perbarui Riwayat Upah; nominal ini tidak dibayar lagi sebagai tambahan.']);
        }
    }
}
