<?php

namespace App\Http\Controllers\Approval;

use App\Http\Controllers\Controller;
use App\Http\Requests\Approval\ProcessApprovalRequest;
use App\Models\ApprovalDelegation;
use App\Models\AttendanceCorrection;
use App\Models\Cuti;
use App\Models\Roster;
use App\Models\RosterOffRequest;
use App\Notifications\StatusPengajuanNotification;
use App\Services\Approvals\ApprovalDelegationService;
use App\Services\Notifications\ApprovalNotificationService;
use App\Services\Storage\SensitiveFileStorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DelegatedApprovalController extends Controller
{
    public function index(Request $request, ?string $module, ApprovalDelegationService $service)
    {
        $module = $service->normalizeModule($module ?: ApprovalDelegation::MODULE_CUTI);

        if (!$module || $module === ApprovalDelegation::MODULE_ALL) {
            abort(404);
        }

        $counts = $service->countsForDelegate($request->user());

        abort_unless(
            $service->hasDelegateAccess($request->user()) || $counts['total'] > 0,
            403,
            'Anda belum ditunjuk sebagai delegasi approval aktif.'
        );

        $target = $service->targetForModule($module);
        $items = $service->restrictPendingForDelegate(
            $service->queryForModule($module),
            $request->user(),
            $module,
            $target['table'],
            $target['model']
        )
            ->latest($target['table'] . '.created_at')
            ->paginate(50)
            ->withQueryString();

        return view('approval.delegate.index', [
            'counts' => $counts,
            'items' => $items,
            'module' => $module,
            'modules' => $service->approvableModules(),
            'moduleLabel' => $service->moduleLabel($module),
        ]);
    }

    public function process(ProcessApprovalRequest $request, string $module, $id, ApprovalDelegationService $service)
    {
        $module = $service->normalizeModule($module);

        if (!$module || $module === ApprovalDelegation::MODULE_ALL) {
            abort(404);
        }

        $validated = $request->validated();
        $action = (int) $validated['action'];
        $target = $service->targetForModule($module);

        $result = DB::transaction(function () use ($request, $service, $module, $id, $target, $action, $validated) {
            $model = $service->restrictPendingForDelegate(
                $service->queryForModule($module),
                $request->user(),
                $module,
                $target['table'],
                $target['model']
            )
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            return $service->processDelegateApproval(
                $model,
                $request->user(),
                $module,
                $target['table'],
                $action,
                $validated['note'] ?? null
            );
        });

        if (!$result['status']) {
            toast()->warning('Peringatan', $result['message']);
            return back();
        }

        $model = $result['model']->fresh($target['with']);

        if ($action === ApprovalDelegationService::STATUS_APPROVED) {
            app(ApprovalNotificationService::class)->notifyDelegatedRequestWaitingForHod($model, $module);
        } else {
            $this->notifyApplicant($model, $module, $result['approval_status']);
        }

        toast()->success('Berhasil', $service->moduleLabel($module) . ' telah ' . strtolower($result['approval_status']) . ' oleh delegasi.');
        return back();
    }

    public function izinProof(Request $request, $id, ApprovalDelegationService $service)
    {
        $target = $service->targetForModule(ApprovalDelegation::MODULE_IZIN);
        $izin = $service->restrictPendingForDelegate(
            $service->queryForModule(ApprovalDelegation::MODULE_IZIN),
            $request->user(),
            ApprovalDelegation::MODULE_IZIN,
            $target['table'],
            $target['model']
        )
            ->whereKey($id)
            ->firstOrFail();

        abort_if(blank($izin->foto) || $izin->foto === '-', 404, 'Bukti izin belum tersedia.');

        return $this->servePrivateFile($izin->foto, ['izin/']);
    }

    public function rosterAttachment(Request $request, $id, ApprovalDelegationService $service)
    {
        $target = $service->targetForModule(ApprovalDelegation::MODULE_ROSTER);
        $roster = $service->restrictPendingForDelegate(
            $service->queryForModule(ApprovalDelegation::MODULE_ROSTER),
            $request->user(),
            ApprovalDelegation::MODULE_ROSTER,
            $target['table'],
            $target['model']
        )
            ->whereKey($id)
            ->firstOrFail();

        abort_if(blank($roster->file), 404, 'Lampiran roster belum tersedia.');

        return $this->servePrivateFile('cuti-roster/' . $roster->nik_karyawan . '/' . basename($roster->file), ['cuti-roster/']);
    }

    private function notifyApplicant(Model $model, string $module, string $status): void
    {
        $user = $this->applicantUser($model);

        if (!$user) {
            return;
        }

        $target = app(ApprovalDelegationService::class)->targetForModule($module);
        $label = app(ApprovalDelegationService::class)->moduleLabel($module);

        $user->notify(new StatusPengajuanNotification([
            'judul' => 'Pengajuan ' . $label . ' ' . $status,
            'pesan' => 'Pengajuan ' . strtolower($label) . ' Anda telah ' . strtolower($status) . ' oleh Delegasi HOD.',
            'url' => route($target['route']),
            'tipe' => $label,
        ]));
    }

    private function applicantUser(Model $model)
    {
        if ($model instanceof AttendanceCorrection) {
            return $model->requester;
        }

        if ($model instanceof Cuti || $model instanceof Roster || $model instanceof RosterOffRequest) {
            return $model->user;
        }

        return null;
    }

    private function servePrivateFile(string $path, array $allowedPrefixes)
    {
        $normalizedPath = str_replace('\\', '/', ltrim($path, '/'));

        abort_if(strpos($normalizedPath, '..') !== false, 404);

        $isAllowed = collect($allowedPrefixes)
            ->contains(fn($prefix) => Str::startsWith($normalizedPath, $prefix));

        abort_unless($isAllowed, 404);

        $absolutePath = app(SensitiveFileStorageService::class)->resolvePath($normalizedPath, $allowedPrefixes);

        abort_unless($absolutePath && File::isFile($absolutePath), 404, 'Lampiran tidak ditemukan.');

        return response()->file($absolutePath, [
            'Content-Type' => File::mimeType($absolutePath) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . basename($normalizedPath) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
