<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ValidatesZipUploads;
use App\Http\Controllers\Controller;
use App\Http\Requests\KaryawanRequest\UpdateKaryawanRequest;
use App\Imports\ImportEmployee;
use App\Jobs\ProcessEmployeeMediaZipUpload;
use App\Jobs\DeleteImportedFile;
use App\Models\Departemen;
use App\Models\Divisi;
use App\Models\Employee;
use App\Models\ImportHistory;
use App\Models\Perusahaan;
use App\Models\WorkPattern;
use App\Services\ContractRenewals\ContractRenewalService;
use App\Services\ImportHistory\ImportHistoryService;
use App\Services\Recruitment\RecruitmentDocumentClient;
use App\Services\Storage\SensitiveFileStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Throwable;

class KaryawanController extends Controller
{
    use ValidatesZipUploads;

    private const DOCUMENT_DOWNLOAD_TYPES = [
        'photo' => ['column' => 'photo_path', 'label' => 'FOTO', 'directory' => 'employee-documents/%s/photo/'],
        'ktp' => ['column' => 'ktp_path', 'label' => 'KTP', 'directory' => 'employee-documents/%s/ktp/'],
        'kk' => ['column' => 'kk_path', 'label' => 'KK', 'directory' => 'employee-documents/%s/kk/'],
        'sim' => ['column' => 'sim_path', 'label' => 'SIM', 'directory' => 'employee-documents/%s/sim/'],
        'sio' => ['column' => 'sio_path', 'label' => 'SIO', 'directory' => 'employee-documents/%s/sio/'],
        'face_reference' => ['column' => 'face_reference_path', 'label' => 'FACE REF', 'directory' => 'face-reference/%s/'],
    ];

    public function index(Request $request)
    {
        if ($request->ajax()) {

            $karyawanService = app()->make(\App\Services\Karyawan\KaryawanService::class);

            return $karyawanService->getDataKaryawan($request, $request->user());
        }

        $scopeQuery = $request->user()->applyEmployeeScope(Employee::query());
        $departemenIds = (clone $scopeQuery)->select('departemen_id')->distinct()->pluck('departemen_id')->filter();
        $divisiIds = (clone $scopeQuery)->select('divisi_id')->distinct()->pluck('divisi_id')->filter();
        $areaCodes = (clone $scopeQuery)->select('area_kerja')->distinct()->pluck('area_kerja')->filter();

        return view('admin.karyawan.index', [
            'departemens' => Departemen::with('perusahaan')->whereIn('id', $departemenIds)->orderBy('departemen')->get(),
            'divisis' => Divisi::whereIn('id', $divisiIds)->orderBy('nama_divisi')->get(),
            'areas' => Perusahaan::whereIn('kode_perusahaan', $areaCodes)->get(),
            'canManageMasterData' => $request->user()->canAccessAllEmployees(),
        ]);
    }

    public function edit($nik, ContractRenewalService $contractRenewalService)
    {
        $employee = auth()->user()
            ->applyEmployeeScope(Employee::query())
            ->where('nik', $nik)
            ->with(['departemen', 'divisi'])
            ->firstOrFail();

        $scopeQuery = auth()->user()->applyEmployeeScope(Employee::query());
        $departemenIds = (clone $scopeQuery)->select('departemen_id')->distinct()->pluck('departemen_id')->filter();
        $divisiIds = (clone $scopeQuery)->select('divisi_id')->distinct()->pluck('divisi_id')->filter();
        $areaCodes = (clone $scopeQuery)->select('area_kerja')->distinct()->pluck('area_kerja')->filter();
        $contractTimeline = $contractRenewalService
            ->contractHistoriesForNiks([$employee->nik])
            ->get($employee->nik, collect());

        return view('admin.karyawan.edit', [
            'employee' => $employee,
            'departemens' => Departemen::with('perusahaan')->whereIn('id', $departemenIds)->orderBy('departemen')->get(),
            'divisis' => Divisi::whereIn('id', $divisiIds)->orderBy('nama_divisi')->get(),
            'areas' => Perusahaan::whereIn('kode_perusahaan', $areaCodes)->get(),
            'workPatterns' => WorkPattern::query()
                ->where(function ($query) use ($employee) {
                    $query->where('is_active', true);

                    if ($employee->work_pattern_id) {
                        $query->orWhere('id', $employee->work_pattern_id);
                    }
                })
                ->orderBy('name')
                ->get(),
            'contractTimeline' => $contractTimeline,
            'canManageSensitiveEmployeeFields' => auth()->user()->canAccessAllEmployees(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->canAccessAllEmployees(), 403, 'Akses tidak diizinkan.');

        $request->validate([
            'file' => 'required|file|mimes:xlsx|max:51200',
        ], [
            'file.required' => 'File Excel karyawan wajib dipilih.',
            'file.file' => 'Upload harus berupa file Excel yang valid.',
            'file.mimes' => 'Format file harus .xlsx.',
            'file.max' => 'Ukuran file Excel maksimal 50MB.',
        ]);

        $uploadedFile = $request->file('file');
        $history = null;

        try {
            $filePath = $uploadedFile->store('imports');
            $history = app(ImportHistoryService::class)->createQueued([
                'import_type' => ImportHistory::TYPE_EMPLOYEE,
                'module' => 'employee',
                'source' => ImportHistory::SOURCE_EXCEL,
                'file_name' => $uploadedFile->getClientOriginalName(),
                'file_path' => $filePath,
                'disk' => config('filesystems.default'),
                'mime_type' => $uploadedFile->getClientMimeType(),
                'file_size' => $uploadedFile->getSize(),
                'created_by' => (string) $request->user()->id,
            ]);

            Excel::queueImport(new ImportEmployee(optional($history)->id), storage_path('app/' . $filePath))->chain([
                new DeleteImportedFile($filePath)
            ]);

            toast()->success('Berhasil', 'File import karyawan berhasil diterima dan sedang diproses di background. Cek History Import untuk melihat hasilnya.');
            return back();
        } catch (Throwable $e) {
            app(ImportHistoryService::class)->markFailed(optional($history)->id, $e);
            report($e);

            toast()->error('Gagal', 'File import karyawan gagal diterima. Pastikan format file .xlsx sesuai template lalu coba lagi.');
            return back();
        }
    }

    public function update(UpdateKaryawanRequest $request, $nik)
    {
        $employee = $request->user()
            ->applyEmployeeScope(Employee::query())
            ->where('nik', $nik)
            ->firstOrFail();

        $validatedData = $request->safe()->except([
            'photo_file',
            'ktp_file',
            'kk_file',
            'sim_file',
            'sio_file',
            'face_reference',
        ]);

        if (($validatedData['status_resign'] ?? null) === 'AKTIF') {
            $validatedData['tgl_resign'] = null;
            $validatedData['kategori_keluar'] = '-';
        }

        if (strtolower(trim((string) ($validatedData['status_perkawinan'] ?? ''))) === 'belum kawin') {
            $validatedData['tanggal_menikah'] = null;
        }

        $mediaService = app()->make(\App\Services\Karyawan\EmployeeMediaService::class);
        $fileInputs = [
            'photo_file' => 'photo',
            'ktp_file' => 'ktp',
            'kk_file' => 'kk',
            'sim_file' => 'sim',
            'sio_file' => 'sio',
            'face_reference' => 'face_reference',
        ];
        $replacedDocuments = 0;
        $storedNewPaths = [];
        $replacedPaths = [];

        try {
            foreach ($fileInputs as $input => $type) {
                if (!$request->hasFile($input)) {
                    continue;
                }

                $column = $mediaService->getColumnForType($type);
                $replacedExisting = false;
                $replacedPath = null;
                $path = $mediaService->storeUploadedFile(
                    $employee,
                    $request->file($input),
                    $type,
                    true,
                    $replacedExisting,
                    false,
                    $replacedPath
                );

                $storedNewPaths[] = $path;
                $validatedData[$column] = $path;
                $employee->{$column} = $path;
                $replacedDocuments += $replacedExisting ? 1 : 0;

                if ($replacedPath) {
                    $replacedPaths[] = $replacedPath;
                }
            }

            DB::transaction(function () use ($employee, $validatedData) {
                $employee->update($validatedData);
            });
        } catch (Throwable $exception) {
            foreach ($storedNewPaths as $storedPath) {
                $mediaService->deleteRelativePath($storedPath);
            }

            report($exception);
            toast('Data karyawan gagal diperbarui. Periksa kembali data dan file dokumen, lalu coba lagi.', 'error');

            return back()->withInput();
        }

        foreach (array_unique($replacedPaths) as $replacedPath) {
            try {
                $mediaService->deleteRelativePath($replacedPath);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $message = $replacedDocuments > 0
            ? "Data karyawan berhasil diperbarui. {$replacedDocuments} file lama ditimpa dengan file baru."
            : 'Data karyawan berhasil diperbarui!';

        toast($message, 'success');
        return redirect()->route('karyawan.index');
    }

    public function bulkUploadDocuments(Request $request)
    {
        abort_unless($request->user()->canAccessAllEmployees(), 403, 'Akses tidak diizinkan.');

        $this->validateZipUploads($request, [
            'bulk_photo_zip' => ['label' => 'ZIP foto karyawan'],
            'bulk_ktp_zip' => ['label' => 'ZIP KTP'],
            'bulk_kk_zip' => ['label' => 'ZIP KK'],
            'bulk_sim_zip' => ['label' => 'ZIP SIM'],
            'bulk_sio_zip' => ['label' => 'ZIP SIO'],
        ]);

        $inputMap = [
            'bulk_photo_zip' => 'photo',
            'bulk_ktp_zip' => 'ktp',
            'bulk_kk_zip' => 'kk',
            'bulk_sim_zip' => 'sim',
            'bulk_sio_zip' => 'sio',
        ];
        $hasAnyUpload = collect(array_keys($inputMap))
            ->contains(fn($input) => $request->hasFile($input));

        if (!$hasAnyUpload) {
            $message = 'Pilih minimal satu file ZIP dokumen untuk diproses.';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'errors' => [
                        'bulk_documents_zip' => [$message],
                    ],
                ], 422);
            }

            return back()->withErrors([
                'bulk_documents_zip' => $message,
            ]);
        }
        $queuedCount = 0;

        foreach ($inputMap as $input => $type) {
            if (!$request->hasFile($input)) {
                continue;
            }

            $uploadedFile = $request->file($input);
            $disk = config('filesystems.employee_import_disk', config('filesystems.default'));
            $history = null;

            $filePath = $uploadedFile->store(
                'employee-zip-imports',
                $disk
            );
            $history = app(ImportHistoryService::class)->createQueued([
                'import_type' => ImportHistory::typeForMedia($type),
                'module' => 'employee_media',
                'source' => ImportHistory::SOURCE_ZIP,
                'file_name' => $uploadedFile->getClientOriginalName(),
                'file_path' => $filePath,
                'disk' => $disk,
                'mime_type' => $uploadedFile->getClientMimeType(),
                'file_size' => $uploadedFile->getSize(),
                'created_by' => (string) $request->user()->id,
                'summary' => [
                    'media_type' => $type,
                ],
            ]);

            ProcessEmployeeMediaZipUpload::dispatch(
                $filePath,
                $type,
                $request->user()->id,
                0,
                optional($history)->import_id,
                optional($history)->id
            );
            $queuedCount++;
        }

        $message = "{$queuedCount} file ZIP dokumen sedang diproses di background. Cek notifikasi untuk hasil akhirnya.";

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'redirect_url' => route('karyawan.index'),
            ]);
        }

        toast()->success('Berhasil', $message);
        return redirect()->route('karyawan.index');
    }

    public function downloadDocument(Request $request, string $nik, string $type)
    {
        $document = $this->resolveEmployeeDocument($request, $nik, $type);

        return response()->download($document['absolute_path'], $document['download_name']);
    }

    public function previewDocument(Request $request, string $nik, string $type)
    {
        $document = $this->resolveEmployeeDocument($request, $nik, $type);

        return response()->file($document['absolute_path'], [
            'Content-Type' => $document['mime_type'],
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $document['download_name'],
                $document['download_name_fallback']
            ),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function photo(Request $request, string $nik)
    {
        return $this->previewDocument($request, $nik, 'photo');
    }

    public function recruitmentDocuments(Request $request, string $nik, RecruitmentDocumentClient $client)
    {
        $employee = $request->user()
            ->applyEmployeeScope(Employee::query())
            ->select('nik', 'nama_karyawan', 'no_ktp')
            ->where('nik', $nik)
            ->firstOrFail();

        if (blank($employee->no_ktp)) {
            return response()->json([
                'found' => false,
                'employee' => [
                    'nik' => $employee->nik,
                    'name' => $employee->nama_karyawan,
                ],
                'message' => 'Nomor KTP karyawan belum tersedia.',
                'documents' => [],
            ]);
        }

        try {
            $payload = $client->lookupByNoKtp($employee->no_ktp);
        } catch (Throwable $exception) {
            report($exception);

            $message = 'Dokumen recruitment belum bisa diambil. Periksa konfigurasi atau koneksi API recruitment.';

            if ((bool) config('app.debug')) {
                $message .= ' Detail: ' . $exception->getMessage();
            }

            return response()->json([
                'found' => false,
                'employee' => [
                    'nik' => $employee->nik,
                    'name' => $employee->nama_karyawan,
                ],
                'message' => $message,
                'documents' => [],
            ], 502);
        }

        return response()->json([
            'found' => (bool) ($payload['found'] ?? false),
            'employee' => [
                'nik' => $employee->nik,
                'name' => $employee->nama_karyawan,
                'no_ktp' => $employee->no_ktp,
            ],
            'candidate' => $payload['candidate'] ?? null,
            'documents' => $payload['documents'] ?? [],
        ]);
    }

    public function destroy(Request $request, $nik)
    {
        abort_unless($request->user()->canAccessAllEmployees(), 403, 'Akses tidak diizinkan.');

        $employee = $request->user()
            ->applyEmployeeScope(Employee::query())
            ->where('nik', $nik)
            ->firstOrFail();

        try {
            $employee->delete();
        } catch (Throwable $exception) {
            report($exception);

            $message = 'Data karyawan gagal dihapus. Pastikan data ini tidak masih dipakai oleh presensi, kontrak, approval, atau data terkait lain.';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 500);
            }

            toast($message, 'error');
            return redirect()->route('karyawan.index');
        }

        $message = 'Data karyawan berhasil dihapus!';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        toast($message, 'success');
        return redirect()->route('karyawan.index');
    }

    public function departemenByArea(Request $request)
    {
        $areaCodes = collect((array) $request->input('area'))
            ->filter(fn($value) => filled($value))
            ->map(fn($value) => trim((string) $value))
            ->unique()
            ->values()
            ->all();

        $query = Departemen::whereHas('employee', function ($q) use ($request, $areaCodes) {
            if ($areaCodes) {
                $q->whereIn('area_kerja', $areaCodes);
            }

            $request->user()->applyEmployeeScope($q);
        });

        return $query
            ->orderBy('departemen')
            ->get(['id', 'departemen']);
    }

    /**
     * DEPARTEMEN -> DIVISI
     */
    public function divisiByDepartemen(Request $request)
    {
        $query = Divisi::where('departemen_id', $request->departemen);

        if ($request->user()->isDivisionScopedRole()) {
            $query->whereIn('id', $request->user()->scopedDivisionIds());
        }

        return $query
            ->orderBy('nama_divisi')
            ->get(['id', 'nama_divisi']);
    }

    private function buildDocumentDownloadName(Employee $employee, string $documentLabel, ?string $extension): string
    {
        $nik = $this->normalizeDownloadNamePart($employee->nik);
        $name = $this->normalizeDownloadNamePart($employee->nama_karyawan ?: 'KARYAWAN');
        $filename = trim("{$nik} {$name} - {$documentLabel}");

        return $extension ? "{$filename}.{$extension}" : $filename;
    }

    private function resolveEmployeeDocument(Request $request, string $nik, string $type): array
    {
        abort_unless(isset(self::DOCUMENT_DOWNLOAD_TYPES[$type]), 404);

        $documentConfig = self::DOCUMENT_DOWNLOAD_TYPES[$type];
        $column = $documentConfig['column'];
        $employee = $request->user()
            ->applyEmployeeScope(Employee::query())
            ->select('nik', 'nama_karyawan', $column)
            ->where('nik', $nik)
            ->firstOrFail();
        $relativePath = $employee->{$column};

        abort_if(blank($relativePath), 404, 'Dokumen belum tersedia.');

        $normalizedPath = str_replace('\\', '/', ltrim($relativePath, '/'));
        $expectedDirectory = sprintf($documentConfig['directory'], $employee->nik);

        abort_if(
            str_contains($normalizedPath, '..') || !Str::startsWith($normalizedPath, $expectedDirectory),
            404
        );

        $absolutePath = app(SensitiveFileStorageService::class)->resolvePath($normalizedPath, [$expectedDirectory]);

        abort_unless($absolutePath && File::isFile($absolutePath), 404, 'File dokumen tidak ditemukan.');

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $downloadName = $this->buildDocumentDownloadName($employee, $documentConfig['label'], $extension);

        return [
            'absolute_path' => $absolutePath,
            'download_name' => $downloadName,
            'download_name_fallback' => $this->buildAsciiFilenameFallback($downloadName),
            'mime_type' => File::mimeType($absolutePath) ?: 'application/octet-stream',
        ];
    }

    private function buildAsciiFilenameFallback(string $filename): string
    {
        $fallback = preg_replace('/[^A-Za-z0-9 ._\-()]/', '', $filename) ?: '';
        $fallback = trim($fallback);

        return $fallback !== '' ? $fallback : 'document';
    }

    private function normalizeDownloadNamePart($value): string
    {
        $value = strtoupper((string) $value);
        $value = preg_replace('/[\\\\\/:*?"<>|]+/', ' ', $value) ?: '';
        $value = preg_replace('/\s+/', ' ', $value) ?: '';

        return trim($value);
    }
}
