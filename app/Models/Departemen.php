<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Departemen extends Model
{
    protected $table = 'departemens';

    protected $guarded = [];

    public function employee()
    {
        return $this->hasMany(Employee::class, 'departemen_id');
    }

    public function divisi()
    {
        return $this->hasMany(Divisi::class, 'departemen_id');
    }

    public function perusahaan()
    {
        return $this->belongsTo(Perusahaan::class, 'perusahaan_id');
    }
}
