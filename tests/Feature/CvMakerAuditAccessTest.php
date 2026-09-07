<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\CvMakerCompareController;
use App\Models\Role;
use App\Models\User;
use App\Services\CvMaker\CvMakerApiClient;
use App\Services\CvMaker\CvMakerCompareService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CvMakerAuditAccessTest extends TestCase
{
    public function test_audit_mutations_are_denied_before_controller_execution(): void
    {
        foreach (['preview-update', 'update-hris', 'correct-field', 'reminders.store'] as $suffix) {
            $route = app('router')->getRoutes()->getByName('cv-maker-compare.' . $suffix);
            $guards = array_filter($route->gatherMiddleware(), function ($middleware) {
                return $middleware instanceof \Closure;
            });
            $this->assertNotEmpty($guards);
            foreach ([['Audit CV'], ['Audit CV', 'HR']] as $roles) {
                foreach ($guards as $guard) {
                    try {
                        $guard($this->auditRequest($roles), function () {
                            $this->fail('Audit mutation must not reach the controller.');
                        });
                        $this->fail('Expected forbidden response.');
                    } catch (HttpException $exception) {
                        $this->assertSame(403, $exception->getStatusCode());
                    }
                }
            }
            foreach ($guards as $guard) {
                $this->assertSame('allowed', $guard($this->auditRequest(['HR']), function () {
                    return 'allowed';
                }));
            }
        }

        $reviewRoute = app('router')->getRoutes()->getByName('cv-maker-compare.review-status.update');
        $this->assertEmpty(array_filter($reviewRoute->gatherMiddleware(), function ($middleware) {
            return $middleware instanceof \Closure;
        }));
        $this->assertContains('role:Super Admin,HR,HOD,Manager,Supervisor,Admin Divisi,Audit CV', $reviewRoute->gatherMiddleware());
    }

    public function test_audit_scope_includes_other_employees_only_in_cv_maker(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        \Illuminate\Support\Facades\DB::purge('sqlite');
        \Illuminate\Support\Facades\Schema::create('employees', function ($table) {
            $table->string('nik')->primary();
        });
        \Illuminate\Support\Facades\DB::table('employees')->insert([
            ['nik' => '0001'], ['nik' => '0002'],
        ]);

        $user = $this->auditRequest()->user();
        $user->nik_karyawan = '0001';
        $this->assertSame(['0001', '0002'], $user->applyCvMakerEmployeeScope(\App\Models\Employee::query())
            ->orderBy('nik')->pluck('nik')->all());
        $this->assertSame(['0001'], $user->applyEmployeeScope(\App\Models\Employee::query())
            ->pluck('nik')->all());

        $staff = $this->auditRequest(['Staff'])->user();
        $staff->nik_karyawan = '0001';
        $this->assertSame(['0001'], $staff->applyCvMakerEmployeeScope(\App\Models\Employee::query())
            ->pluck('nik')->all());
    }

    private function auditRequest(array $roles = ['Audit CV']): Request
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('assignedRoles')->andReturn(collect(array_map(function ($name) {
            return new Role(['permission_role' => $name]);
        }, $roles)));
        $request = Request::create('/cv-maker-compare/data');
        $request->setUserResolver(function () use ($user) { return $user; });

        return $request;
    }

    public function test_audit_can_read_compare_data_with_default_menu_permissions(): void
    {
        $request = $this->auditRequest();
        $service = \Mockery::mock(CvMakerCompareService::class);
        $service->shouldReceive('datatable')->once()->with($request, $request->user())
            ->andReturn(['data' => []]);

        $response = (new CvMakerCompareController())->data($request, $service);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($request->user()->hasMenuAccess('cv_maker_compare'));
    }

    public function test_audit_cannot_fetch_personal_files_even_with_an_additional_hr_role(): void
    {
        foreach ([['Audit CV'], ['Audit CV', 'HR']] as $roles) {
            foreach (['document', 'photo'] as $method) {
                $service = \Mockery::mock(CvMakerCompareService::class);
                $client = \Mockery::mock(CvMakerApiClient::class);
                $service->shouldNotReceive('detailForEmployee');
                $client->shouldNotReceive('file');
                try {
                    (new CvMakerCompareController())->{$method}($this->auditRequest($roles), '0001', 1, $service, $client);
                    $this->fail('Personal file access must be denied.');
                } catch (HttpException $exception) {
                    $this->assertSame(403, $exception->getStatusCode());
                }
            }
        }
    }
}
