<?php

namespace Tests\Feature;

use App\Models\ImportHistory;
use App\Services\ImportHistory\ImportHistoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class ImportHistoryServiceTest extends TestCase
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

        $this->createSchema();
    }

    public function test_excel_import_history_records_chunk_and_completion_counts(): void
    {
        $service = app(ImportHistoryService::class);
        $history = $service->createQueued([
            'import_type' => ImportHistory::TYPE_EMPLOYEE,
            'source' => ImportHistory::SOURCE_EXCEL,
            'file_name' => 'employees.xlsx',
            'created_by' => 'user-1',
        ]);

        $service->markProcessing($history->id);
        $service->addChunkResult($history->id, 10, 8, 2, 0, [[
            'status' => 'skip',
            'nik' => 'EMP001',
            'message' => 'Duplikat dalam file.',
        ]], [], 5, 3);
        $service->markCompleted($history->id);

        $history = $history->fresh();

        $this->assertSame(ImportHistory::STATUS_COMPLETED_WITH_ERRORS, $history->status);
        $this->assertSame(10, $history->total_rows);
        $this->assertSame(8, $history->success_count);
        $this->assertSame(2, $history->skipped_count);
        $this->assertSame(5, $history->inserted_count);
        $this->assertSame(3, $history->updated_count);
        $this->assertCount(1, $history->failure_samples);
        $this->assertNotNull($history->started_at);
        $this->assertNotNull($history->finished_at);
    }

    public function test_import_history_marks_fatal_failure(): void
    {
        $service = app(ImportHistoryService::class);
        $history = $service->createQueued([
            'import_type' => ImportHistory::TYPE_RESIGN,
            'source' => ImportHistory::SOURCE_EXCEL,
            'file_name' => 'resign.xlsx',
            'created_by' => 'user-1',
        ]);

        $service->markFailed($history->id, new RuntimeException('File tidak dapat dibaca.'));

        $history = $history->fresh();

        $this->assertSame(ImportHistory::STATUS_FAILED, $history->status);
        $this->assertSame(1, $history->failed_count);
        $this->assertSame('File tidak dapat dibaca.', $history->error_message);
        $this->assertNotNull($history->finished_at);
    }

    public function test_zip_import_history_syncs_absolute_progress_summary(): void
    {
        $service = app(ImportHistoryService::class);
        $history = $service->createQueued([
            'import_type' => ImportHistory::TYPE_EMPLOYEE_PHOTO,
            'source' => ImportHistory::SOURCE_ZIP,
            'file_name' => 'foto.zip',
            'created_by' => 'user-1',
        ]);

        $service->syncMediaSummary($history->id, [
            'status' => 'processing',
            'total_entries' => 3,
            'success_count' => 1,
            'skipped_count' => 1,
            'items' => [
                ['status' => 'success', 'file' => '1001.jpg', 'message' => 'Berhasil.'],
                ['status' => 'skip', 'file' => 'bad.txt', 'message' => 'Ekstensi tidak sesuai.'],
            ],
        ]);

        $history = $history->fresh();

        $this->assertSame(ImportHistory::STATUS_PROCESSING, $history->status);
        $this->assertSame(3, $history->total_rows);
        $this->assertSame(1, $history->success_count);
        $this->assertSame(1, $history->skipped_count);
        $this->assertCount(1, $history->failure_samples);

        $service->syncMediaSummary($history->id, [
            'status' => 'completed',
            'total_entries' => 3,
            'success_count' => 2,
            'skipped_count' => 1,
            'items' => [],
        ]);

        $this->assertSame(ImportHistory::STATUS_COMPLETED_WITH_ERRORS, $history->fresh()->status);
    }

    private function createSchema(): void
    {
        Schema::create('import_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('import_id', 36)->nullable()->unique();
            $table->string('import_type', 80);
            $table->string('module', 80)->nullable();
            $table->string('source', 40)->default('excel');
            $table->string('status', 40)->default('queued');
            $table->string('file_name', 255)->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('disk', 80)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('inserted_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->longText('summary')->nullable();
            $table->longText('failure_samples')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->string('created_by', 36)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }
}
