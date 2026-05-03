<?php

namespace Tests\Feature;

use App\Http\Controllers\Approval\RosterOffApprovalController;
use App\Http\Controllers\User\RosterOffRequestController;
use App\Http\Requests\Approval\ProcessApprovalRequest;
use App\Http\Requests\Roster\RosterOffRequestRequest;
use App\Models\Employee;
use App\Models\Role;
use App\Models\RosterOffRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RosterOffRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-05-02 08:00:00');

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
        $this->seedOrganization();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_staff_roster_can_submit_off_request_and_active_duplicate_is_rejected(): void
    {
        $user = $this->makeUser('Staff Roster', 'EMP001');

        app(RosterOffRequestController::class)->store($this->offFormRequest($user, [
            'tanggal_off' => '2026-05-10',
            'alasan' => 'Pengganti hari libur roster.',
        ]));

        $this->assertDatabaseHas('roster_off_requests', [
            'nik_karyawan' => 'EMP001',
            'tanggal_off' => '2026-05-10',
            'status_hod' => RosterOffRequest::STATUS_PENDING,
            'status_hrd' => RosterOffRequest::STATUS_PENDING,
        ]);

        app(RosterOffRequestController::class)->store($this->offFormRequest($user, [
            'tanggal_off' => '2026-05-10',
            'alasan' => 'Duplikat.',
        ]));

        $this->assertSame(1, DB::table('roster_off_requests')->where('nik_karyawan', 'EMP001')->count());
    }

    public function test_super_admin_with_employee_profile_can_submit_off_request(): void
    {
        $user = $this->makeUser('Super Admin', 'EMP001');

        app(RosterOffRequestController::class)->store($this->offFormRequest($user, [
            'tanggal_off' => '2026-05-11',
            'alasan' => 'Pengujian akses Super Admin.',
        ]));

        $this->assertDatabaseHas('roster_off_requests', [
            'nik_karyawan' => 'EMP001',
            'requested_by' => 'user-EMP001',
            'tanggal_off' => '2026-05-11',
        ]);
    }

    public function test_effective_dates_returns_approved_off_inside_requested_period(): void
    {
        $user = $this->makeUser('Staff Roster', 'EMP001');

        DB::table('roster_off_requests')->insert([
            [
                'nik_karyawan' => 'EMP001',
                'tanggal_off' => '2026-05-10',
                'status_hod' => RosterOffRequest::STATUS_APPROVED,
                'status_hrd' => RosterOffRequest::STATUS_PENDING,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nik_karyawan' => 'EMP001',
                'tanggal_off' => '2026-05-12',
                'status_hod' => RosterOffRequest::STATUS_APPROVED,
                'status_hrd' => RosterOffRequest::STATUS_REJECTED,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = app(RosterOffRequestController::class)->effectiveDates(
            $this->plainRequest($user, [
                'periode_awal' => '2026-05-01',
                'periode_akhir' => '2026-05-31',
            ])
        );

        $payload = json_decode($response->getContent(), true);

        $this->assertSame(['2026-05-10'], collect($payload['data'])->pluck('date')->all());
    }

    public function test_hod_approval_makes_roster_off_effective_for_attendance_status(): void
    {
        DB::table('roster_off_requests')->insert([
            'id' => 1,
            'nik_karyawan' => 'EMP001',
            'tanggal_off' => '2026-05-10',
            'status_hod' => RosterOffRequest::STATUS_PENDING,
            'status_hrd' => RosterOffRequest::STATUS_PENDING,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(RosterOffApprovalController::class)->hodProcess(
            $this->approvalRequest($this->makeUser('HOD', 'HOD001'), RosterOffRequest::STATUS_APPROVED),
            1
        );

        $this->assertDatabaseHas('roster_off_requests', [
            'id' => 1,
            'status_hod' => RosterOffRequest::STATUS_APPROVED,
        ]);
        $this->assertDatabaseHas('absensis', [
            'nik_karyawan' => 'EMP001',
            'tanggal' => '2026-05-10',
            'status_presensi' => 'Off',
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('departemens', function (Blueprint $table) {
            $table->increments('id');
            $table->string('departemen');
        });

        Schema::create('divisis', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_divisi');
            $table->unsignedInteger('departemen_id')->nullable();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->string('nik')->primary();
            $table->string('nama_karyawan');
            $table->unsignedInteger('departemen_id')->nullable();
            $table->unsignedInteger('divisi_id')->nullable();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('nik_karyawan')->nullable();
            $table->timestamps();
        });

        Schema::create('roster_off_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nik_karyawan');
            $table->string('requested_by', 36)->nullable();
            $table->date('tanggal_off');
            $table->text('alasan')->nullable();
            $table->unsignedTinyInteger('status_hod')->default(0);
            $table->unsignedTinyInteger('status_hrd')->default(0);
            $table->string('hod_processed_by', 36)->nullable();
            $table->timestamp('hod_processed_at')->nullable();
            $table->string('hrd_processed_by', 36)->nullable();
            $table->timestamp('hrd_processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('absensis', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nik_karyawan');
            $table->date('tanggal');
            $table->dateTime('jam_masuk')->nullable();
            $table->dateTime('jam_istirahat')->nullable();
            $table->dateTime('jam_kembali_istirahat')->nullable();
            $table->dateTime('jam_pulang')->nullable();
            $table->string('status_presensi')->nullable();
            $table->timestamps();
        });
    }

    private function seedOrganization(): void
    {
        DB::table('departemens')->insert(['id' => 10, 'departemen' => 'Produksi']);
        DB::table('divisis')->insert(['id' => 101, 'nama_divisi' => 'Smelter A', 'departemen_id' => 10]);
        DB::table('employees')->insert([
            ['nik' => 'EMP001', 'nama_karyawan' => 'Staff Roster', 'departemen_id' => 10, 'divisi_id' => 101],
            ['nik' => 'HOD001', 'nama_karyawan' => 'HOD Produksi', 'departemen_id' => 10, 'divisi_id' => 101],
        ]);
    }

    private function makeUser(string $roleName, string $nik): User
    {
        $user = new User();
        $user->id = 'user-' . $nik;
        $user->name = $roleName . ' User';
        $user->email = strtolower($nik) . '@example.test';
        $user->nik_karyawan = $nik;
        $user->setRelation('role', new Role(['permission_role' => $roleName]));
        $user->setRelation('employee', Employee::query()->whereKey($nik)->first());

        return $user;
    }

    private function offFormRequest(User $user, array $payload): RosterOffRequestRequest
    {
        $request = RosterOffRequestRequest::create('/roster-off', 'POST', $payload);
        $this->prepareRequest($request, $user);
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);
        $request->validateResolved();

        return $request;
    }

    private function approvalRequest(User $user, int $action): ProcessApprovalRequest
    {
        $request = ProcessApprovalRequest::create('/approval/hod/off-roster/1', 'POST', [
            'action' => $action,
        ]);
        $this->prepareRequest($request, $user);
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);
        $request->validateResolved();

        return $request;
    }

    private function plainRequest(User $user, array $payload): Request
    {
        $request = Request::create('/roster-off/effective-dates', 'GET', $payload);
        $this->prepareRequest($request, $user);

        return $request;
    }

    private function prepareRequest(Request $request, User $user): void
    {
        $request->setUserResolver(fn($guard = null) => $user);
        $session = $this->app['session']->driver();
        $session->start();
        $request->setLaravelSession($session);
        $this->be($user);
        $this->app->instance('request', $request);
    }
}
