(function(){
  function openModal(selector){
    var modal=document.querySelector(selector);
    if(!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden','false');
    document.documentElement.classList.add('ttos-modal-open');
    var first=modal.querySelector('input,select,textarea,button');
    if(first) first.focus();
  }
  function closeModal(modal){
    if(!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden','true');
    if(!document.querySelector('.ttos-modal.is-open')) document.documentElement.classList.remove('ttos-modal-open');
  }
  document.addEventListener('click',function(e){
    var opener=e.target.closest('.ttos-open-config');
    if(opener){ e.preventDefault(); openModal(opener.getAttribute('data-modal')); return; }
    if(e.target.matches('[data-close-modal]')){ e.preventDefault(); closeModal(e.target.closest('.ttos-modal')); }
  });
  document.addEventListener('keydown',function(e){
    if(e.key==='Escape') document.querySelectorAll('.ttos-modal.is-open').forEach(closeModal);
  });
})();

/* v0.4.0 search, allergen filters and modal price preview */
(function(){
  function money(value){
    try { return new Intl.NumberFormat('en-GB',{style:'currency',currency:'GBP'}).format(value || 0); }
    catch(e){ return '£' + (value || 0).toFixed(2); }
  }
  function applyMenuFilters(){
    var q=(document.querySelector('.ttos-menu-search-input')||{}).value || '';
    q=q.toLowerCase().trim();
    var blocked=[].slice.call(document.querySelectorAll('.ttos-allergen-toggle:checked')).map(function(i){return i.value;});
    document.querySelectorAll('.ttos-product').forEach(function(card){
      var title=card.getAttribute('data-title') || '';
      var allergens=(card.getAttribute('data-allergens') || '').split(',').filter(Boolean);
      var matchesText=!q || title.indexOf(q)!==-1;
      var hasBlocked=blocked.some(function(slug){ return allergens.indexOf(slug)!==-1; });
      card.style.display=(matchesText && !hasBlocked) ? '' : 'none';
    });
  }
  document.addEventListener('input', function(e){ if(e.target.matches('.ttos-menu-search-input')) applyMenuFilters(); });
  document.addEventListener('change', function(e){ if(e.target.matches('.ttos-allergen-toggle')) applyMenuFilters(); if(e.target.closest('.ttos-config-form')) updateFormPrice(e.target.closest('.ttos-config-form')); });
  function updateFormPrice(form){
    var base=parseFloat(form.getAttribute('data-base-price') || '0') || 0;
    var extras=0;
    form.querySelectorAll('.ttos-config-option input:checked').forEach(function(input){
      extras += parseFloat(input.getAttribute('data-price') || '0') || 0;
    });
    var out=form.querySelector('.ttos-config-total strong');
    if(out) out.textContent=money(base + extras);
  }
  document.querySelectorAll('.ttos-config-form').forEach(updateFormPrice);
})();

/* v1.3.0 menu: unified search/diet/allergen filters + sticky category nav scroll-spy */
(function(){
  var wrap = document.querySelector('.ttos-menu-wrap');
  if (!wrap) return;

  function applyFilters(){
    var q = (wrap.querySelector('.ttos-filter-search') || {value:''}).value.toLowerCase().trim();
    var blockedAllergens = [].slice.call(wrap.querySelectorAll('.ttos-filter-allergen:checked')).map(function(i){ return i.value; });
    var activeDiets = [].slice.call(wrap.querySelectorAll('.ttos-filter-diet[aria-pressed="true"]')).map(function(b){ return b.getAttribute('data-diet'); });

    wrap.querySelectorAll('.ttos-product').forEach(function(card){
      var title = card.getAttribute('data-title') || '';
      var allergens = (card.getAttribute('data-allergens') || '').split(',').filter(Boolean);
      var diets = (card.getAttribute('data-diets') || '').split(',').filter(Boolean);
      var matchesText = !q || title.indexOf(q) !== -1;
      var hasBlocked = blockedAllergens.some(function(slug){ return allergens.indexOf(slug) !== -1; });
      var matchesDiet = !activeDiets.length || activeDiets.every(function(slug){ return diets.indexOf(slug) !== -1; });
      card.style.display = (matchesText && !hasBlocked && matchesDiet) ? '' : 'none';
    });

    /* hide sections (and their nav links) with no visible products */
    wrap.querySelectorAll('.ttos-menu-section').forEach(function(section){
      var visible = [].slice.call(section.querySelectorAll('.ttos-product')).some(function(c){ return c.style.display !== 'none'; });
      var hasNote = !!section.querySelector('.ttos-admin-note');
      section.style.display = (visible || hasNote) ? '' : 'none';
      var link = wrap.querySelector('.ttos-cat-nav a[href="#' + section.id + '"]');
      if (link) link.style.display = (visible || hasNote) ? '' : 'none';
    });
  }

  wrap.addEventListener('input', function(e){
    if (e.target.matches('.ttos-filter-search')) applyFilters();
  });
  wrap.addEventListener('change', function(e){
    if (e.target.matches('.ttos-filter-allergen')) applyFilters();
  });
  wrap.addEventListener('click', function(e){
    var chip = e.target.closest('.ttos-filter-diet');
    if (!chip) return;
    chip.setAttribute('aria-pressed', chip.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
    applyFilters();
  });

  /* scroll-spy for the sticky category nav */
  var nav = wrap.querySelector('.ttos-cat-nav');
  if (nav && 'IntersectionObserver' in window) {
    var links = {};
    nav.querySelectorAll('a[href^="#"]').forEach(function(a){ links[a.getAttribute('href').slice(1)] = a; });
    var observer = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        var link = links[entry.target.id];
        if (!link) return;
        if (entry.isIntersecting) {
          nav.querySelectorAll('a.is-active').forEach(function(a){ a.classList.remove('is-active'); });
          link.classList.add('is-active');
        }
      });
    }, { rootMargin: '-20% 0px -70% 0px' });
    wrap.querySelectorAll('.ttos-menu-section').forEach(function(section){ observer.observe(section); });
  }
})();

/* v1.3.0 banner + popup behaviour: dismissal memory, schedule handled server-side */
(function(){
  function storageKey(prefix, hash){ return prefix + '-' + hash; }

  /* Banner */
  var banner = document.querySelector('[data-ttos-banner]');
  if (banner) {
    var bHash = banner.getAttribute('data-ttos-banner');
    var remember = banner.getAttribute('data-remember') === '1';
    var store = remember ? window.localStorage : window.sessionStorage;
    var dismissed = false;
    try { dismissed = store.getItem(storageKey('ttos-banner', bHash)) === '1'; } catch (e) {}
    if (!dismissed) banner.hidden = false;
    banner.addEventListener('click', function(e){
      if (!e.target.closest('[data-ttos-banner-close]')) return;
      banner.hidden = true;
      try { store.setItem(storageKey('ttos-banner', bHash), '1'); } catch (err) {}
    });
  }

  /* Popup */
  var popup = document.querySelector('[data-ttos-popup]');
  if (!popup) return;
  var pHash = popup.getAttribute('data-ttos-popup');
  var frequency = popup.getAttribute('data-frequency') || 'session';
  var delay = parseInt(popup.getAttribute('data-delay') || '3', 10) * 1000;
  var key = storageKey('ttos-popup', pHash);
  var lastFocused = null;

  function suppressed(){
    try {
      if (frequency === 'session') return window.sessionStorage.getItem(key) === '1';
      var until = parseInt(window.localStorage.getItem(key) || '0', 10);
      return until > Date.now();
    } catch (e) { return false; }
  }
  function markSeen(){
    try {
      if (frequency === 'session') { window.sessionStorage.setItem(key, '1'); return; }
      var ms = frequency === 'week' ? 7 * 86400000 : 86400000;
      window.localStorage.setItem(key, String(Date.now() + ms));
    } catch (e) {}
  }
  function focusables(){
    return popup.querySelectorAll('a[href], button:not([disabled]), input, [tabindex]:not([tabindex="-1"])');
  }
  function openPopup(){
    lastFocused = document.activeElement;
    popup.hidden = false;
    var close = popup.querySelector('.ttos-popup-close') || focusables()[0];
    if (close) close.focus();
  }
  function closePopup(){
    popup.hidden = true;
    markSeen();
    if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
  }

  if (suppressed()) return;
  window.setTimeout(openPopup, isNaN(delay) ? 3000 : delay);

  popup.addEventListener('click', function(e){
    if (e.target.closest('[data-ttos-popup-close]')) { e.preventDefault(); closePopup(); }
  });
  document.addEventListener('keydown', function(e){
    if (popup.hidden) return;
    if (e.key === 'Escape') { closePopup(); return; }
    if (e.key === 'Tab') {
      var items = focusables();
      if (!items.length) return;
      var first = items[0], last = items[items.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });
})();
