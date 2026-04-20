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
    }

    public function handle(EmployeeMediaService $mediaService)
    {
        $uploader = User::find($this->uploaderId);

        if (!Storage::exists($this->zipPath)) {
            $this->notifyResult($uploader, [
                'success_count' => 0,
                'skipped_count' => 1,
                'items' => [
                    [
                        'status' => 'skip',
                        'file' => basename($this->zipPath),
                        'message' => 'File ZIP tidak ditemukan di server.',
                    ],
                ],
            ], true);

            return;
        }

        $zipAbsolutePath = Storage::path($this->zipPath);
        $zip = new ZipArchive();
        $zipOpened = $zip->open($zipAbsolutePath);

        if ($zipOpened !== true) {
            Storage::delete($this->zipPath);

            $this->notifyResult($uploader, [
                'success_count' => 0,
                'skipped_count' => 1,
                'items' => [
                    [
                        'status' => 'skip',
                        'file' => basename($this->zipPath),
                        'message' => 'ZIP tidak dapat dibuka atau rusak.',
                    ],
                ],
            ], true);

            return;
        }

        $summary = [
            'success_count' => 0,
            'skipped_count' => 0,
            'items' => [],
            'processed_niks' => [],
        ];
        $tempDirectory = storage_path('app/temp/employee-media-imports/' . Str::uuid());
        $employeesQuery = Employee::query();

        if ($uploader) {
            $uploader->applyEmployeeScope($employeesQuery);
        }

        $employees = $employeesQuery->get()->keyBy('nik');
        $allowedExtensions = $mediaService->getAllowedExtensions($this->mediaType);
        $column = $mediaService->getColumnForType($this->mediaType);
        $summary = $this->loadSummary();
        $startIndex = max(0, $this->offset);
        $chunkSize = max(5, (int) env('EMPLOYEE_MEDIA_ZIP_CHUNK_SIZE', 20));
        $endIndex = min($zip->numFiles, $startIndex + $chunkSize);

        if (!File::exists($tempDirectory)) {
            File::makeDirectory($tempDirectory, 0755, true);
        }

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
                    continue;
                }

                $nik = $mediaService->resolveNikFromFilename($basename, $employees->keys()->all());

                if (!$nik) {
                    $summary['skipped_count']++;
                    $this->rememberItem($summary, 'skip', $basename, 'NIK tidak dikenali dari nama file.');
                    continue;
                }

                if (isset($summary['processed_niks'][$nik])) {
                    $summary['skipped_count']++;
                    $this->rememberItem($summary, 'skip', $basename, "Duplikat file untuk NIK {$nik} dalam ZIP yang sama.");
                    continue;
                }

                $employee = $employees->get($nik);

                if (!$employee) {
                    $summary['skipped_count']++;
                    $this->rememberItem($summary, 'skip', $basename, "Karyawan dengan NIK {$nik} tidak ditemukan atau di luar scope.");
                    continue;
                }

                $temporaryFile = $this->extractZipEntryToTemporaryFile($zip, $entryName, $tempDirectory, $extension);

                if (!$temporaryFile) {
                    $summary['skipped_count']++;
                    $this->rememberItem($summary, 'skip', $basename, 'File di dalam ZIP tidak dapat diekstrak.');
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

                    $path = $mediaService->storeUploadedFile($employee, $uploadedFile, $this->mediaType);
                    $employee->forceFill([$column => $path])->save();
                    $summary['processed_niks'][$nik] = true;
                    $summary['success_count']++;
                    $this->rememberItem($summary, 'success', $basename, "Berhasil dipasangkan ke {$employee->nama_karyawan} ({$employee->nik}).");
                } catch (Throwable $exception) {
                    $summary['skipped_count']++;
                    $this->rememberItem($summary, 'skip', $basename, 'Gagal menyimpan file ke data karyawan.');

                    Log::warning('Employee ZIP media import failed for file.', [
                        'media_type' => $this->mediaType,
                        'entry_name' => $entryName,
                        'nik' => $nik,
                        'error' => $exception->getMessage(),
                    ]);
                } finally {
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

        $this->persistSummary($summary);

        if ($endIndex < $zip->numFiles) {
            Log::info('Employee ZIP media import chunk finished.', [
                'media_type' => $this->mediaType,
                'uploader_id' => $this->uploaderId,
                'import_id' => $this->importId,
                'offset' => $this->offset,
                'chunk_size' => $chunkSize,
                'next_offset' => $endIndex,
                'success_count' => $summary['success_count'],
                'skipped_count' => $summary['skipped_count'],
            ]);

            self::dispatch($this->zipPath, $this->mediaType, $this->uploaderId, $endIndex, $this->importId)
                ->onQueue($this->queue);

            return;
        }

        Storage::delete($this->zipPath);
        $publicSummary = $this->toPublicSummary($summary);
        $this->deleteSummary();

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
        Storage::delete($this->zipPath);

        $uploader = User::find($this->uploaderId);
        $summary = $this->toPublicSummary($this->loadSummary());
        $this->deleteSummary();

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

        $this->notifyResult($uploader, $summary, true);
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

    protected function loadSummary(): array
    {
        $summaryPath = $this->summaryStoragePath();

        if (!Storage::exists($summaryPath)) {
            return [
                'success_count' => 0,
                'skipped_count' => 0,
                'items' => [],
                'processed_niks' => [],
            ];
        }

        $contents = Storage::get($summaryPath);
        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            return [
                'success_count' => 0,
                'skipped_count' => 0,
                'items' => [],
                'processed_niks' => [],
            ];
        }

        return array_merge([
            'success_count' => 0,
            'skipped_count' => 0,
            'items' => [],
            'processed_niks' => [],
        ], $decoded);
    }

    protected function persistSummary(array $summary): void
    {
        Storage::put($this->summaryStoragePath(), json_encode($summary));
    }

    protected function deleteSummary(): void
    {
        Storage::delete($this->summaryStoragePath());
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

        $uploader->notify(new StatusPengajuanNotification([
            'judul' => $title,
            'pesan' => $message,
            'url' => $isFaceReference ? route('set-kehadiran.index') : route('karyawan.index'),
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
