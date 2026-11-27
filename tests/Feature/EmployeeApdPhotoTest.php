<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeeApdPhotoTest extends TestCase
{
    private const V_HIRE_API = 'https://vhire.example.test/api/internal/candidate-documents';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'services.recruitment.base_url' => 'https://vhire.example.test',
            'services.recruitment.token' => 'synthetic-token',
            'services.recruitment.cache_ttl' => 3,
            'cache.default' => 'array',
            'logging.default' => 'null',
        ]);
        DB::purge('sqlite');
        Schema::create('employees', function (Blueprint $table) {
            $table->string('nik')->primary();
            $table->string('no_ktp');
            $table->string('nama_karyawan');
            $table->unsignedBigInteger('departemen_id')->nullable();
            $table->unsignedBigInteger('divisi_id')->nullable();
            $table->unsignedBigInteger('work_pattern_id')->nullable();
            $table->date('work_pattern_start_date')->nullable();
            $table->unsignedInteger('sisa_cuti')->nullable();
            $table->string('posisi')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('face_reference_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('notifiable_type');
            $table->string('notifiable_id');
            $table->timestamp('read_at')->nullable();
        });

        Employee::create([
            'nik' => 'APD-TEST',
            'no_ktp' => '6400000000000001',
            'nama_karyawan' => 'Karyawan Sintetis',
            'photo_path' => 'employee-documents/APD-TEST/photo/original.jpg',
        ]);

        $this->actingAs(new User([
            'id' => 'synthetic-apd-user',
            'nik_karyawan' => 'APD-TEST',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]))->withHeader('User-Agent', 'Android VPEOPLE_APP/1.0.7');
    }

    public function test_reads_the_current_apd_photo_from_vhire_without_an_upload_form(): void
    {
        $preview = self::V_HIRE_API . '/6400000000000001/pas_foto_apd/preview';
        Http::fake([
            self::V_HIRE_API => Http::sequence()
                ->push(['found' => true, 'documents' => []])
                ->push(['found' => true, 'documents' => [[
                    'type' => 'pas_foto_apd',
                    'mime' => 'image/jpeg',
                    'preview_url' => $preview,
                ]]]),
        ]);

        $url = route('pengaturan-akun.index');
        $this->get($url)->assertOk()->assertDontSee('Pas Foto APD dari V-Hire');
        $this->get($url)->assertOk()
            ->assertSee('Pas Foto APD dari V-Hire')
            ->assertSee($preview)
            ->assertDontSee('name="pas_foto_apd"', false);

        $this->assertFalse(Route::has('pengaturan-akun.apd-photo.update'));
        $this->assertSame('employee-documents/APD-TEST/photo/original.jpg', Employee::findOrFail('APD-TEST')->photo_path);
        Http::assertSentCount(2);
        Http::assertSent(function (Request $request) {
            return $request->url() === self::V_HIRE_API
                && $request->hasHeader('Authorization', 'Bearer synthetic-token')
                && $request['no_ktp'] === '6400000000000001';
        });
    }

    public function test_profile_can_still_be_opened_when_vhire_is_unavailable(): void
    {
        Http::fake([self::V_HIRE_API => Http::response([], 503)]);
        $this->get(route('pengaturan-akun.index'))->assertOk()->assertDontSee('Pas Foto APD dari V-Hire');
    }

    public function test_employee_documents_show_a_new_apd_photo_on_reopen(): void
    {
        auth()->user()->setRelation('role', new Role([
            'permission_role' => 'HR',
            'menu_permissions' => ['data_karyawan'],
        ]));

        $photo = [
            'type' => 'pas_foto_apd',
            'label' => 'Pas Foto APD',
            'mime' => 'image/png',
            'preview_url' => self::V_HIRE_API . '/6400000000000001/pas_foto_apd/preview',
            'download_url' => self::V_HIRE_API . '/6400000000000001/pas_foto_apd/download',
        ];
        Http::fake([
            self::V_HIRE_API => Http::sequence()
                ->push(['found' => true, 'documents' => []])
                ->push(['found' => true, 'documents' => [$photo]]),
        ]);

        $url = route('karyawan.recruitment-documents', 'APD-TEST');
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'documents');
        $this->getJson($url)->assertOk()->assertJsonFragment($photo);

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request['no_ktp'] === '6400000000000001'
            && $request->hasHeader('Authorization', 'Bearer synthetic-token'));
    }

    public function test_employee_documents_respect_employee_scope_before_requesting_vhire(): void
    {
        $manager = auth()->user();
        $manager->setRelation('role', new Role([
            'permission_role' => 'Manager',
            'menu_permissions' => ['data_karyawan'],
        ]));
        $manager->setRelation('employee', new Employee(['departemen_id' => 2]));
        Http::fake();

        $this->getJson(route('karyawan.recruitment-documents', 'APD-TEST'))->assertNotFound();

        Http::assertNothingSent();
    }
}
