<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LokasiAbsen extends Model
{
    protected $table = 'lokasi_absens';

    protected $guarded = [];

    public function getDisplayNameAttribute(): string
    {
        if (filled($this->nama_lokasi)) {
            return $this->nama_lokasi;
        }

        if ($this->relationLoaded('divisi') && $this->divisi) {
            return 'Lokasi ' . $this->divisi->nama_divisi;
        }

        return 'Lokasi Presensi #' . $this->id;
    }

    public function divisi()
    {
        return $this->belongsTo(Divisi::class, 'divisi_id');
    }

    public function employeeLocationAssignments()
    {
        return $this->hasMany(EmployeeAttendanceLocationAssignment::class, 'lokasi_absen_id');
    }
}
