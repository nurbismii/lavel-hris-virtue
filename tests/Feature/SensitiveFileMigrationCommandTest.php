<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SensitiveFileMigrationCommandTest extends TestCase
{
    private string $testPrefix = 'employee-documents/test-private-migration';

    protected function tearDown(): void
    {
        File::deleteDirectory(public_path($this->testPrefix));
        File::deleteDirectory(storage_path('app/private/' . $this->testPrefix));

        parent::tearDown();
    }

    public function test_dry_run_does_not_copy_files(): void
    {
        $relativePath = $this->createPublicFixture('dry-run.txt');

        $this->artisan('sensitive-files:migrate-private', [
            '--prefix' => [$this->testPrefix],
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertFileExists(public_path($relativePath));
        $this->assertFileDoesNotExist(storage_path('app/private/' . $relativePath));
    }

    public function test_command_copies_public_sensitive_files_to_private_storage_without_deleting_by_default(): void
    {
        $relativePath = $this->createPublicFixture('ktp/example.txt');

        $this->artisan('sensitive-files:migrate-private', [
            '--prefix' => [$this->testPrefix],
        ])->assertExitCode(0);

        $this->assertFileExists(public_path($relativePath));
        $this->assertFileExists(storage_path('app/private/' . $relativePath));
        $this->assertSame(
            File::get(public_path($relativePath)),
            File::get(storage_path('app/private/' . $relativePath))
        );
    }

    public function test_command_deletes_public_copy_only_when_requested(): void
    {
        $relativePath = $this->createPublicFixture('kk/delete-after-copy.txt');

        $this->artisan('sensitive-files:migrate-private', [
            '--prefix' => [$this->testPrefix],
            '--delete-public' => true,
        ])->assertExitCode(0);

        $this->assertFileDoesNotExist(public_path($relativePath));
        $this->assertFileExists(storage_path('app/private/' . $relativePath));
    }

    private function createPublicFixture(string $filename): string
    {
        $relativePath = $this->testPrefix . '/' . $filename;

        File::ensureDirectoryExists(dirname(public_path($relativePath)), 0755, true);
        File::put(public_path($relativePath), 'sensitive-test-content');

        return $relativePath;
    }
}
