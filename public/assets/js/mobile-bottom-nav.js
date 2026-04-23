document.addEventListener('DOMContentLoaded', function() {
    const mobileNav = document.querySelector('.mobile-bottom-nav');

    if (!mobileNav) {
        return;
    }

    const menuGroups = Array.from(mobileNav.querySelectorAll('.mobile-bottom-nav__group'));
    const toggles = Array.from(mobileNav.querySelectorAll('[data-mobile-menu-toggle]'));
    const logoutButton = mobileNav.querySelector('[data-mobile-logout]');
    const logoutForm = document.getElementById('mobile-bottom-nav-logout-form');

    const closeAllMenus = function() {
        menuGroups.forEach(function(group) {
            group.classList.remove('is-open');

            const popup = group.querySelector('.mobile-bottom-nav__popup');
            const toggle = group.querySelector('[data-mobile-menu-toggle]');

            if (popup) {
                popup.classList.remove('is-open');
            }

            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    };

    toggles.forEach(function(toggle) {
        toggle.addEventListener('click', function(event) {
            event.preventDefault();

            const group = toggle.closest('.mobile-bottom-nav__group');
            const popup = group ? group.querySelector('.mobile-bottom-nav__popup') : null;
            const willOpen = popup && !popup.classList.contains('is-open');

            closeAllMenus();

            if (group && popup && willOpen) {
                group.classList.add('is-open');
                popup.classList.add('is-open');
                toggle.setAttribute('aria-expanded', 'true');
            }
        });
    });

    document.addEventListener('click', function(event) {
        if (!mobileNav.contains(event.target)) {
            closeAllMenus();
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeAllMenus();
        }
    });

    if (logoutButton && logoutForm) {
        logoutButton.addEventListener('click', function() {
            logoutForm.submit();
        });
    }
});
