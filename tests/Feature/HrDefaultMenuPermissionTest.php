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
        $this->assertContains('approval_hod', $hrMenus);
        $this->assertContains('approval_hr', $hrMenus);
        $this->assertContains('lembur', $hrMenus);
        $this->assertContains('audit_trail', $hrMenus);

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
}
