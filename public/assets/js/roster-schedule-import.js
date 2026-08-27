(function ($) {
    'use strict';
    var page = $('#roster-import-page');
    var form = $('#roster-import-form');
    var terminal = page.data('terminal') === 1 || page.data('terminal') === '1';
    var statusUrl = page.data('status-url');
    var started = Date.now();

    function notify(icon, message) {
        if (window.Swal) { Swal.fire({icon: icon, title: icon === 'error' ? 'Gagal' : 'Informasi', text: message}); }
    }
    function errorMessage(xhr) {
        if (xhr.status === 401 || xhr.status === 419) { return 'Sesi login berakhir. Silakan login ulang.'; }
        if (xhr.status === 403) { return 'Anda tidak memiliki akses untuk tindakan ini.'; }
        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) { var key = Object.keys(xhr.responseJSON.errors)[0]; return xhr.responseJSON.errors[key][0]; }
        if (xhr.status === 0) { return 'Koneksi bermasalah. Silakan cek jaringan Anda.'; }
        return (xhr.responseJSON && xhr.responseJSON.message) || 'Request gagal diproses.';
    }
    if (form.length) {
        form.on('submit', function () {
            var button = form.find('button[type="submit"]');
            if (button.prop('disabled')) { return false; }
            button.data('label', button.html()).prop('disabled', true).html('Mengunggah...');
        });
    }
    function poll() {
        if (!statusUrl || terminal || Date.now() - started > 720000) { return; }
        $.getJSON(statusUrl).done(function (response) {
            var data = response.data || {};
            $('[data-import-status]').text(data.status || '-');
            $.each(data.summary || {}, function (key, value) { $('[data-summary="' + key + '"]').text(value); });
            terminal = !!data.terminal;
            if (!terminal) { window.setTimeout(poll, 5000); }
        }).fail(function (xhr) { notify('error', errorMessage(xhr)); });
    }
    poll();
}(jQuery));
