/* Takeaway Theme v0.3.3 — two-row sticky header, method switcher, basket preview. */
(function () {
    'use strict';

    /* ── Direction-aware compact header ────────────────────────────── */
    var header = document.getElementById('site-header');
    if (header) {
        var lastScrollY = window.scrollY;
        var rafPending  = false;

        function updateHeaderOnScroll() {
            var y         = window.scrollY;
            var drawerEl  = document.getElementById('tt-mobile-drawer');
            var drawerOpen = drawerEl && !drawerEl.hidden;
            var goingDown = y > lastScrollY;

            if (goingDown && y > 120 && !drawerOpen) {
                header.classList.add('header-compact');
            } else if (!goingDown || y < 80) {
                header.classList.remove('header-compact');
            }
            lastScrollY = Math.max(y, 0);
            rafPending  = false;
        }

        window.addEventListener('scroll', function () {
            if (!rafPending) {
                rafPending = true;
                requestAnimationFrame(updateHeaderOnScroll);
            }
        }, { passive: true });
    }

    /* ── Method switcher (homepage hero start-card) ─────────────────── */
    var methodSwitch = document.querySelector('.tt-method-switch');
    if (methodSwitch) {
        var methodBtns = Array.prototype.slice.call(methodSwitch.querySelectorAll('.tt-method[data-method]'));
        var panel      = document.querySelector('.tt-start-panel');
        var titleEl    = panel && panel.querySelector('.tt-method-title');
        var textEl     = panel && panel.querySelector('.tt-method-text');
        var ctaEl      = panel && panel.querySelector('.tt-method-cta');

        function activateMethod(btn) {
            methodBtns.forEach(function (b) {
                b.classList.toggle('active', b === btn);
                b.setAttribute('aria-selected', b === btn ? 'true' : 'false');
            });
            if (!panel) return;
            if (titleEl) titleEl.textContent = btn.dataset.title || '';
            if (textEl)  textEl.textContent  = btn.dataset.text  || '';
            if (ctaEl) {
                ctaEl.href        = btn.dataset.ctaHref || '#';
                ctaEl.textContent = btn.dataset.ctaText || '';
            }
        }

        methodBtns.forEach(function (btn) {
            btn.addEventListener('click', function () { activateMethod(btn); });
        });
    }

    /* ── Basket preview toggle ──────────────────────────────────────── */
    var basketBtn   = document.getElementById('tt-basket-btn');
    var cartPreview = document.getElementById('tt-cart-preview');
    var basketWrap  = document.getElementById('tt-basket-wrap');

    if (basketBtn && cartPreview) {
        function openCart() {
            cartPreview.classList.add('open');
            basketBtn.setAttribute('aria-expanded', 'true');
        }
        function closeCart() {
            cartPreview.classList.remove('open');
            basketBtn.setAttribute('aria-expanded', 'false');
        }

        basketBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = cartPreview.classList.contains('open');
            if (isOpen) { closeCart(); } else { openCart(); }
        });

        document.addEventListener('click', function (e) {
            if (cartPreview.classList.contains('open') && basketWrap && !basketWrap.contains(e.target)) {
                closeCart();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && cartPreview.classList.contains('open')) closeCart();
        });
    }

    /* ── Account dropdown (logged-in state) ────────────────────────── */
    var acctBtn      = document.getElementById('tt-acct-btn');
    var acctDropdown = document.getElementById('tt-acct-dropdown');
    var acctWrap     = document.getElementById('tt-acct-wrap');

    if (acctBtn && acctDropdown) {
        function openAcct() {
            acctDropdown.classList.add('open');
            acctBtn.setAttribute('aria-expanded', 'true');
        }
        function closeAcct() {
            acctDropdown.classList.remove('open');
            acctBtn.setAttribute('aria-expanded', 'false');
        }

        acctBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (acctDropdown.classList.contains('open')) { closeAcct(); } else { openAcct(); }
        });

        document.addEventListener('click', function (e) {
            if (acctDropdown.classList.contains('open') && acctWrap && !acctWrap.contains(e.target)) {
                closeAcct();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && acctDropdown.classList.contains('open')) closeAcct();
        });
    }

    /* ── Fulfilment toggle (menu pagehead) ──────────────────────────── */
    document.querySelectorAll('.tt-fulfilment-toggle').forEach(function (tog) {
        tog.setAttribute('role', 'radiogroup');
        var pills = tog.querySelectorAll('.tt-fulfilment-pill[data-fulfilment]');
        var scope = tog.parentElement;

        pills.forEach(function (p) {
            p.setAttribute('role', 'radio');
            p.setAttribute('aria-checked', p.getAttribute('aria-pressed') === 'true' ? 'true' : 'false');
            p.removeAttribute('aria-pressed');
        });

        function activateMode(mode) {
            pills.forEach(function (p) {
                p.setAttribute('aria-checked', p.dataset.fulfilment === mode ? 'true' : 'false');
            });
            scope.querySelectorAll('.tt-hero-zone[data-zone], .tt-pagehead-zone[data-zone]').forEach(function (z) {
                z.hidden = z.dataset.zone !== mode;
            });
        }

        pills.forEach(function (pill) {
            pill.addEventListener('click', function () { activateMode(pill.dataset.fulfilment); });
        });
    });

    /* ── Mobile drawer ─────────────────────────────────────────────── */
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
        if (header) header.classList.remove('header-compact');
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
        if (event.key === 'Escape') { closeDrawer(); return; }
        if (event.key === 'Tab') {
            var items = focusables();
            if (!items.length) return;
            var first = items[0];
            var last  = items[items.length - 1];
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

/* ── WooCommerce checkout/cart form: aria-invalid + aria-describedby ─ */
(function () {
    var errCounter = 0;
    function linkErrors(form) {
        form.querySelectorAll('.form-row').forEach(function (row) {
            var field = row.querySelector('input, select, textarea');
            if (!field) return;
            if (row.classList.contains('woocommerce-invalid')) {
                field.setAttribute('aria-invalid', 'true');
                var msg = row.querySelector('.woocommerce-error, [role="alert"]');
                if (msg) {
                    if (!msg.id) { msg.id = 'wce-' + (++errCounter); }
                    field.setAttribute('aria-describedby', msg.id);
                }
            } else {
                field.removeAttribute('aria-invalid');
            }
        });
    }
    var form = document.querySelector('.woocommerce-checkout');
    if (form && window.MutationObserver) {
        new MutationObserver(function () { linkErrors(form); })
            .observe(form, { subtree: true, attributeFilter: ['class'], childList: true });
    }
})();
