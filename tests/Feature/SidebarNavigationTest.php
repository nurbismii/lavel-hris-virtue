<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\Approvals\ApprovalSidebarCountService;
use DOMDocument;
use DOMXPath;
use Mockery;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    public function test_staff_has_a_compact_menu_with_all_personal_destinations(): void
    {
        $nav = $this->navigation('Staff', 'cuti.create');

        $this->assertSame(5, $nav->query('//*[@id="appNavigation"]/li')->length);
        $this->assertSame(1, $nav->query('//button[@data-bs-toggle="collapse"]')->length);
        $this->assertSame(1, $nav->query('//*[@id="sidebar-requests" and contains(@class,"show")]')->length);

        foreach (['dashboard.karyawan', 'presensi.index', 'cuti.index', 'izin.index',
            'attendance-corrections.index', 'lembur.index', 'slipgaji.index', 'user-electronic-contracts.index'] as $route) {
            $this->assertSame(1, $nav->query('//*[@id="appNavigation"]//a[@href="' . route($route) . '"]')->length, $route);
        }
        $this->assertSame(0, $nav->query('//*[@id="sidebar-approvals" or @id="sidebar-settings"]')->length);
    }

    public function test_hr_and_hod_keep_separate_approval_and_management_access(): void
    {
        $hod = $this->navigation('HOD', 'approval.cuti.hod');
        $this->assertSame(1, $hod->query('//a[@href="' . route('approval.cuti.hod') . '" and @aria-current="page"]')->length);
        $this->assertSame(0, $hod->query('//a[@href="' . route('approval.cuti.hrd') . '"]')->length);
        $this->assertSame(0, $hod->query('//a[@href="' . route('overtime-masters.index') . '"]')->length);

        $hr = $this->navigation('HR', 'approval.cuti.hrd');
        foreach (['approval.cuti.hod', 'approval.cuti.hrd', 'overtime-masters.index'] as $route) {
            $this->assertSame(1, $hr->query('//a[@href="' . route($route) . '"]')->length);
        }
        $this->assertSame(0, $hr->query('//*[@id="sidebar-requests"]')->length);
        $this->assertSame(0, $hr->query('//a[@href="' . route('presensi.index') . '"]')->length);
    }

    public function test_restricted_roles_have_no_empty_groups_or_extra_permissions(): void
    {
        $empty = $this->navigation('Custom', 'update.akun', []);
        $this->assertSame(0, $empty->query('//*[@id="appNavigation"]/li')->length);

        $shift = $this->navigation('Custom', 'shifts.index', ['master_shift']);
        $this->assertSame(1, $shift->query('//*[@id="appNavigation"]/li')->length);
        $this->assertSame(1, $shift->query('//*[@id="appNavigation"]//a')->length);
        $this->assertSame(1, $shift->query('//a[@href="' . route('shifts.index') . '"]')->length);

        $staff = $this->navigation('Staff', 'home', ['slip_gaji_admin', 'electronic_contract_admin', 'electronic_contract_first_party_signature', 'off_roster']);
        foreach (['employee-salaries.index', 'electronic-contracts.first-party-signature.edit', 'roster-off.index'] as $route) {
            $this->assertSame(0, $staff->query('//a[@href="' . route($route) . '"]')->length, $route);
        }
        $hr = $this->navigation('HR', 'employee-salaries.index', ['slip_gaji_admin']);
        $this->assertSame(1, $hr->query('//a[@href="' . route('employee-salaries.index') . '" and @aria-current="page"]')->length);
    }

    public function test_delegated_access_and_pending_badges_remain_visible(): void
    {
        $counts = (new ApprovalSidebarCountService())->defaultCounts();
        $counts['approvalDelegateAccess'] = true;
        $counts['employeeMovementDelegateAccess'] = true;
        $counts['approvalDelegateCounts']['total'] = 3;
        $nav = $this->navigation('Staff', 'approval.delegate.index', [], $counts);

        $this->assertSame(1, $nav->query('//*[@id="sidebar-approvals"]//a[@href="' . route('approval.delegate.index', ['module' => 'cuti']) . '"]')->length);
        $this->assertSame('3', trim($nav->evaluate('string(//button[@aria-controls="sidebar-approvals"]/span[contains(@class,"badge")])')));
        $this->assertSame(1, $nav->query('//*[@id="sidebar-employees"]//a[@href="' . route('employee-movements.index') . '"]')->length);
    }

    public function test_active_pages_open_the_correct_group_without_nested_accordions(): void
    {
        foreach ([
            'presensi.index' => null,
            'attendance-corrections.index' => 'requests',
            'approval.cuti.hrd' => 'approvals',
            'employee-movements.index' => 'employees',
            'organization-structure.index' => 'employees',
            'shift-settings.index' => 'attendance',
            'overtime-masters.index' => 'attendance',
            'electronic-contracts.templates.index' => 'contracts',
            'employee-salaries.index' => 'contracts',
            'contract-renewals.dashboard' => 'contracts',
            'approval-sla.index' => 'monitoring',
            'search-logs.index' => 'settings',
        ] as $route => $group) {
            $nav = $this->navigation('Super Admin', $route);
            $this->assertSame(1, $nav->query('//*[@id="appNavigation"]//a[@aria-current="page"]')->length, $route);
            $this->assertSame(0, $nav->query('//div[contains(@class,"collapse")]//div[contains(@class,"collapse")]')->length);
            $this->assertSame($group ? 1 : 0, $nav->query('//button[@aria-expanded="true"]')->length, $route);
            if ($group) {
                $this->assertSame(1, $nav->query('//*[@id="sidebar-' . $group . '" and contains(@class,"show")]')->length, $route);
            }
        }
    }

    public function test_both_languages_have_translated_navigation_labels(): void
    {
        foreach (['id', 'zh_CN'] as $locale) {
            app()->setLocale($locale);
            $nav = $this->navigation('Super Admin');
            $this->assertStringNotContainsString('navigation.', $nav->document->textContent);
        }
    }

    private function navigation(string $roleName, string $route = 'home', ?array $menus = null, ?array $counts = null): DOMXPath
    {
        $role = new Role();
        $role->forceFill(['id' => 1, 'permission_role' => $roleName, 'menu_permissions' => $menus]);
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('assignedRoles')->andReturn(collect([$role]));
        $this->actingAs($user);
        $this->instance(ApprovalSidebarCountService::class, Mockery::mock(ApprovalSidebarCountService::class, function ($mock) use ($counts) {
            $mock->shouldReceive('countsFor')->andReturn($counts ?? (new ApprovalSidebarCountService())->defaultCounts());
        }));
        $currentRoute = app('router')->getRoutes()->getByName($route);
        $this->assertNotNull($currentRoute, $route);
        request()->setRouteResolver(function () use ($currentRoute) { return $currentRoute; });

        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="utf-8" ?>' . view('partials.sidebar')->render(), LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }
}
