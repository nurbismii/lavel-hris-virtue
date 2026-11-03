(function () {
    'use strict';
    var ios = /iPad|iPhone|iPod/i.test(navigator.userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    var android = /Android/i.test(navigator.userAgent);
    var mobile = ios || android || /Mobile|IEMobile|Opera Mini/i.test(navigator.userAgent);
    var status = document.getElementById('downloadStatus');
    if (mobile) document.getElementById('desktopLogin').hidden = true;
    if (ios && document.body.dataset.iosRequired !== '1') document.getElementById('openAppButton').hidden = true;
    if (ios || android) {
        document.querySelectorAll('[data-platform]').forEach(function (section) {
            section.hidden = section.dataset.platform !== (ios ? 'ios' : 'android');
        });
    }
    document.getElementById('openAppButton').addEventListener('click', function () {
        status.textContent = status.dataset.opening;
    });
    document.querySelectorAll('[data-download]').forEach(function (link) {
        link.addEventListener('click', function () { status.textContent = status.dataset.downloading; });
    });
})();
