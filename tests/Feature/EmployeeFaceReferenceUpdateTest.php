<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class EmployeeFaceReferenceUpdateTest extends TestCase
{
    private $originalStoragePath;
    private $testStoragePath;
    private $oldPath = 'face-reference/FACE-TEST/old.jpg';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        config()->set('app.employee_media_server_side_compression', false);
        config()->set('logging.default', 'null');
        DB::purge('sqlite');
        Schema::create('employees', function (Blueprint $table) {
            $table->string('nik')->primary();
            $table->string('nama_karyawan');
            $table->string('face_reference_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        $this->originalStoragePath = storage_path();
        $this->testStoragePath = storage_path('framework/testing/face-reference-' . Str::uuid());
        $this->app->useStoragePath($this->testStoragePath);
        File::ensureDirectoryExists(storage_path('app/private/face-reference/FACE-TEST'));
        $image = UploadedFile::fake()->image('old.jpg', 240, 240);
        file_put_contents($this->absolutePath($this->oldPath), file_get_contents($image->getRealPath()));
        Employee::create(['nik' => 'FACE-TEST', 'nama_karyawan' => 'Pegawai Sintetis', 'face_reference_path' => $this->oldPath]);
    }

    protected function tearDown(): void
    {
        $expectedRoot = str_replace('\\', '/', $this->originalStoragePath . '/framework/testing/face-reference-');
        if ($this->testStoragePath && strpos(str_replace('\\', '/', $this->testStoragePath), $expectedRoot) === 0) {
            File::deleteDirectory($this->testStoragePath);
        }
        $this->app->useStoragePath($this->originalStoragePath);
        parent::tearDown();
    }

    private function absolutePath(string $path): string
    {
        return storage_path('app/private/' . $path);
    }

    private function administrator(): User
    {
        return new class(['id' => 'synthetic-hr']) extends User {
            public function canAccessAllEmployees(): bool { return true; }
        };
    }

    private function employeeUser(Employee $employee): User
    {
        $user = new class(['id' => 'synthetic-employee', 'nik_karyawan' => $employee->nik]) extends User {
            public function canAccessAllEmployees(): bool { return false; }
            public function isDepartmentScopedRole(): bool { return false; }
            public function isDivisionScopedRole(): bool { return false; }
        };
        $user->setRelation('employee', $employee);
        return $user;
    }

    public function test_replacing_a_reference_updates_the_database_and_both_preview_endpoints(): void
    {
        $file = UploadedFile::fake()->image('new-reference.jpg', 480, 640);
        $newBytes = file_get_contents($file->getRealPath());
        $this->actingAs($this->administrator())->post(route('karyawan.update', 'FACE-TEST'), [
            '_method' => 'PUT', 'nama_karyawan' => 'Pegawai Sintetis', 'face_reference' => $file,
        ])->assertRedirect(route('karyawan.index'))->assertSessionHasNoErrors();

        $employee = Employee::findOrFail('FACE-TEST');
        $this->assertNotSame($this->oldPath, $employee->face_reference_path);
        $this->assertFileDoesNotExist($this->absolutePath($this->oldPath));
        $this->assertSame($newBytes, file_get_contents($this->absolutePath($employee->face_reference_path)));
        $this->assertSame('success', json_decode(session('alert.config'), true)['icon']);
        $this->assertStringContainsString('Buka ulang halaman presensi', json_decode(session('alert.config'), true)['title']);

        $preview = $this->get(route('karyawan.documents.preview', ['nik' => 'FACE-TEST', 'type' => 'face_reference']))->assertOk();
        $this->assertSame($newBytes, file_get_contents($preview->baseResponse->getFile()->getPathname()));
        $this->actingAs($this->employeeUser($employee));
        $reference = $this->get(route('presensi.face-reference'))->assertOk();
        $this->assertSame($newBytes, file_get_contents($reference->baseResponse->getFile()->getPathname()));
    }

    public function test_reference_preview_and_download_responses_cannot_be_reused_from_cache(): void
    {
        $this->actingAs($this->administrator());
        $urls = [
            route('karyawan.documents.preview', ['nik' => 'FACE-TEST', 'type' => 'face_reference']),
            route('karyawan.documents.download', ['nik' => 'FACE-TEST', 'type' => 'face_reference']),
        ];
        foreach ($urls as $url) {
            $response = $this->get($url)->assertOk();
            $this->assertTrue($response->headers->hasCacheControlDirective('private'));
            $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
            $this->assertFalse($response->headers->hasCacheControlDirective('public'));
        }
        $this->actingAs($this->employeeUser(Employee::findOrFail('FACE-TEST')));
        $response = $this->get(route('presensi.face-reference'))->assertOk();
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }

    public function test_both_views_change_the_reference_url_after_a_new_file_is_saved(): void
    {
        $employee = Employee::findOrFail('FACE-TEST');
        $this->actingAs($this->employeeUser($employee));
        foreach (['admin.karyawan.edit' => 'faceReferencePreviewUrl', 'user.presensi.index' => 'faceReferenceUrl'] as $view => $variable) {
            $source = file_get_contents(resource_path('views/' . str_replace('.', '/', $view) . '.blade.php'));
            preg_match('/@php\s[\s\S]*?@endphp/', $source, $block);
            $this->assertNotEmpty($block);
            $employee->face_reference_path = $this->oldPath;
            $first = Blade::render("\n" . $block[0] . "\n{{ $" . $variable . " }}\n", ['employee' => $employee, 'errors' => new ViewErrorBag()]);
            $employee->face_reference_path = 'face-reference/FACE-TEST/new.jpg';
            $second = Blade::render("\n" . $block[0] . "\n{{ $" . $variable . " }}\n", ['employee' => $employee, 'errors' => new ViewErrorBag()]);
            $this->assertStringContainsString('v=', $first);
            $this->assertNotSame($first, $second);
        }
    }

    public function test_failed_and_incomplete_writes_keep_the_old_reference_and_report_failure(): void
    {
        config()->set('app.employee_media_server_side_compression', true);
        $writes = 0;
        File::partialMock()->shouldReceive('put')->twice()->andReturnUsing(function ($path, $bytes) use (&$writes) {
            if (++$writes === 1) { return false; }
            return file_put_contents($path, substr($bytes, 0, 16));
        });
        foreach ([1, 2] as $attempt) {
            $this->actingAs($this->administrator())->from('/synthetic-edit')->post(route('karyawan.update', 'FACE-TEST'), [
                '_method' => 'PUT', 'nama_karyawan' => 'Pegawai Sintetis',
                'face_reference' => UploadedFile::fake()->image('new.jpg', 480, 640)->size(3000),
            ])->assertRedirect('/synthetic-edit');
            $this->assertSame($this->oldPath, Employee::findOrFail('FACE-TEST')->face_reference_path);
            $this->assertFileExists($this->absolutePath($this->oldPath));
            $this->assertSame('error', json_decode(session('alert.config'), true)['icon']);
            $this->assertCount(1, File::files(storage_path('app/private/face-reference/FACE-TEST')));
        }
    }

    public function test_invalid_upload_cannot_replace_the_reference(): void
    {
        $this->actingAs($this->administrator())->post(route('karyawan.update', 'FACE-TEST'), [
            '_method' => 'PUT', 'nama_karyawan' => 'Pegawai Sintetis',
            'face_reference' => UploadedFile::fake()->create('invalid.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('face_reference');
        $this->assertSame($this->oldPath, Employee::findOrFail('FACE-TEST')->face_reference_path);
        $this->assertFileExists($this->absolutePath($this->oldPath));
    }

    public function test_an_employee_outside_the_users_scope_cannot_be_changed(): void
    {
        $user = $this->employeeUser(new Employee(['nik' => 'OTHER-TEST']));
        $this->actingAs($user)->post(route('karyawan.update', 'FACE-TEST'), [
            '_method' => 'PUT', 'nama_karyawan' => 'Pegawai Sintetis',
            'face_reference' => UploadedFile::fake()->image('other.jpg'),
        ])->assertNotFound();
        $this->get(route('karyawan.documents.preview', ['nik' => 'FACE-TEST', 'type' => 'face_reference', 'v' => 'other-version']))->assertNotFound();
        $this->get(route('karyawan.documents.download', ['nik' => 'FACE-TEST', 'type' => 'face_reference', 'v' => 'other-version']))->assertNotFound();
        $this->assertSame($this->oldPath, Employee::findOrFail('FACE-TEST')->face_reference_path);
        $this->assertFileExists($this->absolutePath($this->oldPath));
    }
}
