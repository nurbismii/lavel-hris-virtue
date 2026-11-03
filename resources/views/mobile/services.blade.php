@extends('layouts.app')
@section('title', __('navigation.services'))
@section('content')
<main class="page-inner native-services">
    <h1>{{ __('navigation.services') }}</h1>
    <p>{{ __('navigation.services_hint') }}</p>
    @include('partials.sidebar', ['serviceDirectory' => true])
    <div class="native-account">
        <a href="{{ route('update.akun') }}"><i class="fas fa-cog" aria-hidden="true"></i>{{ __('navigation.account_settings') }}</a>
        <form method="POST" action="{{ route('logout') }}" data-submit-lock>
            @csrf
            <button type="submit" data-loading-text="{{ __('navigation.logout') }}…"><i class="fas fa-sign-out-alt" aria-hidden="true"></i>{{ __('navigation.logout') }}</button>
        </form>
    </div>
</main>
@endsection
