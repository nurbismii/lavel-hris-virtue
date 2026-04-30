<?php

namespace App\Http\Middleware;

use App\Services\Localization\LocaleService;
use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    private $localeService;

    public function __construct(LocaleService $localeService)
    {
        $this->localeService = $localeService;
    }

    public function handle(Request $request, Closure $next)
    {
        $locale = $this->localeService->resolve(
            $request->session()->get(LocaleService::SESSION_KEY)
        );

        $this->localeService->apply($locale);

        return $next($request);
    }
}
