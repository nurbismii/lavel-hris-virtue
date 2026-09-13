<?php

namespace App\Http\Middleware;

use App\Services\Auth\PasskeyService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class EnsurePasskeysAvailable
{
    public function handle(Request $request, Closure $next)
    {
        try {
            abort_unless(app(PasskeyService::class)->available(), 503,
                'Login passkey belum tersedia. Gunakan password atau hubungi admin.');
            $response = $next($request);
        } catch (ValidationException $exception) {
            $response = response()->json([
                'success' => false,
                'message' => collect($exception->errors())->flatten()->first(),
                'errors' => $exception->errors(),
            ], 422);
        } catch (HttpExceptionInterface $exception) {
            $response = response()->json(['success' => false, 'message' => $exception->getMessage()
                ?: 'Permintaan passkey tidak dapat diproses.'], $exception->getStatusCode());
        } catch (Throwable $exception) {
            // Never log request bodies, credential material, passwords or client JSON.
            Log::error('passkey.request_failed', ['exception' => get_class($exception)]);
            $response = response()->json(['success' => false,
                'message' => 'Passkey gagal diproses. Silakan coba lagi atau gunakan password.'], 500);
        }

        $response->headers->set('Cache-Control', 'no-store, private');
        return $response;
    }
}
