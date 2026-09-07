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
