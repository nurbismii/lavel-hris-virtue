(function () {
    'use strict';

    const decode = value => Uint8Array.from(atob(value.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));
    const encode = buffer => btoa(Array.from(new Uint8Array(buffer), c => String.fromCharCode(c)).join(''))
        .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

    document.querySelectorAll('[data-passkeys]').forEach(root => {
        const status = root.querySelector('[data-passkey-status]');
        let busy = false;
        const supported = window.isSecureContext && !!window.PublicKeyCredential
            && !!navigator.credentials && typeof navigator.credentials.get === 'function';
        const setStatus = (message, error = false) => {
            status.textContent = message;
            status.dataset.error = String(error);
        };
        const feedback = async (message, error = false) => {
            setStatus(message, error);
            if (window.Swal && typeof window.Swal.fire === 'function') {
                await window.Swal.fire({ icon: error ? 'error' : 'success', title: error ? 'Belum berhasil' : 'Berhasil',
                    text: message, confirmButtonText: 'OK' });
            }
        };
        const request = async (url, body, method = 'POST') => {
            const controller = new AbortController();
            const timer = setTimeout(() => controller.abort(), 20000);
            try {
                const response = await fetch(url, {
                    method, credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': root.dataset.csrf },
                    ...(method === 'GET' ? {} : { body: JSON.stringify(body || {}) })
                });
                if (response.redirected) throw new Error('Sesi atau akses halaman berubah. Muat ulang halaman atau login dengan password.');
                const data = await response.json().catch(() => null);
                if (!response.ok || !data || data.success !== true) {
                    const messages = {
                        401: 'Sesi login berakhir. Silakan login ulang.',
                        419: 'Sesi halaman berakhir. Muat ulang halaman lalu coba lagi.',
                        403: 'Akses ditolak. Pastikan email akun telah diverifikasi.',
                        429: 'Terlalu banyak percobaan. Tunggu satu menit lalu coba lagi.',
                        500: 'Server belum dapat memproses passkey. Coba lagi atau gunakan password.'
                    };
                    throw new Error(messages[response.status] || (data && data.message) || 'Passkey gagal diproses. Silakan coba lagi.');
                }
                return data;
            } catch (error) {
                if (error.name === 'AbortError' || error instanceof TypeError) {
                    throw new Error('Koneksi bermasalah atau waktu tunggu habis. Periksa koneksi, lalu muat ulang daftar sebelum mencoba kembali.');
                }
                throw error;
            } finally { clearTimeout(timer); }
        };
        const run = async (button, action) => {
            if (busy) return;
            busy = true;
            const controls = Array.from(root.querySelectorAll('button'));
            controls.forEach(control => { control.disabled = true; });
            const original = button.innerHTML;
            button.textContent = 'Memproses...';
            root.setAttribute('aria-busy', 'true');
            try { await action(); }
            catch (error) {
                const messages = {
                    NotAllowedError: 'Verifikasi dibatalkan, waktu habis, atau passkey tidak tersedia. Coba lagi atau gunakan password.',
                    InvalidStateError: 'Passkey ini sudah terdaftar pada perangkat. Gunakan passkey lain.',
                    SecurityError: 'Alamat website tidak sesuai konfigurasi passkey. Hubungi admin atau gunakan password.',
                    NotSupportedError: 'Perangkat atau browser belum mendukung pilihan passkey ini. Gunakan password.'
                };
                await feedback(messages[error.name] || error.message || 'Passkey gagal diproses.', true);
            } finally {
                busy = false;
                button.innerHTML = original;
                root.removeAttribute('aria-busy');
                root.querySelectorAll('button').forEach(control => {
                    control.disabled = !supported && control.matches('[data-passkey-login], [data-passkey-create]');
                });
            }
        };
        const prepare = data => {
            const key = data.publicKey;
            key.challenge = decode(key.challenge);
            if (key.user) key.user.id = decode(key.user.id);
            ['excludeCredentials', 'allowCredentials'].forEach(field => {
                if (key[field]) key[field].forEach(credential => { credential.id = decode(credential.id); });
            });
            return { publicKey: key };
        };
        const serialize = (credential, challengeId) => {
            if (!credential) throw new Error('Tidak ada passkey yang dipilih. Silakan coba lagi.');
            const response = { clientDataJSON: encode(credential.response.clientDataJSON) };
            ['attestationObject', 'authenticatorData', 'signature', 'userHandle'].forEach(field => {
                if (credential.response[field]) response[field] = encode(credential.response[field]);
            });
            return { id: encode(credential.rawId), type: credential.type, response, challenge_id: challengeId };
        };

        if (!supported) {
            setStatus('Passkey memerlukan HTTPS dan browser yang mendukung. Gunakan password untuk login.', true);
            root.querySelectorAll('[data-passkey-login], [data-passkey-create]').forEach(button => { button.disabled = true; });
        }

        if (root.dataset.passkeys === 'login') {
            const button = root.querySelector('[data-passkey-login]');
            button.addEventListener('click', () => run(button, async () => {
                const options = await request(root.dataset.optionsUrl, {
                    remember: !!document.querySelector('#remember:checked'),
                    redirect: document.querySelector('input[name="redirect"]')?.value || null
                });
                setStatus(options.message);
                const credential = await navigator.credentials.get(prepare(options.data));
                const result = await request(root.dataset.verifyUrl, serialize(credential, options.data.challenge_id));
                await feedback(result.message);
                window.location.assign(result.data.redirect);
            }));
            return;
        }

        const list = root.querySelector('[data-passkey-list]');
        const formatDate = value => value ? new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : 'Belum digunakan';
        const load = async () => {
            const result = await request(root.dataset.listUrl, null, 'GET');
            list.replaceChildren();
            result.data.passkeys.forEach(passkey => {
                const row = document.createElement('div');
                row.className = 'passkey-item';
                const details = document.createElement('div');
                details.className = 'passkey-item__details';
                const name = document.createElement('strong');
                name.textContent = passkey.name;
                const dates = document.createElement('small');
                dates.textContent = 'Ditambahkan ' + formatDate(passkey.created_at) + ' · Terakhir dipakai: ' + formatDate(passkey.last_used_at);
                details.append(name, dates);
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'btn btn-sm btn-outline-danger';
                remove.textContent = 'Cabut akses';
                remove.disabled = busy;
                remove.addEventListener('click', () => run(remove, async () => {
                    if (!window.Swal || typeof window.Swal.fire !== 'function') {
                        throw new Error('Dialog keamanan belum tersedia. Muat ulang halaman lalu coba lagi.');
                    }
                    const confirmed = await window.Swal.fire({ title: 'Cabut akses passkey?',
                        text: 'Passkey “' + passkey.name + '” tidak dapat digunakan untuk login berikutnya. Masukkan password akun untuk melanjutkan.',
                        input: 'password', inputAttributes: { autocomplete: 'current-password', maxlength: '1024', 'aria-label': 'Password akun saat ini' },
                        showCancelButton: true, confirmButtonText: 'Cabut akses', cancelButtonText: 'Batal',
                        inputValidator: value => !value ? 'Masukkan password akun saat ini.' : undefined });
                    if (!(confirmed.isConfirmed || confirmed.value)) return;
                    const url = root.dataset.deleteUrl.replace(/\/0$/, '/' + encodeURIComponent(passkey.id));
                    const response = await request(url, { current_password: confirmed.value }, 'DELETE');
                    await feedback(response.message);
                    await load();
                }));
                row.append(details, remove);
                list.append(row);
            });
            if (!result.data.passkeys.length) {
                const empty = document.createElement('div');
                empty.className = 'passkey-empty';
                empty.textContent = 'Belum ada passkey. Tambahkan passkey pertama melalui formulir di bawah.';
                list.append(empty);
            }
            setStatus(supported ? result.data.passkeys.length + ' passkey terdaftar pada akun Anda.'
                : 'Browser ini belum mendukung pembuatan passkey. Anda tetap dapat mencabut akses passkey lama.', !supported);
        };
        const refresh = root.querySelector('[data-passkey-refresh]');
        refresh.addEventListener('click', () => run(refresh, load));
        const form = root.querySelector('[data-passkey-register]');
        form.addEventListener('submit', event => {
            event.preventDefault();
            if (!supported) return;
            run(root.querySelector('[data-passkey-create]'), async () => {
                const body = { name: form.elements.name.value, current_password: form.elements.current_password.value };
                let options;
                try { options = await request(root.dataset.optionsUrl, body); }
                finally { form.elements.current_password.value = ''; body.current_password = ''; }
                setStatus(options.message);
                const credential = await navigator.credentials.create(prepare(options.data));
                const result = await request(root.dataset.verifyUrl, serialize(credential, options.data.challenge_id));
                form.reset();
                await feedback(result.message);
                await load();
            });
        });
        run(refresh, load);
    });
})();
