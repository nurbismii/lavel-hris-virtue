(function () {
    const page = document.querySelector('.shift-settings-page');

    if (!page) {
        return;
    }

    const updateUrl = page.dataset.updateUrl;
    const divisionsUrl = page.dataset.divisionsUrl;
    const csrfToken = page.dataset.csrfToken;
    const dirtyShiftCells = new Map();
    let shiftDebounceTimer = null;

    function bindDivisionFilter() {
        if (!window.jQuery || !divisionsUrl) {
            return;
        }

        jQuery(function ($) {
            const filterDepartemen = $('#filter_departemen');
            const filterDivisi = $('#filter_divisi');

            if (!filterDepartemen.length || !filterDivisi.length) {
                return;
            }

            filterDepartemen.on('change', function () {
                const departemen = $(this).val();

                filterDivisi.prop('disabled', true).html('<option value="">Loading...</option>');

                if (!departemen) {
                    filterDivisi.html('<option value="">Semua Divisi</option>').prop('disabled', true);
                    return;
                }

                $.get(divisionsUrl, {
                    departemen: departemen
                }).done(function (response) {
                    filterDivisi.empty().append(new Option('Semua Divisi', ''));

                    response.forEach(function (item) {
                        filterDivisi.append(new Option(item.nama_divisi, item.id));
                    });

                    filterDivisi.prop('disabled', false);
                }).fail(function () {
                    filterDivisi.html('<option value="">Gagal memuat divisi</option>').prop('disabled', true);
                });
            });
        });
    }

    async function sendShiftBatch() {
        const payload = Array.from(dirtyShiftCells.values());

        if (payload.length === 0 || !updateUrl || !csrfToken) {
            return;
        }

        try {
            const response = await fetch(updateUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    data: payload.map((item) => ({
                        employee_id: item.employee_id,
                        tanggal: item.tanggal,
                        shift_id: item.shift_id || null
                    }))
                })
            });

            let responseData = null;

            try {
                responseData = await response.json();
            } catch (error) {
                responseData = null;
            }

            if (!response.ok) {
                throw new Error(responseData && responseData.message ? responseData.message : 'Update gagal');
            }

            payload.forEach((item) => {
                const newShiftId = String(item.shift_id || '');
                const selectedOption = item.element.find('option:selected');
                const shiftLabel = newShiftId ? selectedOption.text() : 'Pola Kerja';

                item.element.data('shift-id', newShiftId);
                item.element.closest('td').removeClass('table-warning').addClass('table-success');
                item.element.siblings('small').text(shiftLabel);

                setTimeout(() => {
                    item.element.closest('td').removeClass('table-success');
                }, 800);
            });

            dirtyShiftCells.clear();
        } catch (error) {
            payload.forEach((item) => {
                item.element.val(String(item.element.data('shift-id') || ''));
                item.element.closest('td').removeClass('table-warning');
            });

            alert(error.message || 'Update gagal');
        }
    }

    function bindShiftChanges() {
        if (!window.jQuery) {
            return;
        }

        jQuery(document).on('change', '.shift-assignment-select', function () {
            const select = jQuery(this);
            const employee = select.data('employee');
            const date = select.data('date');
            const oldShiftId = String(select.data('shift-id') || '');
            const newShiftId = String(select.val() || '');

            if (newShiftId === oldShiftId) {
                return;
            }

            dirtyShiftCells.set(`${employee}_${date}`, {
                employee_id: employee,
                tanggal: date,
                shift_id: newShiftId,
                element: select
            });

            select.closest('td').addClass('table-warning');

            clearTimeout(shiftDebounceTimer);
            shiftDebounceTimer = setTimeout(sendShiftBatch, 700);
        });
    }

    bindDivisionFilter();
    bindShiftChanges();
})();
