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
            document.documentElement.classList.add('tt-cart-open');
        }
        function closeCart() {
            cartPreview.classList.remove('open');
            basketBtn.setAttribute('aria-expanded', 'false');
            document.documentElement.classList.remove('tt-cart-open');
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

    /* ── Account dropdown — main nav pill ──────────────────────────── */
    var acctMainBtn  = document.getElementById('tt-acct-main-btn');
    var acctMainDrop = document.getElementById('tt-acct-main-drop');
    var acctMain     = document.getElementById('tt-acct-main');

    if (acctMainBtn && acctMainDrop) {
        function openAcct() {
            acctMainDrop.classList.add('open');
            acctMainBtn.setAttribute('aria-expanded', 'true');
            // Focus first link in the dropdown.
            var first = acctMainDrop.querySelector('a, button');
            if (first) first.focus();
        }
        function closeAcct() {
            acctMainDrop.classList.remove('open');
            acctMainBtn.setAttribute('aria-expanded', 'false');
        }

        acctMainBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (acctMainDrop.classList.contains('open')) { closeAcct(); } else { openAcct(); }
        });

        document.addEventListener('click', function (e) {
            if (acctMainDrop.classList.contains('open') && acctMain && !acctMain.contains(e.target)) {
                closeAcct();
            }
        });

        // Trap focus inside dropdown while open; Escape closes.
        acctMainDrop.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { closeAcct(); acctMainBtn.focus(); return; }
            if (e.key !== 'Tab') return;
            var items = Array.prototype.slice.call(acctMainDrop.querySelectorAll('a, button'));
            if (!items.length) return;
            var first = items[0];
            var last  = items[items.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        });
    }

    /* ── Contact mega panel ─────────────────────────────────────────── */
    var contactPanel = document.getElementById('tt-contact-panel');
    var cpanelClose  = document.getElementById('tt-cpanel-close');
    // The Contact nav trigger is tagged with .tt-contact-trigger (server-side filter).
    var contactTrigger = document.querySelector('.tt-main-nav .tt-contact-trigger > a');

    if (contactPanel && contactTrigger) {
        function openContactPanel() {
            contactPanel.classList.add('open');
            contactPanel.setAttribute('aria-hidden', 'false');
            contactTrigger.setAttribute('aria-expanded', 'true');
            contactTrigger.parentElement.classList.add('tt-panel-open');
            // Move focus to the first input in the form.
            var firstInput = contactPanel.querySelector('input, textarea, button');
            if (firstInput) firstInput.focus();
        }
        function closeContactPanel() {
            contactPanel.classList.remove('open');
            contactPanel.setAttribute('aria-hidden', 'true');
            contactTrigger.setAttribute('aria-expanded', 'false');
            contactTrigger.parentElement.classList.remove('tt-panel-open');
            contactTrigger.focus();
        }

        // Set initial ARIA.
        contactTrigger.setAttribute('aria-expanded', 'false');
        contactTrigger.setAttribute('aria-controls', 'tt-contact-panel');
        contactTrigger.setAttribute('role', 'button');

        contactTrigger.addEventListener('click', function (e) {
            e.preventDefault();
            if (contactPanel.classList.contains('open')) { closeContactPanel(); } else { openContactPanel(); }
        });

        if (cpanelClose) {
            cpanelClose.addEventListener('click', closeContactPanel);
        }

        // Click outside panel (but not on trigger) closes it.
        document.addEventListener('click', function (e) {
            if (
                contactPanel.classList.contains('open') &&
                !contactPanel.contains(e.target) &&
                !contactTrigger.contains(e.target)
            ) {
                closeContactPanel();
            }
        });

        // Escape key closes the panel.
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && contactPanel.classList.contains('open')) {
                closeContactPanel();
            }
        });
    }

    /* ── Contact form AJAX submit ───────────────────────────────────── */
    var contactForm = document.getElementById('tt-contact-form');
    if (contactForm) {
        var cfMsg = document.getElementById('tt-cf-msg');

        contactForm.addEventListener('submit', function (e) {
            e.preventDefault();

            // HTML5 validation.
            if (!contactForm.checkValidity()) {
                contactForm.reportValidity();
                return;
            }

            var ajaxUrl = contactForm.dataset.ajax;
            var nonce   = contactForm.dataset.nonce;
            var data    = new FormData(contactForm);
            data.append('action', 'tt_contact');
            data.append('nonce', nonce);

            contactForm.classList.add('is-loading');
            if (cfMsg) { cfMsg.className = 'tt-cf-msg'; cfMsg.hidden = true; cfMsg.textContent = ''; }

            fetch(ajaxUrl, { method: 'POST', body: data })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    contactForm.classList.remove('is-loading');
                    if (!cfMsg) return;
                    cfMsg.textContent = res.data && res.data.message ? res.data.message : (res.success ? 'Sent!' : 'Error.');
                    cfMsg.className   = 'tt-cf-msg ' + (res.success ? 'is-success' : 'is-error');
                    cfMsg.hidden      = false;
                    if (res.success) contactForm.reset();
                })
                .catch(function () {
                    contactForm.classList.remove('is-loading');
                    if (cfMsg) {
                        cfMsg.textContent = 'Something went wrong. Please try again.';
                        cfMsg.className   = 'tt-cf-msg is-error';
                        cfMsg.hidden      = false;
                    }
                });
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
        var openCartPreview = document.getElementById('tt-cart-preview');
        var openBasketBtn = document.getElementById('tt-basket-btn');
        if (openCartPreview) openCartPreview.classList.remove('open');
        if (openBasketBtn) openBasketBtn.setAttribute('aria-expanded', 'false');
        document.documentElement.classList.remove('tt-cart-open');
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
