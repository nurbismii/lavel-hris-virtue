(function () {
    'use strict';
    function errorMessage(response, payload) {
        if (response.status === 401 || response.status === 419) return 'Sesi login berakhir. Silakan login ulang.';
        if (response.status === 403) return 'Anda tidak memiliki akses untuk tindakan ini.';
        if (response.status === 404) return 'NIK tidak ditemukan atau berada di luar cakupan akses Anda.';
        if (response.status === 422 && payload.errors) return Object.values(payload.errors).flat()[0];
        return payload.message || (response.status >= 500 ? 'Server gagal memproses permintaan. Silakan coba kembali.' : 'Permintaan gagal diproses.');
    }
    const form = document.getElementById('warning-create-form');
    if (form) {
        const startDateInput = document.getElementById('tgl_mulai');
        const endDateInput = document.getElementById('tgl_berakhir');
        function updateValidityEnd() {
            endDateInput.value = '';
            if (!startDateInput.value || !startDateInput.validity.valid) return;
            const endDate = new Date(startDateInput.value + 'T00:00:00Z');
            if (Number.isNaN(endDate.getTime())) return;
            const originalDay = endDate.getUTCDate();
            endDate.setUTCDate(1);
            endDate.setUTCMonth(endDate.getUTCMonth() + Number(endDateInput.dataset.validityMonths));
            const lastDay = new Date(endDate.getTime());
            lastDay.setUTCMonth(lastDay.getUTCMonth() + 1, 0);
            endDate.setUTCDate(Math.min(originalDay, lastDay.getUTCDate()));
            endDateInput.value = endDate.toISOString().slice(0, 10);
        }
        startDateInput.addEventListener('input', updateValidityEnd);
        startDateInput.addEventListener('change', updateValidityEnd);
        updateValidityEnd();

        const nik = document.getElementById('employee-nik');
        const employeeQuery = document.getElementById('employee-query');
        const search = document.getElementById('find-employee');
        const submit = document.getElementById('submit-warning');
        const result = document.getElementById('employee-result');
        const options = document.getElementById('employee-options');
        const feedback = document.getElementById('employee-feedback');
        let selectedNik = null;

        function resetEmployeeSelection() {
            selectedNik = null;
            nik.value = '';
            submit.disabled = true;
            submit.title = 'Cari dan pilih karyawan terlebih dahulu';
            result.hidden = true;
        }

        function selectEmployee(employee) {
            selectedNik = employee.nik;
            nik.value = employee.nik;
            employeeQuery.value = employee.nik + ' - ' + employee.name;
            result.querySelectorAll('[data-employee-field]').forEach(function (node) {
                node.textContent = employee[node.dataset.employeeField] || '-';
            });
            options.hidden = true;
            options.replaceChildren();
            result.hidden = false;
            submit.disabled = false;
            submit.removeAttribute('title');
            feedback.textContent = 'Karyawan dipilih. Periksa identitas sebelum mengajukan.';
        }

        function showEmployees(employees, keyword) {
            options.replaceChildren();
            if (!employees.length) {
                options.hidden = true;
                feedback.textContent = 'Karyawan tidak ditemukan atau berada di luar cakupan departemen/divisi Anda.';
                return;
            }

            const exactNik = employees.find(function (employee) {
                return employee.nik === keyword;
            });
            if (exactNik) {
                selectEmployee(exactNik);
                return;
            }

            employees.forEach(function (employee) {
                const option = document.createElement('button');
                const identity = document.createElement('span');
                const organization = document.createElement('small');
                option.type = 'button';
                option.className = 'warning-employee-option';
                option.setAttribute('role', 'option');
                identity.className = 'd-block fw-semibold text-break';
                identity.textContent = employee.nik + ' — ' + employee.name;
                organization.className = 'd-block text-muted text-break mt-1';
                organization.textContent = employee.department + ' / ' + employee.division + ' / ' + employee.position;
                option.append(identity, organization);
                option.addEventListener('click', function () { selectEmployee(employee); });
                options.appendChild(option);
            });
            options.hidden = false;
            feedback.textContent = employees.length + ' karyawan ditemukan. Pilih satu karyawan dari daftar.';
        }

        employeeQuery.addEventListener('input', function () {
            resetEmployeeSelection();
            options.hidden = true;
            options.replaceChildren();
            feedback.textContent = 'Klik Cari karyawan untuk menampilkan hasil sesuai cakupan Anda.';
        });
        employeeQuery.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                search.click();
            }
        });
        search.addEventListener('click', async function () {
            const keyword = employeeQuery.value.trim();
            if (keyword.length < 2 || keyword.length > 100) {
                feedback.textContent = 'Masukkan minimal 2 dan maksimal 100 karakter NIK atau nama karyawan.';
                return;
            }
            search.disabled = true;
            resetEmployeeSelection();
            options.hidden = true;
            options.replaceChildren();
            search.textContent = 'Mencari...';
            feedback.textContent = 'Mencari karyawan dalam cakupan departemen/divisi Anda...';
            try {
                const url = new URL(search.dataset.url, window.location.href);
                url.searchParams.set('q', keyword);
                const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                const payload = await response.json().catch(function () { return {}; });
                if (!response.ok || !payload.success) throw new Error(errorMessage(response, payload));
                if (employeeQuery.value.trim() !== keyword) return;
                showEmployees(Array.isArray(payload.data) ? payload.data : [], keyword);
            } catch (error) {
                feedback.textContent = error instanceof TypeError ? 'Koneksi bermasalah. Silakan periksa jaringan dan coba kembali.' : error.message;
            } finally {
                search.disabled = false;
                search.textContent = 'Cari karyawan';
            }
        });
        form.addEventListener('submit', function (event) {
            if (!selectedNik || selectedNik !== nik.value) {
                event.preventDefault();
                feedback.textContent = 'Cari dan pilih karyawan terlebih dahulu.';
            }
        });
        if (nik.value) {
            employeeQuery.value = nik.value;
            search.click();
        }
    }
    document.querySelectorAll('[data-warning-submit]').forEach(function (target) {
        const decision = target.querySelector('[name="decision"]');
        const reason = target.querySelector('[name="reason"]');
        if (decision && reason) {
            const updateReason = function () { reason.required = decision.value === 'reject'; };
            decision.addEventListener('change', updateReason);
            updateReason();
        }
        target.addEventListener('submit', function (event) {
            if (event.defaultPrevented) return;
            if (target.dataset.submitting === 'true') { event.preventDefault(); return; }
            if (target.hasAttribute('data-warning-review') && target.dataset.confirmed !== 'true') {
                event.preventDefault();
                window.AppDialog.confirm({ icon: 'question', title: decision.value === 'approve' ? 'Terbitkan surat peringatan?' : 'Tolak pengajuan?',
                    text: decision.value === 'approve' ? 'Nomor SP akan diberikan dan QR verifikasi publik dicantumkan pada surat.' : 'Alasan penolakan akan dicatat dan ditampilkan kepada pengaju.',
                    confirmButtonText: 'Ya, proses', cancelButtonText: 'Batal' }).then(function (confirmed) {
                        if (confirmed) { target.dataset.confirmed = 'true'; target.requestSubmit(); }
                    });
                return;
            }
            if (target.hasAttribute('data-warning-verification') && target.dataset.confirmed !== 'true') {
                event.preventDefault();
                const action = target.querySelector('[name="action"]').value;
                window.AppDialog.confirm({ icon: 'warning', title: action === 'revoke' ? 'Cabut verifikasi publik?' : 'Aktifkan verifikasi publik?',
                    text: action === 'revoke' ? 'Hasil scan QR akan menampilkan status verifikasi dicabut.' : 'QR akan kembali menampilkan status dokumen sesuai masa berlakunya.',
                    confirmButtonText: 'Ya, proses', cancelButtonText: 'Batal' }).then(function (confirmed) {
                        if (confirmed) { target.dataset.confirmed = 'true'; target.requestSubmit(); }
                    });
                return;
            }
            target.dataset.submitting = 'true';
            target.querySelectorAll('button[type="submit"]').forEach(function (button) {
                button.dataset.originalText = button.textContent;
                button.disabled = true;
                button.textContent = target.dataset.loadingText || 'Memproses...';
            });
        });
    });
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('[data-warning-submit]').forEach(function (target) {
            delete target.dataset.submitting;
            delete target.dataset.confirmed;
            target.querySelectorAll('button[data-original-text]').forEach(function (button) {
                button.disabled = false;
                button.textContent = button.dataset.originalText;
            });
        });
    });
    document.querySelectorAll('[data-warning-download]').forEach(function (link) {
        link.addEventListener('click', async function (event) {
            event.preventDefault();
            if (link.dataset.busy === 'true') return;
            const text = link.textContent;
            link.dataset.busy = 'true';
            link.setAttribute('aria-disabled', 'true');
            link.textContent = 'Menyiapkan PDF...';
            try {
                const response = await fetch(link.href, { headers: { Accept: 'application/pdf, application/json' }, credentials: 'same-origin' });
                if (!response.ok) {
                    const payload = await response.json().catch(function () { return {}; });
                    throw new Error(errorMessage(response, payload));
                }
                if (!(response.headers.get('content-type') || '').includes('application/pdf')) throw new Error('PDF belum tersedia atau sesi berakhir. Muat ulang halaman untuk melihat pesan terbaru.');
                const url = URL.createObjectURL(await response.blob());
                const download = document.createElement('a');
                download.href = url;
                const disposition = response.headers.get('content-disposition') || '';
                const filename = disposition.match(/filename="?([^";]+)"?/i);
                download.download = filename ? filename[1] : 'surat-peringatan.pdf';
                document.body.appendChild(download); download.click(); download.remove();
                window.setTimeout(function () { URL.revokeObjectURL(url); }, 10000);
                const successMessage = response.headers.get('X-Warning-Letter-Verification') === 'qr'
                    ? 'Surat dengan QR verifikasi telah dikirim ke unduhan browser.'
                    : 'Surat telah dikirim ke unduhan browser.';
                window.AppDialog.alert('PDF siap', successMessage, 'success');
            } catch (error) {
                window.AppDialog.alert('Unduhan gagal', error instanceof TypeError ? 'Koneksi bermasalah. Periksa jaringan dan coba kembali.' : error.message, 'error');
            } finally {
                delete link.dataset.busy;
                link.removeAttribute('aria-disabled');
                link.textContent = text;
            }
        });
    });
}());
