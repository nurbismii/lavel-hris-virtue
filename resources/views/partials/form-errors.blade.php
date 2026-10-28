@if($errors->any())
<div class="alert alert-danger" role="alert">
    <strong>{{ __('Data belum disimpan. Periksa kembali isian berikut.') }}</strong>
    <ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
</div>
@endif
