<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuratPeringatan\StoreWarningLetterRequest;
use App\Http\Requests\SuratPeringatan\ReviewWarningLetterRequest;
use App\Http\Requests\SuratPeringatan\CancelWarningLetterRequest;
use App\Models\WarningLetterRequest;
use App\Services\SuratPeringatan\WarningLetterWorkflowService;
use App\Services\SuratPeringatan\WarningLetterVerificationService;
use App\Support\SafeExceptionLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class WarningLetterRequestController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', WarningLetterRequest::class);
        $filters = $request->validate(['status' => 'nullable|in:pending,approved,rejected,cancelled', 'nik' => 'nullable|string|max:32']);
        $query = WarningLetterRequest::query()->select('id', 'nik', 'status', 'level_sp', 'employee_snapshot', 'created_by_name', 'letter_number', 'created_at');
        if (!$request->user()->canAccessAllEmployees()) {
            $query->whereHas('employee', function ($employees) use ($request) { $request->user()->applyEmployeeScope($employees); });
        }
        if (!empty($filters['status'])) { $query->where('status', $filters['status']); }
        if (!empty($filters['nik'])) { $query->where('nik', $filters['nik']); }

        return view('admin.surat-peringatan.requests.index', ['letters' => $query->latest('id')->paginate(20)->withQueryString()]);
    }

    public function create(WarningLetterWorkflowService $service)
    {
        Gate::authorize('create', WarningLetterRequest::class);
        return view('admin.surat-peringatan.requests.create', ['token' => (string) Str::uuid(), 'signer' => $service->masterSigner()]);
    }

    public function employee(Request $request, WarningLetterWorkflowService $service)
    {
        Gate::authorize('create', WarningLetterRequest::class);
        $data = $request->validate(['nik' => 'required|string|regex:/^[0-9]+$/|max:32']);
        return response()->json(['success' => true, 'message' => 'Data karyawan ditemukan.',
            'data' => $service->employeeData($service->employee($request->user(), $data['nik']))]);
    }

    public function employees(Request $request, WarningLetterWorkflowService $service)
    {
        Gate::authorize('create', WarningLetterRequest::class);
        $request->merge(['q' => trim((string) $request->query('q'))]);
        $data = $request->validate([
            'q' => 'required|string|min:2|max:100',
        ], [
            'q.min' => 'Masukkan minimal 2 karakter NIK atau nama karyawan.',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pencarian karyawan selesai.',
            'data' => $service->searchEmployees($request->user(), $data['q'])->values(),
        ]);
    }

    public function store(StoreWarningLetterRequest $request, WarningLetterWorkflowService $service)
    {
        try {
            $letter = $service->submit($request->validated(), $request->user());
        } catch (\Illuminate\Auth\Access\AuthorizationException | \Symfony\Component\HttpKernel\Exception\HttpException | \Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $reference = app(SafeExceptionLogger::class)->warning('warning_letters.submit', $exception);
            return back()->withInput()->withErrors(['submission' => 'Pengajuan gagal disimpan. Silakan coba lagi. Kode bantuan: ' . $reference]);
        }
        toast()->success('Pengajuan tersimpan', 'Pelanggaran menunggu approval HR/Super Admin. Surat belum diterbitkan.');
        return redirect()->route('warning-letter-requests.show', $letter);
    }

    public function show(
        WarningLetterRequest $warningLetter,
        WarningLetterWorkflowService $service,
        WarningLetterVerificationService $verificationService
    )
    {
        Gate::authorize('view', $warningLetter);
        if ($warningLetter->status === WarningLetterRequest::APPROVED) {
            $warningLetter = $verificationService->ensureCredentials($warningLetter, request()->user());
        }

        return view('admin.surat-peringatan.requests.show', [
            'letter' => $warningLetter,
            'signer' => $service->masterSigner(),
            'verificationUrl' => $warningLetter->status === WarningLetterRequest::APPROVED
                ? $verificationService->url($warningLetter) : null,
            'verificationAccessCount' => $warningLetter->status === WarningLetterRequest::APPROVED
                ? $warningLetter->verificationLogs()->count() : 0,
        ]);
    }

    public function review(ReviewWarningLetterRequest $request, WarningLetterRequest $warningLetter, WarningLetterWorkflowService $service)
    {
        try {
            $letter = $service->review($warningLetter, $request->validated(), $request->user());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $reference = app(SafeExceptionLogger::class)->warning('warning_letters.review', $exception);
            return back()->withErrors(['approval' => 'Approval gagal diproses. Status belum berubah. Silakan coba kembali. Kode bantuan: ' . $reference]);
        }
        toast()->success('Berhasil', $letter->status === WarningLetterRequest::APPROVED
            ? 'SP telah diterbitkan. Surat PDF tersedia untuk diunduh.' : 'Pengajuan ditolak dengan alasan yang tercatat.');
        return redirect()->route('warning-letter-requests.show', $letter);
    }

    public function download(Request $request, WarningLetterRequest $warningLetter, WarningLetterWorkflowService $service)
    {
        Gate::authorize('view', $warningLetter);
        try {
            return $service->pdf($warningLetter, $request->user())
                ->download('surat-peringatan-' . $warningLetter->number_sequence . '.pdf')
                ->header('Cache-Control', 'private, no-store')
                ->header('X-Warning-Letter-Verification', 'qr');
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() < 500) {
                throw $exception;
            }

            return $this->downloadFailureResponse($request, $exception);
        } catch (Throwable $exception) {
            return $this->downloadFailureResponse($request, $exception);
        }
    }

    public function verification(
        Request $request,
        WarningLetterRequest $warningLetter,
        WarningLetterVerificationService $service
    ) {
        Gate::authorize('manageVerification', $warningLetter);
        $data = $request->validate(['action' => 'required|in:revoke,activate']);
        $revoked = $data['action'] === 'revoke';
        $service->setRevoked($warningLetter, $revoked, $request->user());
        toast()->success('Berhasil', $revoked
            ? 'Verifikasi publik surat telah dicabut.'
            : 'Verifikasi publik surat telah diaktifkan kembali.');

        return redirect()->route('warning-letter-requests.show', $warningLetter);
    }

    public function destroy(
        CancelWarningLetterRequest $request,
        WarningLetterRequest $warningLetter,
        WarningLetterWorkflowService $service
    ) {
        try {
            $letter = $service->cancel($warningLetter, $request->validated('reason'), $request->user());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $reference = app(SafeExceptionLogger::class)->warning('warning_letters.cancel', $exception);
            return back()->withErrors([
                'cancellation' => 'SP gagal dihapus. Status belum berubah. Kode bantuan: ' . $reference,
            ]);
        }

        toast()->success('SP dihapus', 'Surat dibatalkan, QR dinonaktifkan, dan riwayat audit tetap disimpan.');

        return redirect()->route('warning-letter-requests.show', $letter);
    }

    private function downloadFailureResponse(Request $request, Throwable $exception)
    {
        [$failureCode, $instruction] = $this->classifyDownloadFailure($exception);
        $reference = app(SafeExceptionLogger::class)->warning(
            'warning_letters.download.' . strtolower($failureCode),
            $exception
        );
        $message = $instruction . ' Kode bantuan: ' . $failureCode . '-' . $reference;

        if ($request->expectsJson() || str_contains((string) $request->header('Accept'), 'application/json')) {
            return response()->json(['success' => false, 'message' => $message], 500);
        }

        return back()->withErrors(['download' => $message]);
    }

    private function classifyDownloadFailure(Throwable $exception): array
    {
        $messages = [];
        $current = $exception;
        do {
            $messages[] = strtolower($current->getMessage());
            $current = $current->getPrevious();
        } while ($current);
        $message = implode(' | ', $messages);

        if (str_contains($message, 'qr_code_package_not_installed')
            || (str_contains($message, 'class') && str_contains($message, 'endroid'))) {
            return ['QR01', 'Komponen QR belum terpasang di server. Jalankan composer install dari composer.lock.'];
        }
        if (str_contains($message, 'unknown column') || str_contains($message, 'base table or view not found')) {
            return ['DB01', 'Struktur database hosting belum diperbarui. Jalankan migration aplikasi.'];
        }
        if (str_contains($message, 'logo surat peringatan') || str_contains($message, 'watermark surat peringatan')) {
            return ['AST01', 'Aset logo atau watermark surat belum tersedia di hosting. Unggah ulang folder public/assets/img.'];
        }
        if (str_contains($message, 'permission denied') || str_contains($message, 'not writable')
            || str_contains($message, 'failed to open stream') || str_contains($message, 'unable to create')) {
            return ['FS01', 'Folder storage dan bootstrap/cache harus dapat ditulis oleh PHP hosting.'];
        }
        if (str_contains($message, 'mac is invalid') || str_contains($message, 'could not decrypt')) {
            return ['KEY01', 'Token surat tidak dapat dibaca. Pastikan APP_KEY hosting tidak berubah.'];
        }
        if (str_contains($message, 'allowed memory size') || str_contains($message, 'maximum execution time')) {
            return ['RES01', 'Batas memori atau waktu proses PHP hosting tidak cukup untuk membuat PDF.'];
        }

        return ['PDF01', 'Server gagal membuat PDF surat. Hubungi administrator untuk pemeriksaan log.'];
    }
}
