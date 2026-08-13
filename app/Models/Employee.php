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
        'tanggal_kelulusan',
        'tanggal_menikah',
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

    public function jobTitle()
    {
        return $this->belongsTo(JobTitle::class, 'job_title_id');
    }

    public function organizationPosition()
    {
        return $this->belongsTo(OrganizationPosition::class, 'organization_position_id');
    }

    public function directSupervisor()
    {
        return $this->belongsTo(self::class, 'reports_to_nik', 'nik');
    }

    public function directReports()
    {
        return $this->hasMany(self::class, 'reports_to_nik', 'nik');
    }

    public function positionAssignments()
    {
        return $this->hasMany(EmployeePositionAssignment::class, 'employee_nik', 'nik');
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

    public function cvEducations()
    {
        return $this->hasMany(EmployeeCvEducation::class, 'employee_nik', 'nik');
    }

    public function cvExperiences()
    {
        return $this->hasMany(EmployeeCvExperience::class, 'employee_nik', 'nik');
    }

    public function cvOrganizations()
    {
        return $this->hasMany(EmployeeCvOrganization::class, 'employee_nik', 'nik');
    }

    public function cvCertifications()
    {
        return $this->hasMany(EmployeeCvCertification::class, 'employee_nik', 'nik');
    }

    public function cvLanguages()
    {
        return $this->hasMany(EmployeeCvLanguage::class, 'employee_nik', 'nik');
    }

    public function cvProjects()
    {
        return $this->hasMany(EmployeeCvProject::class, 'employee_nik', 'nik');
    }
}
