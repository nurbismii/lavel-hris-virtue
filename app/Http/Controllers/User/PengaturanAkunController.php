<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\Recruitment\RecruitmentDocumentClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PengaturanAkunController extends Controller
{
    public function index(RecruitmentDocumentClient $documents)
    {
        $user = auth()->user()->load('employee.divisi.departemen');
        $apdPhotoUrl = null;

        if ($user->employee && filled(config('services.recruitment.base_url')) && filled(config('services.recruitment.token'))) {
            $noKtp = (string) Employee::whereKey($user->employee->nik)->value('no_ktp');

            if (preg_match('/^[0-9]{16}$/', $noKtp)) {
                try {
                    $payload = $documents->lookupByNoKtp($noKtp, false);
                    $photo = collect($payload['documents'])->firstWhere('type', 'pas_foto_apd');

                    if ($payload['found'] && in_array($photo['mime'] ?? null, ['image/jpeg', 'image/png'], true)) {
                        $apdPhotoUrl = $photo['preview_url'];
                    }
                } catch (RuntimeException $exception) {
                    Log::warning('Foto APD dari V-Hire tidak tersedia.', ['code' => $exception->getCode()]);
                }
            }
        }

        return view('user.pengaturan-akun.profile', compact('user', 'apdPhotoUrl'));
    }

    public function SetIndex()
    {
        return view('user.pengaturan-akun.index');
    }

    public function store(Request $request)
    {
        $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user = auth()->user();

        // Cek password lama
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors([
                'current_password' => 'Password lama tidak sesuai.'
            ]);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return back()->with('success', 'Password berhasil diperbarui.');
    }
}
