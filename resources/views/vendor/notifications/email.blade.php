@component('mail::layout')

{{-- HEADER --}}
@slot('header')
@component('mail::header', ['url' => config('app.url')])
<span style="font-size:18px; font-weight:bold;">
    V-People HRIS
</span>
<br>
<span style="font-size:12px;">
    PT Virtue Dragon Nickel Industry
</span>
@endcomponent
@endslot


{{-- GREETING --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
# Halo,
@endif


{{-- INTRO LINES --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach


{{-- ACTION BUTTON --}}
@if (isset($actionText))
@component('mail::button', [
'url' => $actionUrl,
'color' => 'primary'
])
{{ $actionText }}
@endcomponent
@endif


{{-- OUTRO LINES --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach


{{-- SALUTATION --}}
@if (! empty($salutation))
{{ $salutation }}
@else
Salam Hormat,
Tim HRIS V-People
PT Virtue Dragon Nickel Industry
@endif


{{-- FOOTER --}}
@slot('footer')
@component('mail::footer')
© {{ date('Y') }} PT Virtue Dragon Nickel Industry
All rights reserved.
@endcomponent
@endslot

@endcomponent