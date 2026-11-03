(function () {
    'use strict';

    function readFix(nonce, key) {
        return new Promise(function (resolve, reject) {
            const timer = setTimeout(function () {
                cleanup();
                reject(new Error('Perangkat belum memberikan lokasi terverifikasi. Periksa izin lokasi dan versi aplikasi.'));
            }, 30000);
            function cleanup() {
                clearTimeout(timer);
                window.removeEventListener('vpeople:gps', received);
            }
            function received(event) {
                if (!event.detail || event.detail.nonce !== nonce) return;
                cleanup();
                if (event.detail.proof) resolve(event.detail.proof);
                else reject(new Error(event.detail.error || 'Lokasi perangkat tidak dapat diverifikasi.'));
            }
            window.addEventListener('vpeople:gps', received);
            window.location.assign('vpeople://gps-proof?nonce=' + encodeURIComponent(nonce) + '&key=' + encodeURIComponent(key));
        });
    }

    window.requestPresensiGps = async function (url, attendanceId, purpose) {
        const abort = new AbortController();
        const timer = setTimeout(() => abort.abort(), 10000);
        let challenge;
        try {
            const response = await fetch(url, {
                method: 'POST', credentials: 'same-origin', signal: abort.signal,
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                body: JSON.stringify({attendance_challenge_id: attendanceId, purpose: purpose})
            });
            if ([401, 419].includes(response.status)) throw new Error('Sesi berakhir. Login kembali untuk melanjutkan.');
            if (response.status === 429) throw new Error('Terlalu banyak permintaan GPS. Tunggu sebentar lalu coba lagi.');
            const json = await response.json();
            if (!response.ok || !json.success) throw new Error(response.status < 500 && json.message || 'Pemeriksaan GPS sedang tidak tersedia.');
            challenge = json.data;
        } catch (err) {
            if (err.name === 'AbortError' || err instanceof TypeError || err instanceof SyntaxError) {
                throw new Error('Tidak dapat menghubungi server untuk verifikasi GPS. Periksa koneksi, lalu coba lagi.');
            }
            throw err;
        } finally {
            clearTimeout(timer);
        }
        return readFix(challenge.nonce, challenge.key);
    };
})();
