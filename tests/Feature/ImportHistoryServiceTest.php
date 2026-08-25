<?php

namespace Tests\Feature;

use App\Exports\ImportHistoryItemsExport;
use App\Models\ImportHistory;
use App\Models\ImportHistoryItem;
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
        ]], [], 5, 3, [
            [
                'category' => ImportHistoryItem::CATEGORY_SKIPPED,
                'row' => 4,
                'nik' => 'EMP001',
                'message' => 'Duplikat dalam file.',
                'payload' => ['nik' => 'EMP001', 'nama_karyawan' => 'Karyawan A'],
            ],
            [
                'category' => ImportHistoryItem::CATEGORY_UPDATED,
                'row' => 5,
                'nik' => 'EMP002',
                'message' => 'Data diperbarui.',
                'payload' => ['nik' => 'EMP002', 'nama_karyawan' => 'Karyawan B'],
            ],
        ]);
        $service->markCompleted($history->id);

        $history = $history->fresh();

        $this->assertSame(ImportHistory::STATUS_COMPLETED_WITH_ERRORS, $history->status);
        $this->assertSame(10, $history->total_rows);
        $this->assertSame(8, $history->success_count);
        $this->assertSame(2, $history->skipped_count);
        $this->assertSame(5, $history->inserted_count);
        $this->assertSame(3, $history->updated_count);
        $this->assertCount(1, $history->failure_samples);
        $this->assertDatabaseHas('import_history_items', [
            'import_history_id' => $history->id,
            'category' => ImportHistoryItem::CATEGORY_SKIPPED,
            'row_number' => 4,
            'nik' => 'EMP001',
        ]);
        $this->assertDatabaseHas('import_history_items', [
            'import_history_id' => $history->id,
            'category' => ImportHistoryItem::CATEGORY_UPDATED,
            'row_number' => 5,
            'nik' => 'EMP002',
            'employee_name' => 'Karyawan B',
        ]);
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
        $this->assertDatabaseHas('import_history_items', [
            'import_history_id' => $history->id,
            'category' => ImportHistoryItem::CATEGORY_FAILED,
            'message' => 'File tidak dapat dibaca.',
        ]);
    }

    public function test_full_detail_items_are_not_limited_to_display_samples(): void
    {
        $service = app(ImportHistoryService::class);
        $history = $service->createQueued([
            'import_type' => ImportHistory::TYPE_EMPLOYEE,
            'source' => ImportHistory::SOURCE_EXCEL,
            'file_name' => 'employees.xlsx',
            'created_by' => 'user-1',
        ]);
        $samples = [];
        $details = [];

        for ($index = 1; $index <= 75; $index++) {
            $samples[] = [
                'status' => 'skip',
                'row' => $index + 1,
                'nik' => 'EMP' . $index,
                'message' => 'Data dilewati.',
            ];
            $details[] = [
                'category' => ImportHistoryItem::CATEGORY_SKIPPED,
                'row' => $index + 1,
                'nik' => 'EMP' . $index,
                'message' => 'Data dilewati.',
                'payload' => ['nama_karyawan' => 'Karyawan ' . $index],
            ];
        }

        $service->addChunkResult($history->id, 75, 0, 75, 0, $samples, [], 0, 0, $details);
        $history = $history->fresh();

        $this->assertCount(50, $history->failure_samples);
        $this->assertSame(75, $history->items()->where('category', ImportHistoryItem::CATEGORY_SKIPPED)->count());

        $export = new ImportHistoryItemsExport($history, ImportHistoryItem::CATEGORY_SKIPPED);
        $exportRows = iterator_to_array($export->generator());

        $this->assertCount(75, $exportRows);
        $this->assertContains('Nama Karyawan', $export->headings());
        $this->assertSame('Karyawan 1', $export->map($exportRows[0])[3]);
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

        Schema::create('import_history_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('import_history_id');
            $table->string('category', 20);
            $table->unsignedInteger('row_number')->nullable();
            $table->string('nik', 100)->nullable();
            $table->string('employee_name', 255)->nullable();
            $table->string('file_name', 255)->nullable();
            $table->string('message', 500)->nullable();
            $table->longText('payload')->nullable();
            $table->timestamps();
        });
    }
}
