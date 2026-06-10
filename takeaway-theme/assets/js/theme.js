/* Takeaway Theme v0.3.0 — sticky header + accessible mobile drawer. Vanilla JS only. */
(function () {
    'use strict';

    /* Sticky header shadow */
    var header = document.getElementById('site-header');
    if (header) {
        var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 16); };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    /* Mobile drawer */
    var drawer = document.getElementById('tt-mobile-drawer');
    var toggle = document.querySelector('.tt-drawer-toggle');
    if (!drawer || !toggle) return;

    var lastFocused = null;

    function focusables() {
        return drawer.querySelectorAll('a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])');
    }

    function openDrawer() {
        lastFocused = document.activeElement;
        drawer.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        document.documentElement.classList.add('tt-drawer-open');
        var first = drawer.querySelector('.tt-drawer-close');
        if (first) first.focus();
    }

    function closeDrawer() {
        drawer.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        document.documentElement.classList.remove('tt-drawer-open');
        if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
    }

    toggle.addEventListener('click', function () {
        if (drawer.hidden) { openDrawer(); } else { closeDrawer(); }
    });

    drawer.addEventListener('click', function (event) {
        if (event.target.closest('[data-drawer-close]')) {
            event.preventDefault();
            closeDrawer();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (drawer.hidden) return;
        if (event.key === 'Escape') {
            closeDrawer();
            return;
        }
        if (event.key === 'Tab') {
            /* simple focus trap */
            var items = focusables();
            if (!items.length) return;
            var first = items[0];
            var last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });
})();
