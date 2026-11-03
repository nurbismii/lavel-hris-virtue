(function () {
    'use strict';
    var agent = navigator.userAgent;
    if (/(?:^|\s)VPEOPLE_APP(?:\/[\d.]+)?(?:\s|$)/i.test(agent)) return;
    // iPad desktop browsing omits the mobile user-agent; this is a UX fallback, not device attestation.
    var ios = /iPhone|iPad|iPod/i.test(agent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    if (ios && document.currentScript.dataset.iosRequired !== '1') return;
    var mobile = /Android|iPhone|iPad|iPod|IEMobile|Windows Phone|BlackBerry|BB10|Opera Mini|Mobile/i.test(agent)
        || ios
        || (navigator.userAgentData && navigator.userAgentData.mobile);
    if (mobile) window.location.replace(document.currentScript.dataset.downloadUrl);
})();
