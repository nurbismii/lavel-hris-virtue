(function() {
    const root = document.querySelector('.dashboard-home-page');

    if (!root) {
        return;
    }

    const parseJson = function(value, fallback) {
        try {
            return JSON.parse(value || JSON.stringify(fallback));
        } catch (error) {
            return fallback;
        }
    };

    const ageLabels = parseJson(root.dataset.ageLabels, []);
    const ageTotals = parseJson(root.dataset.ageTotals, []);
    const monthlyLabels = parseJson(root.dataset.monthlyLabels, []);
    const monthlyMasuk = parseJson(root.dataset.monthlyMasuk, []);
    const monthlyKeluar = parseJson(root.dataset.monthlyKeluar, []);
    const genderL = Number(root.dataset.genderL || 0);
    const genderP = Number(root.dataset.genderP || 0);
    const masuk = Number(root.dataset.masuk || 0);
    const keluar = Number(root.dataset.keluar || 0);
    const softGridOptions = {
        color: 'rgba(148, 163, 184, 0.18)',
        zeroLineColor: 'rgba(148, 163, 184, 0.24)',
        drawBorder: false
    };

    const genderChart = document.getElementById('chartGender');
    const mutasiChart = document.getElementById('chartMutasi');
    const ageRangeChart = document.getElementById('chartAgeRange');
    const monthlyMutasiChart = document.getElementById('chartMonthlyMutasi');

    if (typeof Chart !== 'undefined' && genderChart) {
        new Chart(genderChart, {
            type: 'pie',
            data: {
                labels: ['Laki-laki', 'Perempuan'],
                datasets: [{
                    data: [genderL, genderP],
                    backgroundColor: ['#1d7af3', '#ee22dd'],
                    borderWidth: 0,
                    hoverBorderWidth: 0
                }]
            }
        });
    }

    if (typeof Chart !== 'undefined' && mutasiChart) {
        new Chart(mutasiChart, {
            type: 'bar',
            data: {
                labels: ['Masuk', 'Keluar'],
                datasets: [{
                    label: 'Jumlah Karyawan',
                    data: [masuk, keluar],
                    backgroundColor: ['#1d7af3', '#f3545d'],
                    borderColor: 'transparent',
                    borderWidth: 0,
                    hoverBorderWidth: 0
                }]
            },
            options: {
                scales: {
                    xAxes: [{
                        gridLines: softGridOptions
                    }],
                    yAxes: [{
                        gridLines: softGridOptions,
                        ticks: {
                            beginAtZero: true,
                            precision: 0,
                            maxTicksLimit: 6
                        }
                    }]
                }
            }
        });
    }

    if (typeof Chart !== 'undefined' && ageRangeChart) {
        new Chart(ageRangeChart, {
            type: 'bar',
            data: {
                labels: ageLabels,
                datasets: [{
                    label: 'Jumlah Karyawan',
                    data: ageTotals,
                    backgroundColor: '#1572e8',
                    borderColor: 'transparent',
                    borderWidth: 0,
                    hoverBorderWidth: 0
                }]
            },
            options: {
                scales: {
                    xAxes: [{
                        gridLines: softGridOptions
                    }],
                    yAxes: [{
                        gridLines: softGridOptions,
                        ticks: {
                            beginAtZero: true,
                            precision: 0,
                            maxTicksLimit: 7
                        }
                    }]
                }
            }
        });
    }

    if (typeof Chart !== 'undefined' && monthlyMutasiChart) {
        new Chart(monthlyMutasiChart, {
            type: 'bar',
            data: {
                labels: monthlyLabels,
                datasets: [
                    {
                        label: 'Masuk',
                        data: monthlyMasuk,
                        backgroundColor: '#31ce36',
                        borderColor: 'transparent',
                        borderWidth: 0,
                        hoverBorderWidth: 0
                    },
                    {
                        label: 'Keluar',
                        data: monthlyKeluar,
                        backgroundColor: '#f3545d',
                        borderColor: 'transparent',
                        borderWidth: 0,
                        hoverBorderWidth: 0
                    }
                ]
            },
            options: {
                scales: {
                    xAxes: [{
                        gridLines: softGridOptions
                    }],
                    yAxes: [{
                        gridLines: softGridOptions,
                        ticks: {
                            beginAtZero: true,
                            precision: 0,
                            maxTicksLimit: 6
                        }
                    }]
                }
            }
        });
    }

    const container = document.getElementById('dashboard-upload-progress-list');

    if (!container) {
        return;
    }

    const progressUrl = container.dataset.progressUrl;
    const deleteConfirmMessage = container.dataset.deleteConfirm || 'Hapus progress ini?';

    function statusBadge(item) {
        return `<span class="badge bg-${item.status_class}">${item.status_label}</span>`;
    }

    function deleteButton(item) {
        if (!item.delete_url) {
            return '';
        }

        return `
            <button
                type="button"
                class="btn btn-sm btn-link text-muted p-0 upload-progress-delete"
                data-delete-url="${item.delete_url}"
                aria-label="Hapus progress ${item.label}">
                <i class="fas fa-times"></i>
            </button>
        `;
    }

    function renderItems(items) {
        if (!Array.isArray(items) || items.length === 0) {
            container.innerHTML = '<div class="alert alert-light border mb-0 small text-muted">Belum ada progress upload yang berjalan atau baru selesai.</div>';
            return;
        }

        container.innerHTML = `<div class="row g-3">${items.map((item) => `
            <div class="col-md-6 col-xl-4">
                <div class="upload-progress-card p-3">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <div>
                            <div class="fw-semibold">${item.label}</div>
                            <div class="upload-progress-card__meta">Update ${item.updated_at_human}</div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            ${statusBadge(item)}
                            ${deleteButton(item)}
                        </div>
                    </div>
                    <div class="progress mb-2" style="height: 8px;">
                        <div class="progress-bar bg-${item.status_class}" role="progressbar" style="width: ${item.progress_percentage}%;" aria-valuenow="${item.progress_percentage}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="d-flex justify-content-between upload-progress-card__counts">
                        <span>${item.processed_entries}/${item.total_entries || 0} file</span>
                        <span>${item.progress_percentage}%</span>
                    </div>
                    <div class="upload-progress-card__meta mt-2">
                        Berhasil ${item.success_count} file, dilewati ${item.skipped_count} file.
                    </div>
                </div>
            </div>
        `).join('')}</div>`;
    }

    async function refreshProgress() {
        try {
            const response = await fetch(progressUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                }
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            renderItems(data.items || []);
        } catch (error) {
            console.error('Gagal memuat progress upload dashboard.', error);
        }
    }

    container.addEventListener('click', async function(event) {
        const button = event.target.closest('.upload-progress-delete');

        if (!button) {
            return;
        }

        event.preventDefault();

        const confirmed = await window.AppDialog.confirm({
            title: 'Konfirmasi Hapus',
            text: deleteConfirmMessage,
            icon: 'warning',
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal',
            dangerMode: true
        });

        if (!confirmed) {
            return;
        }

        try {
            const response = await fetch(button.dataset.deleteUrl, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                }
            });

            if (!response.ok) {
                throw new Error('Gagal menghapus progress.');
            }

            await refreshProgress();
        } catch (error) {
            console.error(error);
            window.AppDialog.alert('Gagal', 'Gagal menghapus progress upload.', 'error');
        }
    });

    window.setInterval(refreshProgress, 5000);
})();
