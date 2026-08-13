<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Perusahaan extends Model
{
    public const ORGANIZATION_COMPANY_CODES = ['VDNI', 'VDNIP'];

    protected $table = 'perusahaan';

    protected $guarded = [];

    public function scopeOrganizationCompanies($query)
    {
        return $query->whereIn('kode_perusahaan', self::ORGANIZATION_COMPANY_CODES);
    }

    public function departemen()
    {
        return $this->hasMany(Departemen::class, 'perusahaan_id');
    }

    public function organizationPositions()
    {
        return $this->hasMany(OrganizationPosition::class, 'perusahaan_id');
    }
}
