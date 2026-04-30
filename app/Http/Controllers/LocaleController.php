<?php

namespace App\Http\Controllers;

use App\Http\Requests\Localization\ChangeLocaleRequest;
use App\Services\Localization\LocaleService;

class LocaleController extends Controller
{
    public function update(ChangeLocaleRequest $request, LocaleService $localeService, string $locale)
    {
        $locale = $localeService->remember($request, $locale);
        $localeService->apply($locale);

        return back()->with('status', __('common.language_changed'));
    }
}
