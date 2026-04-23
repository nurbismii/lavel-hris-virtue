(function() {
    const openButton = document.getElementById('openAppButton');
    const downloadArea = document.getElementById('downloadArea');

    function openApp() {
        window.location = 'vpeople://dashboard';

        window.setTimeout(function() {
            if (downloadArea) {
                downloadArea.style.display = 'block';
            }
        }, 1500);
    }

    if (openButton) {
        openButton.addEventListener('click', function(event) {
            event.preventDefault();
            openApp();
        });
    }

    window.addEventListener('load', function() {
        window.setTimeout(openApp, 3000);
    });
})();
