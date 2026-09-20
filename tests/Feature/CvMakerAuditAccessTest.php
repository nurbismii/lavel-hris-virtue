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

    public function test_audit_can_preview_hris_photo_only_with_cv_menu_access(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        \Illuminate\Support\Facades\DB::purge('sqlite');
        \Illuminate\Support\Facades\Schema::create('employees', function ($table) {
            $table->string('nik')->primary();
            $table->string('nama_karyawan');
            $table->string('photo_path');
            $table->string('ktp_path');
        });
        $path = 'employee-documents/0002/photo/test.png';
        \Illuminate\Support\Facades\DB::table('employees')->insert([
            'nik' => '0002', 'nama_karyawan' => 'Test', 'photo_path' => $path,
            'ktp_path' => 'employee-documents/0002/ktp/test.png',
        ]);
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='
        ));
        $storage = \Mockery::mock(\App\Services\Storage\SensitiveFileStorageService::class);
        $storage->shouldReceive('resolvePath')->once()->with($path, ['employee-documents/0002/photo/'])
            ->andReturn(\Illuminate\Support\Facades\Storage::disk('local')->path($path));
        $this->app->instance(\App\Services\Storage\SensitiveFileStorageService::class, $storage);
        $request = $this->auditRequest();
        $request->user()->nik_karyawan = '0001';
        $controller = new \App\Http\Controllers\Admin\KaryawanController();
        $response = $controller->photo($request, '0002');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/png', $response->headers->get('Content-Type'));

        try {
            $controller->previewDocument($request, '0002', 'ktp');
            $this->fail('Audit must not read another employee identity document.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            $this->assertTrue(true);
        }
        $request->user()->shouldReceive('hasMenuAccess')->with('cv_maker_compare')->andReturn(false);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $controller->photo($request, '0002');
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

    public function test_audit_can_fetch_cv_photo_only_for_matching_profile(): void
    {
        $request = $this->auditRequest();
        $employee = new \App\Models\Employee();
        $employee->nik = '0002';
        $query = \Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
        $query->shouldReceive('where')->with('employees.nik', '0002')->andReturnSelf();
        $query->shouldReceive('with')->andReturnSelf();
        $query->shouldReceive('firstOrFail')->andReturn($employee);
        $request->user()->shouldReceive('applyCvMakerEmployeeScope')->andReturn($query);
        $service = \Mockery::mock(CvMakerCompareService::class);
        $service->shouldReceive('detailForEmployee')->with($employee)->andReturn([
            'cv_profile' => ['profile_id' => 7],
            'vitae' => ['profile' => ['photo_available' => true]],
        ]);
        $service->shouldReceive('hashNik')->with('0002')->once()->andReturn('nik-hash');
        $client = \Mockery::mock(CvMakerApiClient::class);
        $client->shouldReceive('file')->once()->with('api/internal/vpeople/profiles/7/photo', 'nik-hash')
            ->andReturn(new \Illuminate\Http\Client\Response(new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'image/png'], 'photo-bytes')));
        $controller = new CvMakerCompareController();
        $response = $controller->photo($request, '0002', 7, $service, $client);
        $this->assertSame('photo-bytes', $response->getContent());
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        try {
            $controller->photo($request, '0002', 8, $service, $client);
            $this->fail('A different CV profile must not be accessible.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
        $request->user()->shouldReceive('hasMenuAccess')->with('cv_maker_compare')->andReturn(false);
        try {
            $controller->photo($request, '0002', 7, $service, $client);
            $this->fail('CV menu permission is required.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_audit_cannot_fetch_personal_documents_even_with_an_additional_hr_role(): void
    {
        foreach ([['Audit CV'], ['Audit CV', 'HR']] as $roles) {
            foreach (['document'] as $method) {
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
