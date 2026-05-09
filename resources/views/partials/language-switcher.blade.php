@php
    $localeService = app(\App\Services\Localization\LocaleService::class);
    $availableLocales = $supportedLocales ?? $localeService->supportedLocales();
    $activeLocale = $currentLocale ?? app()->getLocale();
    $activeLocaleMeta = $availableLocales[$activeLocale] ?? reset($availableLocales);
    $switcherClass = $class ?? '';
    $switcherId = $id ?? 'languageSwitcherDropdown';
@endphp

@if(count($availableLocales) > 1)
    <div class="dropdown language-switcher {{ $switcherClass }}">
        <button
            class="btn btn-light btn-sm dropdown-toggle language-switcher__toggle"
            type="button"
            id="{{ $switcherId }}"
            data-bs-toggle="dropdown"
            aria-expanded="false">
            <i class="fas fa-globe-asia"></i>
            <span>{{ $activeLocaleMeta['short_label'] ?? strtoupper($activeLocale) }}</span>
        </button>

        <div class="dropdown-menu dropdown-menu-end shadow border-0 language-switcher__menu" aria-labelledby="{{ $switcherId }}">
            @foreach($availableLocales as $localeCode => $localeMeta)
                <form method="POST" action="{{ route('locale.update', $localeCode) }}" class="m-0">
                    @csrf
                    <button
                        type="submit"
                        class="dropdown-item language-switcher__item {{ $activeLocale === $localeCode ? 'active' : '' }}"
                        {{ $activeLocale === $localeCode ? 'disabled' : '' }}>
                        <span class="language-switcher__code">{{ $localeMeta['short_label'] }}</span>
                        <span>{{ $localeMeta['native_name'] }}</span>
                    </button>
                </form>
            @endforeach
        </div>
    </div>
@endif
