<?php

namespace Tests\Feature;

use Tests\TestCase;

class HrDefaultMenuPermissionTest extends TestCase
{
    public function test_hr_default_menu_does_not_include_employee_self_service_menus(): void
    {
        $hrMenus = config('access.default_menu_permissions.HR', []);

        $this->assertContains('dashboard_admin', $hrMenus);
        $this->assertContains('data_karyawan', $hrMenus);
        $this->assertContains('cv_maker_compare', $hrMenus);
        $this->assertContains('approval_hod', $hrMenus);
        $this->assertContains('approval_hr', $hrMenus);
        $this->assertContains('lembur', $hrMenus);
        $this->assertContains('audit_trail', $hrMenus);
        $this->assertContains('organization_structure', $hrMenus);

        foreach ([
            'dashboard_karyawan',
            'slip_gaji_user',
            'cuti',
            'izin',
            'presensi',
            'attendance_correction',
        ] as $menu) {
            $this->assertNotContains($menu, $hrMenus);
        }
    }

    public function test_hod_default_menu_can_access_employee_movement_submission(): void
    {
        $hodMenus = config('access.default_menu_permissions.HOD', []);

        $this->assertContains('employee_movement', $hodMenus);
        $this->assertContains('cv_maker_compare', $hodMenus);
        $this->assertContains('approval_hod', $hodMenus);
        $this->assertContains('organization_structure', $hodMenus);
    }
}
