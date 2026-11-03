<!DOCTYPE html>
<html lang="{{ app(\App\Services\Localization\LocaleService::class)->htmlLang() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#126247">
    <title>{{ __('Download V-People App') }}</title>
    <link rel="icon" href="{{ versioned_asset('assets/img/kaiadmin/icon-2.png') }}">
    <link rel="stylesheet" href="{{ versioned_asset('assets/css/download-app.css') }}">
</head>
<body data-ios-required="{{ config('hris.mobile_app.ios_required', false) ? '1' : '0' }}">
    <main class="download-card">
        <img src="{{ versioned_asset('assets/img/kaiadmin/icon-2.png') }}" class="download-logo" alt="V-People" width="72" height="72">
        <span class="download-eyebrow">V-PEOPLE · PT VDNI</span>
        <h1>{{ __('Layanan karyawan dalam satu aplikasi') }}</h1>
        <p class="download-intro">
            @if(config('hris.mobile_app.ios_required', false))
                {{ __('Login dari ponsel dan tablet menggunakan aplikasi V-People. Pengguna laptop atau komputer tetap dapat masuk melalui web.') }}
            @else
                {{ __('Pengguna Android masuk melalui aplikasi V-People. iPhone, iPad, laptop, dan komputer dapat masuk melalui browser.') }}
            @endif
        </p>
        <section class="download-platform" data-platform="android" aria-labelledby="androidTitle">
            <h2 id="androidTitle">Android</h2>
            <p>{{ __('Unduh aplikasi, selesaikan pemasangan, lalu masuk dengan akun V-People Anda.') }}</p>
            @if($androidUrl)
                <a href="{{ $androidUrl }}" class="download-button" rel="noopener noreferrer" data-download>{{ __('Unduh aplikasi Android') }}</a>
            @else
                <p class="download-notice" role="status">{{ __('Aplikasi Android belum tersedia untuk diunduh. Silakan hubungi HR.') }}</p>
            @endif
        </section>
        <section class="download-platform" data-platform="ios" aria-labelledby="iosTitle">
            <h2 id="iosTitle">iPhone &amp; iPad</h2>
            @if(!config('hris.mobile_app.ios_required', false))
                <p>{{ __('Untuk sementara, pengguna iPhone dan iPad dapat masuk melalui browser tanpa memasang aplikasi.') }}</p>
                <a href="{{ route('login') }}" class="download-button">{{ __('Masuk melalui browser') }}</a>
            @elseif($iosUrl)
                <p>{{ __('Pasang aplikasi melalui App Store atau undangan TestFlight resmi perusahaan.') }}</p>
                <a href="{{ $iosUrl }}" class="download-button" rel="noopener noreferrer" data-download>{{ __('Unduh aplikasi iOS') }}</a>
            @else
                <p class="download-notice" role="status">{{ __('Versi iOS belum tersedia. Untuk sementara, gunakan laptop atau komputer untuk masuk, atau hubungi HR.') }}</p>
            @endif
        </section>
        <a href="vpeople://dashboard" class="download-button download-button--secondary" id="openAppButton">{{ __('Buka aplikasi yang sudah terpasang') }}</a>
        <p id="downloadStatus" class="download-status" role="status" aria-live="polite"
            data-opening="{{ __('Membuka aplikasi… Jika tidak terbuka, pasang aplikasi melalui tautan di atas.') }}"
            data-downloading="{{ __('Tautan unduhan dibuka. Selesaikan pemasangan, lalu buka V-People.') }}"></p>
        <a href="{{ route('login') }}" class="download-desktop" id="desktopLogin">{{ __('Masuk melalui laptop / komputer') }}</a>
        <p class="download-footer">{{ __('Gunakan akun yang sama dengan akun HRIS Anda. Jangan bagikan password kepada siapa pun.') }}</p>
    </main>
    <script src="{{ versioned_asset('assets/js/download-app.js?policy=ios-browser') }}"></script>
</body>
</html>
