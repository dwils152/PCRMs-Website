(function () {
    'use strict';

    // Phone menu.
    var toggle = document.querySelector('.site-nav__toggle');
    var navigation = document.getElementById('site-navigation');

    function setMenu(open) {
        toggle.setAttribute('aria-expanded', String(open));
        navigation.classList.toggle('is-open', open);
    }

    if (toggle && navigation) {
        toggle.addEventListener('click', function () {
            setMenu(toggle.getAttribute('aria-expanded') !== 'true');
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && navigation.classList.contains('is-open')) {
                setMenu(false);
                toggle.focus();
            }
        });

        window.matchMedia('(min-width: 761px)').addEventListener('change', function (query) {
            if (query.matches) {
                setMenu(false);
            }
        });
    }

    // Related work: show the first few papers and reveal the rest on request.
    var list = document.getElementById('related-list');
    var more = document.querySelector('.related-toggle');

    if (list && more) {
        var items = list.querySelectorAll('li');
        var visible = parseInt(list.getAttribute('data-visible'), 10) || 3;
        var hiddenCount = items.length - visible;
        var label = more.querySelector('span');

        function setExpanded(expanded) {
            for (var i = visible; i < items.length; i += 1) {
                items[i].hidden = !expanded;
            }
            more.setAttribute('aria-expanded', String(expanded));
            label.textContent = expanded
                ? 'Show fewer'
                : 'Show ' + hiddenCount + ' more publication' + (hiddenCount === 1 ? '' : 's');
        }

        if (hiddenCount > 0) {
            setExpanded(false);
            more.hidden = false;
            more.addEventListener('click', function () {
                var expanded = more.getAttribute('aria-expanded') !== 'true';
                setExpanded(expanded);
                if (expanded) {
                    items[visible].querySelector('a').focus();
                }
            });
        }
    }
}());
