<form method="GET" class="ui-panel approval-list-filter">
    <div class="ui-panel__body ui-actions ui-actions--sm-stack">
        <div class="ui-field">
            <label class="form-label" for="approval-search">{{ __('Cari nama atau NIK karyawan') }}</label>
            <input type="search" class="form-control @error('search') is-invalid @enderror" id="approval-search" name="search" value="{{ request('search') }}" maxlength="100">
            @error('search')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <p class="form-text">{{ __('Pencarian mencakup seluruh pengajuan sesuai akses Anda.') }}</p>
        </div>
        <button class="btn btn-primary ui-btn-icon" type="submit" data-loading-text="{{ __('Mencari...') }}"><i class="fas fa-search" aria-hidden="true"></i> {{ __('Cari') }}</button>
        <a href="{{ url()->current() }}" class="btn btn-outline-secondary">{{ __('Reset') }}</a>
    </div>
</form>
