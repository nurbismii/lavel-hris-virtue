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
use App\Models\Perusahaan;
use App\Models\WorkPattern;
use App\Services\Recruitment\RecruitmentDocumentClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Throwable;

class KaryawanController extends Controller
{
    use ValidatesZipUploads;

    private const DOCUMENT_DOWNLOAD_TYPES = [
        'photo' => ['column' => 'photo_path', 'label' => 'FOTO'],
        'ktp' => ['column' => 'ktp_path', 'label' => 'KTP'],
        'kk' => ['column' => 'kk_path', 'label' => 'KK'],
        'sim' => ['column' => 'sim_path', 'label' => 'SIM'],
        'sio' => ['column' => 'sio_path', 'label' => 'SIO'],
        'face_reference' => ['column' => 'face_reference_path', 'label' => 'FACE REF'],
    ];

    public function index(Request $request)
    {
        $title = 'Delete Data!';
        $text = "Are you sure you want to delete?";
        confirmDelete($title, $text);

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

    public function edit($nik)
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
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->canAccessAllEmployees(), 403, 'Akses tidak diizinkan.');

        $request->validate([
            'file' => 'required|mimes:xlsx'
        ]);

        try {
            $filePath = $request->file('file')->store('imports');

            Excel::queueImport(new ImportEmployee, storage_path('app/' . $filePath))->chain([
                new DeleteImportedFile($filePath)
            ]);

            toast()->success('Success', 'Your file is being processed in the background.');
            return back();
        } catch (\Throwable $e) {

            toast()->error('Error', 'File kamu rusak, buat file baru dan import ulang.');
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

        foreach ($fileInputs as $input => $type) {
            if (!$request->hasFile($input)) {
                continue;
            }

            $column = $mediaService->getColumnForType($type);
            $replacedExisting = false;
            $path = $mediaService->storeUploadedFile($employee, $request->file($input), $type, true, $replacedExisting);

            $validatedData[$column] = $path;
            $employee->{$column} = $path;
            $replacedDocuments += $replacedExisting ? 1 : 0;
        }

        $employee->update($validatedData);

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

            $filePath = $request->file($input)->store(
                'employee-zip-imports',
                config('filesystems.employee_import_disk', config('filesystems.default'))
            );
            ProcessEmployeeMediaZipUpload::dispatch($filePath, $type, $request->user()->id);
            $queuedCount++;
        }

        $message = "{$queuedCount} file ZIP dokumen sedang diproses di background. Cek notifikasi untuk hasil akhirnya.";

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'redirect_url' => route('karyawan.index'),
            ]);
        }

        toast()->success('Success', $message);
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

    public function destroy($nik)
    {
        abort_unless(auth()->user()->canAccessAllEmployees(), 403, 'Akses tidak diizinkan.');

        $employee = auth()->user()
            ->applyEmployeeScope(Employee::query())
            ->where('nik', $nik)
            ->firstOrFail();
        $employee->delete();

        toast('Data karyawan berhasil dihapus!', 'success');
        return redirect()->route('karyawan.index');
    }

    public function departemenByArea(Request $request)
    {
        $query = Departemen::whereHas('employee', function ($q) use ($request) {
            $q->where('area_kerja', $request->area);
            $request->user()->applyEmployeeScope($q);
        });

        return $query
            ->orderBy('departemen')
            ->get(['id', 'departemen']);
    }

    /**
     * DEPARTEMEN → DIVISI
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

        abort_if(str_contains($normalizedPath, '..'), 404);

        $absolutePath = public_path($normalizedPath);

        abort_unless(File::isFile($absolutePath), 404, 'File dokumen tidak ditemukan.');

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
