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
