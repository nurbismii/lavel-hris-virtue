<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\SettingRoleController;
use App\Models\Role;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SettingRoleSystemProtectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('permission_role');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->json('menu_permissions')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('name');
            $table->string('email')->nullable();
            $table->unsignedInteger('role_id')->nullable();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->string('user_id', 36);
            $table->unsignedInteger('role_id');
            $table->timestamps();
        });
    }

    public function test_system_roles_cannot_be_deleted(): void
    {
        $role = Role::create([
            'permission_role' => 'Super Admin',
            'status' => 1,
        ]);

        try {
            app(SettingRoleController::class)->destroy($role->id);

            $this->fail('System role deletion must be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'permission_role' => 'Super Admin',
        ]);
    }

    public function test_system_role_aliases_are_protected(): void
    {
        $role = new Role([
            'permission_role' => 'Administrator',
        ]);

        $this->assertTrue($role->is_system_role);
    }
}
