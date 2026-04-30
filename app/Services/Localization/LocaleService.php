<?php

namespace App\Services\Localization;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;

class LocaleService
{
    public const SESSION_KEY = 'locale';

    public function supportedLocales(): array
    {
        return config('localization.supported_locales', []);
    }

    public function supportedLocaleCodes(): array
    {
        return array_keys($this->supportedLocales());
    }

    public function defaultLocale(): string
    {
        $defaultLocale = config('app.locale', 'id');

        if ($this->isSupported($defaultLocale)) {
            return $defaultLocale;
        }

        return $this->supportedLocaleCodes()[0] ?? 'id';
    }

    public function isSupported(?string $locale): bool
    {
        return is_string($locale) && array_key_exists($locale, $this->supportedLocales());
    }

    public function resolve(?string $locale): string
    {
        if ($this->isSupported($locale)) {
            return $locale;
        }

        return $this->defaultLocale();
    }

    public function apply(string $locale): string
    {
        $locale = $this->resolve($locale);

        App::setLocale($locale);
        Carbon::setLocale($locale);

        View::share('currentLocale', $locale);
        View::share('supportedLocales', $this->supportedLocales());

        return $locale;
    }

    public function remember(Request $request, string $locale): string
    {
        $locale = $this->resolve($locale);

        $request->session()->put(self::SESSION_KEY, $locale);

        return $locale;
    }

    public function htmlLang(?string $locale = null): string
    {
        $locale = $this->resolve($locale ?: App::getLocale());

        return $this->supportedLocales()[$locale]['html_lang'] ?? str_replace('_', '-', $locale);
    }
}
