(function () {
    if (!window.jQuery || !jQuery.fn || !jQuery.fn.select2) {
        return;
    }

    jQuery(function ($) {
        $('.js-overtime-employee-select').each(function () {
            const $select = $(this);

            if ($select.data('select2')) {
                return;
            }

            $select.select2({
                width: '100%',
                placeholder: $select.data('placeholder') || 'Cari karyawan',
                allowClear: true,
                minimumInputLength: 2,
                ajax: {
                    url: $select.data('search-url'),
                    dataType: 'json',
                    delay: 300,
                    data: function (params) {
                        return {
                            q: params.term || '',
                            page: params.page || 1
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.results || [],
                            pagination: data.pagination || {
                                more: false
                            }
                        };
                    },
                    cache: true
                },
                language: {
                    inputTooShort: function () {
                        return 'Ketik minimal 2 karakter nama atau NIK karyawan';
                    },
                    noResults: function () {
                        return 'Karyawan tidak ditemukan';
                    },
                    searching: function () {
                        return 'Mencari karyawan...';
                    }
                }
            });
        });
    });
})();
