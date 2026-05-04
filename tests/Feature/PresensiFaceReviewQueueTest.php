<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PresensiController;
use App\Models\Employee;
use App\Models\Presensi;
use App\Models\PresensiVerification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PresensiFaceReviewQueueTest extends TestCase
{
    private const HR_USER_ID = 'f2a2b70c8dbf4f99a0d9876b4774b3fb';

    private array $selfieFiles = [];

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

        $this->createSchema();
        $this->seedOrganization();
    }

    protected function tearDown(): void
    {
        foreach ($this->selfieFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        parent::tearDown();
    }

    public function test_face_review_queue_lists_pending_and_rejected_with_evidence(): void
    {
        $selfiePath = $this->createSelfieFile('EMP001');
        $this->seedPresensiRow(1, 'EMP001', Presensi::STATUS_ABSEN_PENDING_REVIEW, $selfiePath);
        $this->seedVerificationRow(1, 1, 'EMP001', Presensi::STATUS_ABSEN_PENDING_REVIEW, $selfiePath);
        $this->seedPresensiRow(2, 'EMP002', Presensi::STATUS_ABSEN_REJECTED, null);
        $this->seedVerificationRow(2, 2, 'EMP002', Presensi::STATUS_ABSEN_REJECTED, null);
        $this->seedPresensiRow(3, 'EMP003', Presensi::STATUS_ABSEN_VERIFIED, null);
        $this->seedVerificationRow(3, 3, 'EMP003', Presensi::STATUS_ABSEN_VERIFIED, null);

        DB::table('log_presensi')->insert([
            'nik_karyawan' => 'EMP001',
            'tanggal' => '2026-05-01',
            'lat' => -4.101,
            'long' => 122.501,
            'accuracy' => 12,
            'speed' => 0,
            'ip_address' => '10.0.0.10',
            'user_agent' => 'Feature Test Browser',
            'created_at' => '2026-05-01 08:00:30',
            'updated_at' => '2026-05-01 08:00:30',
        ]);

        $view = app(PresensiController::class)->faceReview(
            $this->scopedRequest($this->makeHrUser())
        );

        $this->assertSame('admin.presensi.face-review', $view->name());

        $verifications = $view->getData()['verifications']->getCollection();
        $this->assertSame([
            Presensi::STATUS_ABSEN_PENDING_REVIEW,
            Presensi::STATUS_ABSEN_REJECTED,
        ], $verifications->pluck('status')->values()->all());

        $pending = $verifications->firstWhere('nik_karyawan', 'EMP001');
        $this->assertTrue($pending->selfie_available);
        $this->assertSame('-4.101', (string) $pending->gps_log->lat);
        $this->assertSame(1, $view->getData()['summary']['pending']);
        $this->assertSame(1, $view->getData()['summary']['rejected']);
        $this->assertSame(1, $view->getData()['summary']['verified']);
    }

    public function test_hr_decision_approves_pending_review_and_updates_current_presensi(): void
    {
        $selfiePath = $this->createSelfieFile('EMP001');
        $this->seedPresensiRow(1, 'EMP001', Presensi::STATUS_ABSEN_PENDING_REVIEW, $selfiePath);
        $this->seedVerificationRow(1, 1, 'EMP001', Presensi::STATUS_ABSEN_PENDING_REVIEW, $selfiePath);

        $request = $this->scopedRequest($this->makeHrUser(), [
            'decision' => PresensiVerification::REVIEW_APPROVED,
            'review_note' => 'Selfie dan lokasi sesuai.',
        ], 'POST');

        app(PresensiController::class)->decideFaceReview(
            $request,
            PresensiVerification::query()->findOrFail(1)
        );

        $verification = DB::table('presensi_verifications')->where('id', 1)->first();
        $presensi = DB::table('absensis')->where('id', 1)->first();

        $this->assertSame(Presensi::STATUS_ABSEN_VERIFIED, $verification->status);
        $this->assertSame(PresensiVerification::REVIEW_APPROVED, $verification->review_decision);
        $this->assertSame('Selfie dan lokasi sesuai.', $verification->review_note);
        $this->assertSame(self::HR_USER_ID, $verification->reviewed_by);
        $this->assertSame(Presensi::STATUS_ABSEN_VERIFIED, $presensi->status_absen);
        $this->assertSame(1, (int) $presensi->face_verified);
        $this->assertSame('hr-manual-review', $presensi->face_verification_method);
        $this->assertStringContainsString('"hr_review"', $presensi->face_verification_meta);
        $this->assertSame('HR Reviewer', PresensiVerification::with('reviewer')->findOrFail(1)->reviewer->name);
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('name');
            $table->string('email')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('nik_karyawan')->nullable();
            $table->timestamps();
        });

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
            $table->string('area_kerja')->nullable();
            $table->unsignedInteger('departemen_id')->nullable();
            $table->unsignedInteger('divisi_id')->nullable();
            $table->string('status_resign')->nullable();
        });

        Schema::create('absensis', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nik_karyawan');
            $table->date('tanggal');
            $table->dateTime('jam_masuk')->nullable();
            $table->string('status_absen')->nullable();
            $table->boolean('face_verified')->default(false);
            $table->timestamp('face_verified_at')->nullable();
            $table->string('face_verification_method')->nullable();
            $table->text('face_verification_meta')->nullable();
            $table->string('face_selfie_hash', 64)->nullable();
            $table->string('presensi_challenge_id', 80)->nullable();
            $table->string('device_info')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('presensi_verifications', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('presensi_id');
            $table->string('nik_karyawan');
            $table->date('tanggal');
            $table->string('attendance_type');
            $table->string('status');
            $table->boolean('face_verified')->default(false);
            $table->string('face_selfie_path')->nullable();
            $table->string('face_selfie_hash', 64)->nullable();
            $table->decimal('face_verification_distance', 8, 6)->nullable();
            $table->timestamp('face_verified_at')->nullable();
            $table->string('face_verification_method')->nullable();
            $table->text('face_verification_meta')->nullable();
            $table->string('presensi_challenge_id', 80)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->string('review_decision', 32)->nullable();
            $table->text('review_note')->nullable();
            $table->string('reviewed_by', 36)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('log_presensi', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nik_karyawan');
            $table->date('tanggal')->nullable();
            $table->decimal('lat', 10, 6)->nullable();
            $table->decimal('long', 10, 6)->nullable();
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->decimal('speed', 8, 2)->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    private function seedOrganization(): void
    {
        DB::table('users')->insert([
            'id' => self::HR_USER_ID,
            'name' => 'HR Reviewer',
            'email' => 'hr@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('departemens')->insert([
            'id' => 10,
            'departemen' => 'Produksi',
        ]);

        DB::table('divisis')->insert([
            'id' => 101,
            'nama_divisi' => 'Smelter A',
            'departemen_id' => 10,
        ]);

        DB::table('employees')->insert([
            ['nik' => 'EMP001', 'nama_karyawan' => 'Budi Operator', 'area_kerja' => 'VDNI', 'departemen_id' => 10, 'divisi_id' => 101, 'status_resign' => 'AKTIF'],
            ['nik' => 'EMP002', 'nama_karyawan' => 'Sari Operator', 'area_kerja' => 'VDNI', 'departemen_id' => 10, 'divisi_id' => 101, 'status_resign' => 'AKTIF'],
            ['nik' => 'EMP003', 'nama_karyawan' => 'Rina Operator', 'area_kerja' => 'VDNI', 'departemen_id' => 10, 'divisi_id' => 101, 'status_resign' => 'AKTIF'],
        ]);
    }

    private function seedPresensiRow(int $id, string $nik, string $status, ?string $selfiePath): void
    {
        DB::table('absensis')->insert([
            'id' => $id,
            'nik_karyawan' => $nik,
            'tanggal' => '2026-05-01',
            'jam_masuk' => '2026-05-01 08:00:00',
            'status_absen' => $status,
            'face_verified' => $status === Presensi::STATUS_ABSEN_VERIFIED,
            'face_verification_method' => 'server-face',
            'face_verification_meta' => json_encode(['distance' => 0.41]),
            'face_selfie_hash' => 'hash-' . $id,
            'presensi_challenge_id' => 'challenge-' . $id,
            'device_info' => 'Android Chrome',
            'ip_address' => '10.0.0.' . $id,
            'user_agent' => 'Feature Test User Agent',
            'created_at' => '2026-05-01 08:00:00',
            'updated_at' => '2026-05-01 08:00:00',
        ]);
    }

    private function seedVerificationRow(int $id, int $presensiId, string $nik, string $status, ?string $selfiePath): void
    {
        DB::table('presensi_verifications')->insert([
            'id' => $id,
            'presensi_id' => $presensiId,
            'nik_karyawan' => $nik,
            'tanggal' => '2026-05-01',
            'attendance_type' => PresensiVerification::TYPE_MASUK,
            'status' => $status,
            'face_verified' => $status === Presensi::STATUS_ABSEN_VERIFIED,
            'face_selfie_path' => $selfiePath,
            'face_selfie_hash' => 'hash-' . $id,
            'face_verification_distance' => 0.41,
            'face_verification_method' => 'server-face',
            'face_verification_meta' => json_encode([
                'distance' => 0.41,
                'server_face_verification' => ['message' => 'Perlu review HR'],
            ]),
            'presensi_challenge_id' => 'challenge-' . $id,
            'submitted_at' => '2026-05-01 08:00:00',
            'created_at' => '2026-05-01 08:00:00',
            'updated_at' => '2026-05-01 08:00:00',
        ]);
    }

    private function createSelfieFile(string $nik): string
    {
        $relativePath = 'presensi-selfie/' . $nik . '/2026/05/01/test-selfie.jpg';
        $absolutePath = public_path($relativePath);

        if (!is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0777, true);
        }

        file_put_contents($absolutePath, base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2w=='));
        $this->selfieFiles[] = $absolutePath;

        return $relativePath;
    }

    private function scopedRequest(User $user, array $payload = [], string $method = 'GET'): Request
    {
        $request = Request::create('/admin/data-presensi/review-wajah', $method, $payload);
        $request->setUserResolver(fn($guard = null) => $user);
        $session = $this->app['session']->driver();
        $session->start();
        $request->setLaravelSession($session);
        $this->be($user);
        $this->app->instance('request', $request);

        return $request;
    }

    private function makeHrUser(): User
    {
        $user = new User();
        $user->id = self::HR_USER_ID;
        $user->name = 'HR Reviewer';
        $user->email = 'hr@example.test';
        $user->setRelation('role', new Role(['permission_role' => 'HR']));
        $user->setRelation('employee', Employee::query()->whereKey('EMP001')->first());

        return $user;
    }
}
