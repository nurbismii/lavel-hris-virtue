<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Roster\UploadRosterScheduleImportRequest;
use App\Models\ImportHistory;
use App\Services\Audit\AuditTrailService;
use App\Services\Roster\RosterScheduleImportPreviewService;
use App\Services\Roster\RosterScheduleImportValidationService;
use App\Services\Roster\RosterScheduleWorkbookReader;
use App\Services\Storage\SensitiveFileStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class RosterScheduleImportController extends Controller
{
    public function create(Request $request)
    {
        $this->authorizeActor($request);

        return view('admin.roster-schedules.import');
    }

    public function store(
        UploadRosterScheduleImportRequest $request,
        SensitiveFileStorageService $storage,
        RosterScheduleImportPreviewService $previewService,
        AuditTrailService $audit
    ) {
        $file = $request->file('file');
        $importId = (string) Str::uuid();
        $relativePath = null;
        $history = null;

        try {
            $relativePath = $storage->storeUploadedFileAs($file, 'roster-imports/' . $importId, 'source.xlsx');
            $absolutePath = $this->resolvePrivatePath($storage, $relativePath);
            if ($absolutePath === null) {
                throw new RuntimeException('File upload tidak dapat diproses.');
            }

            $history = ImportHistory::create([
                'import_id' => $importId,
                'import_type' => ImportHistory::TYPE_ROSTER_SCHEDULE,
                'status' => ImportHistory::STATUS_QUEUED,
                'created_by' => (string) $request->user()->id,
                'file_path' => $relativePath,
                'file_checksum' => hash_file('sha256', $absolutePath),
                'expires_at' => now()->addHours((int) config('roster.import.retention_hours', 12)),
            ]);
            $this->audit($audit, 'roster_schedule_import.uploaded', $history, $request->user());
            $preview = $previewService->preview($history, $request->user());
            $history = $history->fresh();
            $this->audit($audit, 'roster_schedule_import.previewed', $history, $request->user());

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'File diterima dan hasil validasi siap ditinjau.',
                    'data' => ['redirect_url' => route('roster-schedules.import.show', $history)],
                ]);
            }

            return redirect()->route('roster-schedules.import.show', $history)
                ->with('success', 'File diterima dan hasil validasi siap ditinjau.');
        } catch (Throwable $exception) {
            if ($relativePath !== null) {
                $this->deletePrivateFile($storage, $relativePath);
            }
            $this->deletePrivateFile($storage, 'roster-imports/' . $importId . '/failures.xlsx');
            if ($history instanceof ImportHistory) {
                $history->delete();
            }
            Log::warning('Roster import preview failed.', [
                'code' => 'roster_import_preview_failed',
                'import_id' => $importId,
                'exception_class' => get_class($exception),
            ]);

            $message = 'File gagal diproses. Silakan periksa format workbook dan coba lagi.';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 500);
            }

            return back()->withInput()->withErrors(['file' => $message]);
        }
    }

    public function show(
        Request $request,
        ImportHistory $history,
        SensitiveFileStorageService $storage,
        RosterScheduleWorkbookReader $reader,
        RosterScheduleImportValidationService $validator
    )
    {
        $history = $this->ownedImport($request, $history);
        abort_if($history->status === ImportHistory::STATUS_EXPIRED || !$history->expires_at?->isFuture(), 410);
        $path = $this->resolvePrivatePath($storage, (string) $history->file_path);
        abort_unless($path, 404);
        $rows = $validator->validate($reader->read($path))['rows'];

        return view('admin.roster-schedules.import', compact('history', 'rows'));
    }

    public function status(Request $request, ImportHistory $history)
    {
        $history = $this->ownedImport($request, $history);

        return response()->json([
            'success' => true,
            'message' => $history->status_label,
            'data' => [
                'status' => $history->status,
                'summary' => $this->safeSummary($history->summary ?: []),
                'terminal' => in_array($history->status, [
                    ImportHistory::STATUS_COMPLETED,
                    ImportHistory::STATUS_FAILED,
                    ImportHistory::STATUS_VALIDATION_FAILED,
                    ImportHistory::STATUS_EXPIRED,
                ], true),
            ],
        ]);
    }

    public function failure(Request $request, ImportHistory $history, SensitiveFileStorageService $storage, AuditTrailService $audit)
    {
        $history = $this->ownedImport($request, $history);
        abort_if($history->status === ImportHistory::STATUS_EXPIRED, 410);
        abort_unless($history->status === ImportHistory::STATUS_VALIDATION_FAILED && $history->expires_at?->isFuture(), 404);

        $path = $this->resolvePrivatePath($storage, (string) $history->failure_file_path);
        abort_unless($path, 404);
        $this->audit($audit, 'roster_schedule_import.failure_downloaded', $history, $request->user());

        $response = response()->download($path, 'roster-import-failures.xlsx', [
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->headers->set('Cache-Control', 'private, no-store', true);

        return $response;
    }

    private function ownedImport(Request $request, ImportHistory $history): ImportHistory
    {
        abort_unless($history->import_type === ImportHistory::TYPE_ROSTER_SCHEDULE, 404);
        $this->authorizeActor($request);
        abort_unless((string) $history->created_by === (string) $request->user()->id || $request->user()->canAccessAllEmployees(), 403);

        return $history;
    }

    private function authorizeActor(Request $request): void
    {
        abort_unless(
            $request->user()
                && $request->user()->hasRole(['Super Admin', 'HR'])
                && $request->user()->hasMenuAccess('roster_schedule'),
            403
        );
    }

    private function audit(AuditTrailService $audit, string $event, ImportHistory $history, $actor): void
    {
        $audit->record([
            'event' => $event,
            'module' => 'roster_schedule_import',
            'reference_table' => 'import_histories',
            'reference_id' => (string) $history->id,
            'actor' => $actor,
            'metadata' => [
                'import_id' => $history->import_id,
                'safe_filename' => 'source.xlsx',
                'checksum' => $history->file_checksum,
                'status' => $history->status,
                'summary' => $this->safeSummary($history->summary ?: []),
            ],
        ]);
    }

    private function deletePrivateFile(SensitiveFileStorageService $storage, string $relativePath): void
    {
        if (!str_starts_with($relativePath, 'roster-imports/') || str_contains($relativePath, '..')) {
            return;
        }

        $storage->delete($relativePath, ['roster-imports/']);
        Storage::disk('local')->delete('private/' . $relativePath);
    }

    private function safeSummary(array $summary): array
    {
        $sanitize = function ($value, ?string $key = null) use (&$sanitize) {
            $normalizedKey = strtolower((string) $key);
            if (
                $normalizedKey !== ''
                && (
                    preg_match('/ktp|path|checksum|filename/', $normalizedKey) === 1
                    || in_array($normalizedKey, ['row', 'rows', 'raw_rows'], true)
                )
            ) {
                return null;
            }

            if (is_array($value)) {
                $result = [];
                foreach ($value as $nestedKey => $nestedValue) {
                    $sanitized = $sanitize($nestedValue, (string) $nestedKey);
                    if ($sanitized !== null) {
                        $result[$nestedKey] = $sanitized;
                    }
                }

                return $result;
            }

            if (is_string($value) && preg_match('/\\b\\d{16}\\b/', $value) === 1) {
                return '[redacted]';
            }

            return $value;
        };

        return $sanitize($summary);
    }

    private function resolvePrivatePath(SensitiveFileStorageService $storage, string $relativePath): ?string
    {
        $resolved = $storage->resolvePath($relativePath, ['roster-imports/']);
        if ($resolved !== null) {
            return $resolved;
        }

        if (!str_starts_with($relativePath, 'roster-imports/') || str_contains($relativePath, '..')) {
            return null;
        }

        $diskPath = 'private/' . $relativePath;

        return Storage::disk('local')->exists($diskPath)
            ? Storage::disk('local')->path($diskPath)
            : null;
    }
}
