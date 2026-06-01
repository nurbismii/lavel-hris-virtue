<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $table = 'employees';
    protected $primaryKey = 'nik';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    protected $dates = [
        'entry_date',
        'tgl_resign',
        'tgl_lahir',
        'work_pattern_start_date',
    ];

    public function getDocumentPhotoUrlAttribute(): ?string
    {
        return filled($this->photo_path) ? route('employee.photo', ['nik' => $this->nik]) : null;
    }

    public function departemen()
    {
        return $this->belongsTo(Departemen::class, 'departemen_id');
    }

    public function divisi()
    {
        return $this->belongsTo(Divisi::class, 'divisi_id');
    }

    public function provinsi()
    {
        return $this->belongsTo(Provinsi::class, 'provinsi_id');
    }

    public function kabupaten()
    {
        return $this->belongsTo(Kabupaten::class, 'kabupaten_id');
    }

    public function kecamatan()
    {
        return $this->belongsTo(Kecamatan::class, 'kecamatan_id');
    }

    public function kelurahan()
    {
        return $this->belongsTo(Kelurahan::class, 'kelurahan_id');
    }

    public function presensi()
    {
        return $this->hasMany(Presensi::class, 'nik_karyawan');
    }

    public function workPattern()
    {
        return $this->belongsTo(WorkPattern::class, 'work_pattern_id');
    }

    public function shiftAssignments()
    {
        return $this->hasMany(EmployeeShiftAssignment::class, 'employee_id', 'nik');
    }

    public function attendanceLocationAssignments()
    {
        return $this->hasMany(EmployeeAttendanceLocationAssignment::class, 'employee_nik', 'nik');
    }

    public function overtimeOrders()
    {
        return $this->hasMany(OvertimeOrder::class, 'nik_karyawan', 'nik');
    }

    public function leaveBalanceLedgers()
    {
        return $this->hasMany(LeaveBalanceLedger::class, 'employee_nik', 'nik');
    }

    public function movements()
    {
        return $this->hasMany(EmployeeMovement::class, 'employee_nik', 'nik');
    }
}
