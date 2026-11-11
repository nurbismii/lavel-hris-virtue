<?php

namespace App\Services\Recovery;

use App\Models\{Departemen, Divisi, Employee, OrganizationPosition, Perusahaan, SalaryComponent, User};
use App\Services\Audit\AuditTrailService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeletedDataService
{
    public const TYPES = [
        'karyawan'=>[Employee::class, 'Karyawan', 'nama_karyawan', 'data_karyawan'],
        'perusahaan'=>[Perusahaan::class, 'Perusahaan', 'nama_perusahaan', 'perusahaan'],
        'departemen'=>[Departemen::class, 'Departemen', 'departemen', 'perusahaan'],
        'divisi'=>[Divisi::class, 'Divisi', 'nama_divisi', 'perusahaan'],
        'komponen-gaji'=>[SalaryComponent::class, 'Komponen gaji', 'name', 'slip_gaji_admin'],
    ];

    public function query(string $type, User $actor)
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        abort_unless($actor->hasRole(['Super Admin', 'HR']) && $actor->hasMenuAccess(self::TYPES[$type][3]), 403);
        return self::TYPES[$type][0]::withTrashed();
    }

    public function change(string $type, string $id, User $actor, bool $restore): void
    {
        DB::transaction(function () use ($type, $id, $actor, $restore) {
            $item = $this->query($type, $actor)->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($item->trashed() !== $restore) { return; }
            if (!$restore) { $this->checkDependencies($item); }
            if ($restore && $item instanceof Employee && $item->departemen_id && !Departemen::whereKey($item->departemen_id)->exists()) {
                $this->invalid('Pulihkan departemen karyawan terlebih dahulu.');
            }
            if ($restore && $item instanceof Employee && $item->no_ktp && $item->status_resign === 'AKTIF'
                && Employee::where('no_ktp', $item->no_ktp)->where('status_resign', 'AKTIF')->lockForUpdate()->exists()) {
                $this->invalid('Sudah ada karyawan aktif dengan identitas yang sama. Periksa data karyawan sebelum memulihkan.');
            }
            $item->deleted_by = $restore ? null : (string) $actor->id;
            $item->save();
            $restore ? $item->restore() : $item->delete();
            app(AuditTrailService::class)->record([
                'actor'=>$actor, 'module'=>'recovery', 'event'=>$restore ? 'data.restored' : 'data.deleted',
                'auditable_type'=>get_class($item), 'auditable_id'=>(string) $item->getKey(),
                'reference_table'=>$item->getTable(), 'reference_id'=>(string) $item->getKey(),
                'employee_nik'=>$item instanceof Employee ? (string) $item->getKey() : null,
                'note'=>$restore ? 'Data dipulihkan dari Tempat Sampah.' : 'Data dipindahkan ke Tempat Sampah.',
            ]);
        }, 3);
    }

    private function checkDependencies(Model $item): void
    {
        $used = match (true) {
            $item instanceof Perusahaan => Departemen::withTrashed()->where('perusahaan_id', $item->id)->exists()
                || OrganizationPosition::where('perusahaan_id', $item->id)->exists(),
            $item instanceof Departemen => Divisi::withTrashed()->where('departemen_id', $item->id)->exists()
                || Employee::withTrashed()->where('departemen_id', $item->id)->exists()
                || OrganizationPosition::where('departemen_id', $item->id)->exists(),
            $item instanceof Divisi => Employee::withTrashed()->where('divisi_id', $item->id)->exists()
                || OrganizationPosition::where('divisi_id', $item->id)->exists(),
            default => false,
        };
        if ($used) { $this->invalid('Master masih digunakan oleh data organisasi/karyawan, termasuk yang ada di Tempat Sampah. Pindahkan relasinya terlebih dahulu.'); }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['recovery'=>$message]);
    }
}
