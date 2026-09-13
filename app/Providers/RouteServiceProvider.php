<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/admin/home';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/api.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('passkeys', function (Request $request) {
            $identity = $request->user()
                ? 'user:' . $request->user()->getAuthIdentifier()
                : 'session:' . hash('sha256', $request->session()->getId());

            return [
                Limit::perMinute((int) config('passkeys.requests_per_session', 20))->by($identity),
                Limit::perMinute((int) config('passkeys.requests_per_ip', 600))->by('ip:' . $request->ip()),
            ];
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60);
        });

        RateLimiter::for('mobile-app-version', function (Request $request) {
            return Limit::perMinute((int) config('hris.mobile_app.rate_limit_per_minute', 600))
                ->by($request->ip());
        });

        RateLimiter::for('presensi', function (Request $request) {
            return Limit::perMinute(8)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('gps-log', function (Request $request) {
            return Limit::perMinute(30)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('warning-letter-verification', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });
    }
}
