@if(config('passkeys.enabled'))
@include('partials.passkeys-assets')
<section class="passkey-card mt-4" data-passkeys="manage" data-action-loading-scope="ignore"
    data-list-url="{{ route('passkeys.index') }}"
    data-options-url="{{ route('passkeys.register.options') }}"
    data-verify-url="{{ route('passkeys.register.store') }}"
    data-delete-url="{{ route('passkeys.destroy', ['passkey' => 0]) }}"
    data-csrf="{{ csrf_token() }}" aria-labelledby="passkey-title">
    <div class="passkey-card__header">
        <span class="passkey-icon" aria-hidden="true"><i class="fas fa-fingerprint"></i></span>
        <div>
            <h5 id="passkey-title">Masuk dengan passkey</h5>
            <p>Gunakan sidik jari, wajah, atau PIN perangkat untuk login lebih praktis.</p>
        </div>
    </div>
    <div class="passkey-card__body">
        <p class="passkey-note">Data biometrik tetap dikelola perangkat Anda. Daftarkan passkey hanya pada perangkat atau pengelola sandi pribadi.</p>
        <p class="passkey-status" data-passkey-status role="status" aria-live="polite">Memuat daftar passkey...</p>
        <div data-passkey-list class="passkey-list"></div>
        <button type="button" data-passkey-refresh class="btn btn-sm btn-outline-secondary mb-3">Muat ulang daftar</button>
        <form data-passkey-register class="passkey-form" autocomplete="off">
            <div class="mb-3">
                <label for="passkey-name" class="form-label">Nama passkey</label>
                <input id="passkey-name" name="name" class="form-control" maxlength="80"
                    placeholder="Contoh: iPhone pribadi" required>
            </div>
            <div class="mb-3">
                <label for="passkey-password" class="form-label">Password akun saat ini</label>
                <input id="passkey-password" name="current_password" type="password"
                    class="form-control" autocomplete="current-password" maxlength="1024" required>
                <small class="text-muted">Konfirmasi keamanan sebelum menambahkan akses login baru.</small>
            </div>
            <button type="submit" class="btn btn-primary passkey-button" data-passkey-create>
                <i class="fas fa-plus me-2" aria-hidden="true"></i>Tambahkan passkey
            </button>
        </form>
        <p class="passkey-note mt-3 mb-0">Password tetap dapat digunakan jika passkey tidak tersedia. Mencabut passkey mencegah login berikutnya, tetapi tidak mengakhiri sesi yang sudah login.</p>
    </div>
</section>
@endif
