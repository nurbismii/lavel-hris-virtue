<?php

namespace App\Jobs;

use App\Models\Employee;
use App\Models\User;
use App\Notifications\StatusPengajuanNotification;
use App\Services\Karyawan\EmployeeMediaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

class ProcessEmployeeMediaZipUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 300;
    public $failOnTimeout = true;

    protected $zipPath;
    protected $mediaType;
    protected $uploaderId;
    protected $offset;
    protected $importId;

    public function __construct(string $zipPath, string $mediaType, string $uploaderId, int $offset = 0, ?string $importId = null)
    {
        $this->zipPath = $zipPath;
        $this->mediaType = $mediaType;
        $this->uploaderId = $uploaderId;
        $this->offset = $offset;
        $this->importId = $importId ?: (string) Str::uuid();
        $this->onQueue(config('queue.connections.' . config('queue.default') . '.queue', 'default'));
        $this->bootstrapSummaryFile();
    }

    public function handle(EmployeeMediaService $mediaService)
    {
        $uploader = User::find($this->uploaderId);
        $summary = $this->defaultSummary($this->loadSummary());
        $storage = $this->importStorage();

        if (!$storage->exists($this->zipPath)) {
            $summary['status'] = 'failed';
            $summary['skipped_count'] = 1;
            $summary['items'] = [[
                'status' => 'skip',
                'file' => basename($this->zipPath),
                'message' => 'File ZIP tidak ditemukan di server.',
            ]];
            $summary['finished_at'] = now()->toIso8601String();
            $this->persistSummary($summary);

            $this->notifyResult($uploader, $this->toPublicSummary($summary), true);

            return;
        }

        $zipAbsolutePath = $storage->path($this->zipPath);
        $zip = new ZipArchive();
        $zipOpened = $zip->open($zipAbsolutePath);

        if ($zipOpened !== true) {
            $storage->delete($this->zipPath);
            $summary['status'] = 'failed';
            $summary['skipped_count'] = 1;
            $summary['items'] = [[
                'status' => 'skip',
                'file' => basename($this->zipPath),
                'message' => 'ZIP tidak dapat dibuka atau rusak.',
            ]];
            $summary['finished_at'] = now()->toIso8601String();
            $this->persistSummary($summary);

            $this->notifyResult($uploader, $this->toPublicSummary($summary), true);

            return;
        }

        $tempDirectory = storage_path('app/temp/employee-media-imports/' . Str::uuid());
        $allowedExtensions = $mediaService->getAllowedExtensions($this->mediaType);
        $column = $mediaService->getColumnForType($this->mediaType);
        $startIndex = max(0, $this->offset);
        $chunkSize = max(1, (int) env('EMPLOYEE_MEDIA_ZIP_CHUNK_SIZE', 5));
        $zipEntriesCount = max(0, (int) $zip->numFiles);
        $summary['total_entries'] = max(0, (int) ($summary['total_entries'] ?? 0));

        if ($summary['total_entries'] === 0) {
            $summary['total_entries'] = $this->countValidZipEntries($zip);
        }

        $summary['status'] = 'processing';
        $summary['updated_at'] = now()->toIso8601String();
        $this->persistSummary($summary);

        $endIndex = min($zipEntriesCount, $startIndex + $chunkSize);
        $totalEntries = (int) $summary['total_entries'];

        if (!File::exists($tempDirectory)) {
            File::makeDirectory($tempDirectory, 0755, true);
        }

        Log::info('Employee ZIP media import chunk started.', [
            'media_type' => $this->mediaType,
            'uploader_id' => $this->uploaderId,
            'import_id' => $this->importId,
            'offset' => $this->offset,
            'chunk_size' => $chunkSize,
            'total_entries' => $totalEntries,
        ]);

        try {
            for ($index = $startIndex; $index < $endIndex; $index++) {
                $entryName = $zip->getNameIndex($index);

                if (!$this->isValidZipEntry($entryName)) {
                    continue;
                }

                $basename = pathinfo($entryName, PATHINFO_BASENAME);
                $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));

                if (!in_array($extension, $allowedExtensions, true)) {
                    $summary['skipped_count']++;
                    $this->rememberItem($summary, 'skip', $basename, 'Ekstensi file tidak sesuai untuk jenis upload ini.');
                    $this->persistSummary($summary);
                    continue;
                }

                $nikCandidates = $mediaService->extractNikCandidatesFromFilename($basename);

                if (empty($nikCandidates)) {
                    $summary['skipped_count']++;
                    $this->rememberItem($summary, 'skip', $basename, 'NIK tidak dikenali dari nama file.');
                    $this->persistSummary($summary);
                    continue;
                }

                $employee = $this->findEmployeeForUpload($nikCandidates, $column, $uploader);

                if (!$employee) {
                    $summary['skipped_count']++;
                    $this->rememberItem($summary, 'skip', $basename, "Karyawan dengan NIK {$nikCandidates[0]} tidak ditemukan atau di luar scope.");
                    $this->persistSummary($summary);
                    continue;
                }

                $nik = (string) $employee->nik;

                if (isset($summary['processed_niks'][$nik])) {
                    $summary['skipped_count']++;
                    $this->rememberItem($summary, 'skip', $basename, "Duplikat file untuk NIK {$nik} dalam ZIP yang sama.");
                    $this->persistSummary($summary);
                    continue;
                }

                $temporaryFile = $this->extractZipEntryToTemporaryFile($zip, $entryName, $tempDirectory, $extension);

                if (!$temporaryFile) {
                    $summary['skipped_count']++;
                    $this->rememberItem($summary, 'skip', $basename, 'File di dalam ZIP tidak dapat diekstrak.');
                    $this->persistSummary($summary);
                    continue;
                }

                try {
                    $uploadedFile = new UploadedFile(
                        $temporaryFile,
                        $basename,
                        mime_content_type($temporaryFile) ?: null,
                        null,
                        true
                    );

                    $replacedExisting = false;
                    $path = $mediaService->storeUploadedFile($employee, $uploadedFile, $this->mediaType, false, $replacedExisting);
                    $employee->forceFill([$column => $path])->save();
                    $summary['processed_niks'][$nik] = true;
                    $summary['success_count']++;
                    $message = $replacedExisting
                        ? "File lama untuk {$employee->nama_karyawan} ({$employee->nik}) berhasil ditimpa."
                        : "Berhasil dipasangkan ke {$employee->nama_karyawan} ({$employee->nik}).";
                    $this->rememberItem($summary, 'success', $basename, $message);
                } catch (Throwable $exception) {
                    $summary['skipped_count']++;
                    $this->rememberItem($summary, 'skip', $basename, 'Gagal menyimpan file ke data karyawan.');

                    Log::warning('Employee ZIP media import failed for file.', [
                        'media_type' => $this->mediaType,
                        'import_id' => $this->importId,
                        'offset' => $this->offset,
                        'entry_name' => $entryName,
                        'nik' => $nik,
                        'error' => $exception->getMessage(),
                    ]);
                } finally {
                    $this->persistSummary($summary);

                    if (File::exists($temporaryFile)) {
                        File::delete($temporaryFile);
                    }
                }
            }
        } finally {
            $zip->close();

            if (File::exists($tempDirectory)) {
                File::deleteDirectory($tempDirectory);
            }
        }

        if ($endIndex < $zipEntriesCount) {
            Log::info('Employee ZIP media import chunk finished.', [
                'media_type' => $this->mediaType,
                'uploader_id' => $this->uploaderId,
                'import_id' => $this->importId,
                'offset' => $this->offset,
                'chunk_size' => $chunkSize,
                'next_offset' => $endIndex,
                'zip_entries_count' => $zipEntriesCount,
                'total_entries' => $totalEntries,
                'success_count' => $summary['success_count'],
                'skipped_count' => $summary['skipped_count'],
            ]);

            $summary['updated_at'] = now()->toIso8601String();
            $this->persistSummary($summary);

            self::dispatch($this->zipPath, $this->mediaType, $this->uploaderId, $endIndex, $this->importId)
                ->onQueue($this->queue);

            return;
        }

        $storage->delete($this->zipPath);
        $summary['status'] = 'completed';
        $summary['updated_at'] = now()->toIso8601String();
        $summary['finished_at'] = now()->toIso8601String();
        $this->persistSummary($summary);
        $publicSummary = $this->toPublicSummary($summary);

        Log::info('Employee ZIP media import finished.', [
            'media_type' => $this->mediaType,
            'uploader_id' => $this->uploaderId,
            'import_id' => $this->importId,
            'success_count' => $publicSummary['success_count'],
            'skipped_count' => $publicSummary['skipped_count'],
            'sample_items' => $publicSummary['items'],
        ]);

        $this->notifyResult($uploader, $publicSummary, false);
    }

    public function failed(Throwable $exception)
    {
        $this->importStorage()->delete($this->zipPath);

        $uploader = User::find($this->uploaderId);
        $summary = $this->defaultSummary($this->loadSummary());
        $summary['status'] = 'failed';
        $summary['updated_at'] = now()->toIso8601String();
        $summary['finished_at'] = now()->toIso8601String();

        Log::error('Employee ZIP media import job failed.', [
            'media_type' => $this->mediaType,
            'uploader_id' => $this->uploaderId,
            'zip_path' => $this->zipPath,
            'import_id' => $this->importId,
            'offset' => $this->offset,
            'error' => $exception->getMessage(),
        ]);

        if (empty($summary['items'])) {
            $summary['items'][] = [
                'status' => 'skip',
                'file' => basename($this->zipPath),
                'message' => 'Proses ZIP gagal dijalankan. Cek log aplikasi.',
            ];
            $summary['skipped_count']++;
        }

        $this->persistSummary($summary);

        $this->notifyResult($uploader, $this->toPublicSummary($summary), true);
    }

    protected function isValidZipEntry(?string $entryName): bool
    {
        if (!$entryName) {
            return false;
        }

        $normalized = str_replace('\\', '/', $entryName);

        if (Str::endsWith($normalized, '/')) {
            return false;
        }

        if (Str::startsWith($normalized, '__MACOSX/')) {
            return false;
        }

        $basename = pathinfo($normalized, PATHINFO_BASENAME);

        if ($basename === '' || Str::startsWith($basename, '.')) {
            return false;
        }

        return true;
    }

    protected function extractZipEntryToTemporaryFile(ZipArchive $zip, string $entryName, string $directory, string $extension): ?string
    {
        $stream = $zip->getStream($entryName);

        if (!$stream) {
            return null;
        }

        $temporaryFile = $directory . DIRECTORY_SEPARATOR . Str::uuid() . '.' . $extension;
        $output = fopen($temporaryFile, 'wb');

        if (!$output) {
            fclose($stream);
            return null;
        }

        stream_copy_to_stream($stream, $output);
        fclose($stream);
        fclose($output);

        return $temporaryFile;
    }

    protected function rememberItem(array &$summary, string $status, string $file, string $message): void
    {
        if (count($summary['items']) >= 20) {
            return;
        }

        $summary['items'][] = [
            'status' => $status,
            'file' => $file,
            'message' => $message,
        ];
    }

    protected function findEmployeeForUpload(array $nikCandidates, string $column, ?User $uploader): ?Employee
    {
        $query = Employee::query()
            ->select(['nik', 'nama_karyawan', $column])
            ->whereIn('nik', $nikCandidates);

        if ($uploader) {
            $uploader->applyEmployeeScope($query);
        }

        $employees = $query->get()->keyBy(fn(Employee $employee) => (string) $employee->nik);

        foreach ($nikCandidates as $candidate) {
            if ($employees->has($candidate)) {
                return $employees->get($candidate);
            }
        }

        return null;
    }

    protected function loadSummary(): array
    {
        $summaryPath = $this->summaryStoragePath();

        if (!$this->importStorage()->exists($summaryPath)) {
            return [];
        }

        $contents = $this->importStorage()->get($summaryPath);
        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    protected function persistSummary(array $summary): void
    {
        $summary['updated_at'] = $summary['updated_at'] ?? now()->toIso8601String();

        $this->importStorage()->put($this->summaryStoragePath(), json_encode($summary));
    }

    protected function deleteSummary(): void
    {
        $this->importStorage()->delete($this->summaryStoragePath());
    }

    protected function summaryStoragePath(): string
    {
        return 'employee-zip-imports/status/' . $this->importId . '.json';
    }

    protected function toPublicSummary(array $summary): array
    {
        unset($summary['processed_niks']);

        return $summary;
    }

    protected function defaultSummary(array $overrides = []): array
    {
        return array_merge([
            'import_id' => $this->importId,
            'uploader_id' => $this->uploaderId,
            'media_type' => $this->mediaType,
            'status' => 'queued',
            'success_count' => 0,
            'skipped_count' => 0,
            'total_entries' => 0,
            'items' => [],
            'processed_niks' => [],
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'finished_at' => null,
        ], $overrides);
    }

    protected function bootstrapSummaryFile(): void
    {
        if ($this->importStorage()->exists($this->summaryStoragePath())) {
            return;
        }

        $this->persistSummary($this->defaultSummary());
    }

    protected function countValidZipEntries(ZipArchive $zip): int
    {
        $count = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            if ($this->isValidZipEntry($zip->getNameIndex($index))) {
                $count++;
            }
        }

        return $count;
    }

    protected function importStorage()
    {
        return Storage::disk(config('filesystems.employee_import_disk', config('filesystems.default')));
    }

    protected function notifyResult(?User $uploader, array $summary, bool $failed): void
    {
        if (!$uploader) {
            return;
        }

        $isFaceReference = $this->mediaType === 'face_reference';
        $label = $this->mediaLabel();
        $title = $failed
            ? "Bulk upload {$label} gagal"
            : "Bulk upload {$label} selesai";
        $message = $failed
            ? "ZIP {$label} gagal diproses. Berhasil {$summary['success_count']} file, dilewati {$summary['skipped_count']} file."
            : "ZIP {$label} selesai diproses. Berhasil {$summary['success_count']} file, dilewati {$summary['skipped_count']} file.";

        if (!empty($summary['items'])) {
            $sampleMessages = collect($summary['items'])
                ->pluck('message')
                ->filter()
                ->unique()
                ->take(2)
                ->implode(' | ');

            if ($sampleMessages !== '') {
                $message .= ' Info: ' . $sampleMessages;
            }
        }

        $uploader->notify(new StatusPengajuanNotification([
            'judul' => $title,
            'pesan' => $message,
            'url' => $isFaceReference
                ? route('set-kehadiran.index', [], false)
                : route('karyawan.index', [], false),
            'tipe' => 'bulk_upload',
        ]));
    }

    protected function mediaLabel(): string
    {
        switch ($this->mediaType) {
            case 'photo':
                return 'foto karyawan';
            case 'ktp':
                return 'KTP';
            case 'kk':
                return 'KK';
            case 'sim':
                return 'SIM';
            case 'sio':
                return 'SIO';
            case 'face_reference':
                return 'foto referensi presensi';
            default:
                return $this->mediaType;
        }
    }
}
