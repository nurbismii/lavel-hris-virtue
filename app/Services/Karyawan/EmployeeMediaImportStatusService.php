<?php

namespace App\Services\Karyawan;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class EmployeeMediaImportStatusService
{
    private const STATUS_DIRECTORY = 'employee-zip-imports/status';
    private const RETENTION_DAYS = 3;

    public function listForUser(User $user, array $mediaTypes = [], int $limit = 8): Collection
    {
        $storage = $this->storage();

        if (!$storage->exists(self::STATUS_DIRECTORY)) {
            return collect();
        }

        return collect($storage->files(self::STATUS_DIRECTORY))
            ->map(function (string $path) use ($storage) {
                $payload = json_decode((string) $storage->get($path), true);

                if (!is_array($payload)) {
                    return null;
                }

                $payload['_path'] = $path;

                return $payload;
            })
            ->filter()
            ->reject(function (array $item) {
                $updatedAt = isset($item['updated_at']) ? Carbon::parse($item['updated_at']) : null;

                if (!$updatedAt || $updatedAt->lt(now()->subDays(self::RETENTION_DAYS))) {
                    if (!empty($item['_path'])) {
                        $this->storage()->delete($item['_path']);
                    }

                    return true;
                }

                return false;
            })
            ->filter(fn(array $item) => (string) ($item['uploader_id'] ?? '') === (string) $user->id)
            ->filter(function (array $item) use ($mediaTypes) {
                if (empty($mediaTypes)) {
                    return true;
                }

                return in_array((string) ($item['media_type'] ?? ''), $mediaTypes, true);
            })
            ->map(function (array $item) {
                $totalEntries = max(0, (int) ($item['total_entries'] ?? 0));
                $processedEntries = max(0, (int) (($item['success_count'] ?? 0) + ($item['skipped_count'] ?? 0)));
                $progressPercentage = $totalEntries > 0
                    ? min(100, (int) round(($processedEntries / $totalEntries) * 100))
                    : 0;
                $status = (string) ($item['status'] ?? 'queued');
                $updatedAt = isset($item['updated_at']) ? Carbon::parse($item['updated_at']) : null;

                return [
                    'import_id' => $item['import_id'] ?? null,
                    'media_type' => $item['media_type'] ?? null,
                    'label' => $this->labelForType((string) ($item['media_type'] ?? '')),
                    'status' => $status,
                    'status_label' => $this->statusLabel($status),
                    'status_class' => $this->statusClass($status),
                    'total_entries' => $totalEntries,
                    'processed_entries' => $processedEntries,
                    'success_count' => (int) ($item['success_count'] ?? 0),
                    'skipped_count' => (int) ($item['skipped_count'] ?? 0),
                    'progress_percentage' => $progressPercentage,
                    'updated_at' => $updatedAt ? $updatedAt->toIso8601String() : null,
                    'updated_at_human' => $updatedAt ? $updatedAt->diffForHumans() : '-',
                ];
            })
            ->sortByDesc('updated_at')
            ->take($limit)
            ->values();
    }

    public function deleteForUser(User $user, string $importId): bool
    {
        $path = $this->summaryPath($importId);
        $storage = $this->storage();

        if (!$storage->exists($path)) {
            return false;
        }

        $payload = json_decode((string) $storage->get($path), true);

        if (!is_array($payload) || (string) ($payload['uploader_id'] ?? '') !== (string) $user->id) {
            return false;
        }

        return $storage->delete($path);
    }

    private function storage()
    {
        return Storage::disk(config('filesystems.employee_import_disk', config('filesystems.default')));
    }

    private function summaryPath(string $importId): string
    {
        return self::STATUS_DIRECTORY . '/' . $importId . '.json';
    }

    private function labelForType(string $type): string
    {
        switch ($type) {
            case 'photo':
                return 'Foto Karyawan';
            case 'ktp':
                return 'KTP';
            case 'kk':
                return 'KK';
            case 'sim':
                return 'SIM';
            case 'sio':
                return 'SIO';
            case 'face_reference':
                return 'Foto Referensi Presensi';
            default:
                return strtoupper($type);
        }
    }

    private function statusLabel(string $status): string
    {
        switch ($status) {
            case 'processing':
                return 'Sedang diproses';
            case 'completed':
                return 'Selesai';
            case 'failed':
                return 'Gagal';
            default:
                return 'Menunggu queue';
        }
    }

    private function statusClass(string $status): string
    {
        switch ($status) {
            case 'processing':
                return 'info';
            case 'completed':
                return 'success';
            case 'failed':
                return 'danger';
            default:
                return 'secondary';
        }
    }
}
