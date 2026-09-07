<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\UserController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminUserSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('sqlite');
        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
            foreach (['nik_karyawan', 'name', 'email', 'status', 'terakhir_login'] as $column) {
                $table->string($column)->nullable();
            }
            $table->integer('role_id')->nullable();
        });
        Schema::create('employees', function (Blueprint $table) {
            $table->string('nik')->primary();
            foreach (['nama_karyawan', 'departemen_id', 'divisi_id', 'sisa_cuti', 'posisi', 'photo_path', 'face_reference_path', 'work_pattern_id', 'work_pattern_start_date'] as $column) {
                $table->string($column)->nullable();
            }
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('permission_role');
        });
        Schema::create('role_user', function (Blueprint $table) {
            $table->string('user_id');
            $table->integer('role_id');
            $table->timestamps();
        });
        DB::table('roles')->insert([
            ['id' => 1, 'permission_role' => 'HR'],
            ['id' => 2, 'permission_role' => 'Audit CV'],
        ]);
        DB::table('users')->insert([
            ['id' => 'a', 'nik_karyawan' => '001', 'name' => 'Account One', 'email' => 'one@example.test', 'status' => 'aktif', 'role_id' => 1],
            ['id' => 'b', 'nik_karyawan' => '002', 'name' => 'Fallback Two', 'email' => 'two@example.test', 'status' => 'tidak aktif', 'role_id' => null],
            ['id' => 'c', 'nik_karyawan' => '003', 'name' => 'Other', 'email' => 'other@example.test', 'status' => 'aktif', 'role_id' => 2],
        ]);
        DB::table('employees')->insert(['nik' => '001', 'nama_karyawan' => 'Budi Santoso']);
        DB::table('role_user')->insert(['user_id' => 'a', 'role_id' => 2]);
        $this->actingAs(User::findOrFail('a'));
    }

    public function test_search_matches_nik_employee_name_fallback_email_and_additional_role(): void
    {
        foreach (['001', 'Budi', 'one@example.test'] as $keyword) {
            $this->assertSame(['001'], $this->search($keyword));
        }
        $this->assertSame(['002'], $this->search('Fallback'));
        $this->assertSame(['001', '003'], $this->search('Audit CV'));
        $this->assertSame([], $this->search('does-not-exist'));
    }

    public function test_filters_combine_with_search_and_include_additional_roles(): void
    {
        $this->assertSame(['002'], $this->search('', ['status' => 'tidak aktif']));
        $this->assertSame(['001', '003'], $this->search('', ['role_id' => 2]));
        $this->assertSame(['001'], $this->search('Budi', ['role_id' => 2, 'status' => 'aktif']));
        $this->assertSame([], $this->search('Budi', ['status' => 'tidak aktif']));
        $this->assertSame(['001', '002', '003'], $this->search(''));
    }

    public function test_search_cannot_bypass_employee_scope(): void
    {
        DB::table('users')->where('id', 'a')->update(['role_id' => null]);
        DB::table('role_user')->delete();
        $this->actingAs(User::findOrFail('a'));
        $this->assertSame([], $this->search('other@example.test'));
        $this->assertSame(['001'], $this->search(''));
    }

    public function test_invalid_status_is_rejected(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->search('', ['status' => 'invalid']);
    }

    public function test_employee_name_ordering_and_index_rendering(): void
    {
        $this->assertSame(['001', '002', '003'], $this->search('', [
            'order' => [['column' => 1, 'dir' => 'asc']],
        ]));
        // Render the page content without the unrelated navbar database dependencies.
        $template = str_replace("@extends('layouts.app')", '', file_get_contents(resource_path('views/admin/user/index.blade.php')));
        $html = \Illuminate\Support\Facades\Blade::render($template . "\n@yield('content')\n@stack('scripts')", ['roles' => \App\Models\Role::all()]);
        $this->assertStringContainsString('filter-status', $html);
        $this->assertStringContainsString('filter-role', $html);
    }

    private function search(string $keyword, array $filters = []): array
    {
        $request = Request::create('/user/datatable', 'GET', array_merge([
            'draw' => 1, 'start' => 0, 'length' => 10,
            'search' => ['value' => $keyword, 'regex' => 'false'],
            'columns' => [
                ['data' => 'nik_karyawan', 'name' => 'nik_karyawan', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'nama_karyawan', 'name' => 'employee.nama_karyawan', 'searchable' => 'true', 'orderable' => 'true'],
            ],
            'order' => [['column' => 0, 'dir' => 'asc']],
        ], $filters));
        $this->app->instance('request', $request);
        $response = app(UserController::class)->dataTable($request)->getData(true);
        $this->assertArrayNotHasKey('error', $response);

        $niks = array_column($response['data'], 'nik_karyawan');
        sort($niks);

        return $niks;
    }
}
