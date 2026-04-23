(function() {
    if (typeof WebFont === 'undefined') {
        return;
    }

    const fontsUrl = document.querySelector('meta[name="app-fonts-url"]')?.content;

    WebFont.load({
        google: {
            families: ['Public Sans:300,400,500,600,700']
        },
        custom: {
            families: [
                'Font Awesome 5 Solid',
                'Font Awesome 5 Regular',
                'Font Awesome 5 Brands',
                'simple-line-icons',
            ],
            urls: fontsUrl ? [fontsUrl] : [],
        },
        active: function() {
            sessionStorage.fonts = true;
        },
    });
})();
