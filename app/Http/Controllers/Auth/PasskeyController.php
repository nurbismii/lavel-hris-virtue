<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasskeyRequest;
use App\Services\Auth\PasskeyService;
use App\Support\EmailUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PasskeyController extends Controller
{
    public function __construct(private PasskeyService $passkeys) {}

    public function index(Request $request)
    {
        $items = $request->user()->passkeys()->latest('id')
            ->get(['id', 'name', 'created_at', 'last_used_at']);
        return $this->success('Daftar passkey berhasil dimuat.', ['passkeys' => $items]);
    }

    public function registrationOptions(PasskeyRequest $request)
    {
        return $this->success(
            'Konfirmasi pembuatan passkey pada perangkat Anda.',
            $this->passkeys->registrationOptions($request->user(), $request->session()->getId(), $request->validated('name'))
        );
    }

    public function store(PasskeyRequest $request)
    {
        $this->passkeys->register($request->user(), $request->session()->getId(), $request->validated());
        return $this->success('Passkey berhasil ditambahkan. Anda dapat menggunakannya saat login berikutnya.');
    }

    public function loginOptions(PasskeyRequest $request)
    {
        return $this->success(
            'Pilih passkey dan konfirmasi pada perangkat Anda.',
            $this->passkeys->loginOptions(
                $request->session()->getId(),
                $request->boolean('remember'),
                EmailUrl::safeRedirectPath($request->input('redirect'))
            )
        );
    }

    public function login(PasskeyRequest $request)
    {
        $result = $this->passkeys->authenticate($request->session()->getId(), $request->validated());
        $user = $result['user'];
        $user->markLastLogin();
        Auth::guard('web')->login($user, $result['remember']);
        $request->session()->regenerate();
        $redirect = $result['redirect']
            ?: EmailUrl::safeRedirectPath($request->session()->pull('url.intended'));

        return redirect()->to($redirect ?: route($user->preferredHomeRouteName(), [], false));
    }

    public function destroy(PasskeyRequest $request, string $passkey)
    {
        $this->passkeys->revoke($request->user(), $passkey);
        return $this->success('Akses passkey telah dicabut. Hapus juga passkey dari pengelola sandi perangkat jika tidak digunakan lagi.');
    }

    private function success(string $message, array $data = [])
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }
}
