(function () {
    'use strict';

    var toggle = document.querySelector('.site-nav__toggle');
    var navigation = document.getElementById('site-navigation');

    if (toggle && navigation) {
        toggle.addEventListener('click', function () {
            var isOpen = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!isOpen));
            navigation.classList.toggle('is-open', !isOpen);
        });

        navigation.addEventListener('click', function (event) {
            if (event.target.closest('a') && window.innerWidth < 900) {
                toggle.setAttribute('aria-expanded', 'false');
                navigation.classList.remove('is-open');
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                var mobileMenuWasOpen = navigation.classList.contains('is-open');
                var openSubmenu = navigation.querySelector('.site-nav__group[open]');

                if (openSubmenu) {
                    openSubmenu.removeAttribute('open');
                    openSubmenu.querySelector('summary').focus();
                }

                if (mobileMenuWasOpen) {
                    toggle.setAttribute('aria-expanded', 'false');
                    navigation.classList.remove('is-open');
                    toggle.focus();
                }
            }
        });
    }

    document.addEventListener('click', function (event) {
        document.querySelectorAll('.site-nav__group[open]').forEach(function (menu) {
            if (!menu.contains(event.target)) {
                menu.removeAttribute('open');
            }
        });
    });
}());
