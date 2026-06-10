jQuery(function($){
  function cleanMessage(message){
    const div = document.createElement('div');
    div.innerHTML = String(message || 'Install failed.');
    return (div.textContent || div.innerText || 'Install failed.').replace(/\s+/g, ' ').trim();
  }

  /* Media picker */
  $(document).on('click', '.ttos-pick-media', function(e){
    e.preventDefault();
    const targetSelector = $(this).data('target');
    const $target = $(targetSelector);
    const $field = $(this).closest('.ttos-media-field');
    const frame = wp.media({title:'Choose image', multiple:false, library:{type:'image'}});
    frame.on('select', function(){
      const attachment = frame.state().get('selection').first().toJSON();
      $target.val(attachment.id).trigger('change');
      const previewUrl = (attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url);
      $field.find('.ttos-media-preview').html('<img src="' + previewUrl + '" alt="">');
    });
    frame.open();
  });

  $(document).on('click', '.ttos-clear-media', function(e){
    e.preventDefault();
    const $field = $(this).closest('.ttos-media-field');
    $($(this).data('target')).val('').trigger('change');
    $field.find('.ttos-media-preview').html('<em>No image selected</em>');
  });

  /* Single-page launch wizard */
  function showWizardStep(step){
    if (!step) return;
    const $panel = $('.ttos-wizard-panel[data-panel="' + step + '"]');
    if (!$panel.length) return;
    $('.ttos-wizard-panel').removeClass('is-active');
    $('.ttos-wizard-step').removeClass('is-active');
    $panel.addClass('is-active');
    $('.ttos-wizard-step[data-step="' + step + '"]').addClass('is-active');
    if (window.history && window.history.replaceState) {
      window.history.replaceState(null, '', window.location.pathname + window.location.search + '#ttos-step-' + step);
    }
  }

  $('.ttos-wizard-step').on('click', function(){ showWizardStep($(this).data('step')); });

  $('.ttos-wizard-next').on('click', function(e){
    if ($(this).hasClass('is-disabled')) {
      e.preventDefault();
      const $installer = $(this).closest('.ttos-installer');
      $installer.find('.ttos-progress-label').text('Install the required foundation first.');
      return;
    }
    const next = $(this).data('next');
    if (next) {
      e.preventDefault();
      showWizardStep(next);
      const el = document.getElementById('ttos-step-' + next);
      if (el) el.scrollIntoView({behavior:'smooth', block:'start'});
    }
  });

  if (window.location.hash) {
    const hash = window.location.hash.replace('#ttos-step-', '').replace('#', '');
    const allowed = ['required','business','branding','delivery','menu','pages','payments','golive'];
    if (allowed.indexOf(hash) !== -1) showWizardStep(hash);
  }

  /* Plugin installer */
  function rowIsActive($row){ return $.trim($row.find('.ttos-status').text()).toLowerCase() === 'active'; }

  function dependencyReady(key){
    const plugin = window.TTOSInstaller && TTOSInstaller.plugins && TTOSInstaller.plugins[key] ? TTOSInstaller.plugins[key] : null;
    const $row = $('.ttos-install-row[data-key="' + key + '"]').first();
    if ($row.length && rowIsActive($row)) return true;
    return plugin && plugin.status === 'active';
  }

  function dependenciesReady($row){
    const depends = String($row.data('depends') || '').split(',').filter(Boolean);
    for (let i = 0; i < depends.length; i++) if (!dependencyReady(depends[i])) return false;
    return true;
  }

  function requiredComplete(){
    let complete = true;
    $('.ttos-install-row[data-required="1"]').each(function(){ if (!rowIsActive($(this))) complete = false; });
    return complete;
  }

  function refreshNext(){
    const complete = requiredComplete();
    $('.ttos-next-step').toggleClass('is-disabled', !complete).attr('aria-disabled', complete ? 'false' : 'true');
    $('.ttos-wizard').attr('data-required-complete', complete ? '1' : '0');
    $('.ttos-install-row').each(function(){
      const $row = $(this), $btn = $row.find('.ttos-install-one');
      if (!$btn.length) return;
      const ready = dependenciesReady($row);
      $btn.toggleClass('is-disabled', !ready).prop('disabled', !ready);
      if (!ready) $btn.attr('title', 'Install and activate WooCommerce first.'); else $btn.removeAttr('title');
    });
  }

  function panelParts($scope){ return { progress:$scope.find('.ttos-progress span').first(), label:$scope.find('.ttos-progress-label').first() }; }
  function setProgress($scope, done, total, text){
    const parts = panelParts($scope); const pct = total ? Math.round((done / total) * 100) : 0;
    parts.progress.css('width', pct + '%'); parts.label.text(text || (pct + '%'));
  }

  function installRow($row, $scope){
    const slug = $row.data('slug'), key = $row.data('key'), $status = $row.find('.ttos-status'), $button = $row.find('.ttos-install-one');
    if (rowIsActive($row)) return $.Deferred().resolve({skipped:true}).promise();
    if (!dependenciesReady($row)) {
      const message = 'Install and activate WooCommerce first, then refresh before activating payment gateways.';
      $status.removeClass('ttos-good ttos-warn').addClass('ttos-bad').text('Waiting');
      $row.attr('title', message).addClass('is-failed');
      return $.Deferred().reject(message).promise();
    }
    $row.addClass('is-working').removeClass('is-failed');
    $status.removeClass('ttos-good ttos-bad').addClass('ttos-warn').text('Installing…');
    $button.prop('disabled', true).text('Working…');
    return $.ajax({
      url: TTOSInstaller.ajaxUrl, method: 'POST', dataType: 'json',
      data: { action:'ttos_install_plugin', nonce:TTOSInstaller.nonce, key:key, slug:slug }
    }).then(function(res){
      if (!res || !res.success) {
        const message = cleanMessage(res && res.data && res.data.message ? res.data.message : 'Install failed.');
        $status.removeClass('ttos-good ttos-warn').addClass('ttos-bad').text('Failed');
        $row.attr('title', message).addClass('is-failed'); $button.prop('disabled', false).text('Retry');
        return $.Deferred().reject(message).promise();
      }
      $status.removeClass('ttos-bad ttos-warn').addClass('ttos-good').text('Active');
      $button.remove(); $row.removeClass('is-working is-failed').addClass('is-done');
      if (TTOSInstaller.plugins && TTOSInstaller.plugins[key]) TTOSInstaller.plugins[key].status = 'active';
      refreshNext(); return res;
    }, function(xhr){
      const message = cleanMessage(xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'Install request failed.');
      $status.removeClass('ttos-good ttos-warn').addClass('ttos-bad').text('Failed');
      $row.attr('title', message).addClass('is-failed'); $button.prop('disabled', false).text('Retry');
      return $.Deferred().reject(message).promise();
    });
  }

  function runQueue($scope, $rows, options){
    options = options || {}; const total = $rows.length; let done = 0; let chain = $.Deferred().resolve().promise();
    if (!total) { setProgress($scope, 1, 1, 'Everything required is already active.'); refreshNext(); return; }
    $scope.find('button').prop('disabled', true); setProgress($scope, 0, total, 'Starting install…');
    $rows.each(function(){
      const $row = $(this);
      chain = chain.then(function(){
        setProgress($scope, done, total, 'Installing ' + $.trim($row.find('strong').first().text()) + '…');
        return installRow($row, $scope).always(function(){ done++; setProgress($scope, done, total, done + ' of ' + total + ' complete'); });
      });
    });
    chain.then(function(){
      setProgress($scope, total, total, 'Done. Required plugins are active.'); refreshNext();
      if (options.reloadAfterComplete && TTOSInstaller.reloadUrl) {
        panelParts($scope).label.text('Done. Refreshing setup screen so WooCommerce loads properly…');
        window.setTimeout(function(){ window.location.href = TTOSInstaller.reloadUrl; }, 700);
      }
    }).fail(function(message){ panelParts($scope).label.text('Stopped: ' + cleanMessage(message)); refreshNext(); })
      .always(function(){ $scope.find('button').prop('disabled', false); $scope.find('.ttos-install-row.is-done .ttos-install-one').remove(); refreshNext(); });
  }

  if (typeof TTOSInstaller !== 'undefined') {
    $(document).on('click', '#ttos-install-required', function(){
      const $scope = $(this).closest('.ttos-installer');
      const $rows = $scope.find('.ttos-install-row[data-required="1"]').filter(function(){ return !rowIsActive($(this)); });
      runQueue($scope, $rows, {reloadAfterComplete:true});
    });
    $(document).on('click', '.ttos-install-one', function(){
      const $row = $(this).closest('.ttos-install-row'), $scope = $(this).closest('.ttos-installer');
      setProgress($scope, 0, 1, 'Starting install…');
      installRow($row, $scope).then(function(){
        setProgress($scope, 1, 1, 'Done.');
        if ($row.data('key') === 'woocommerce' && TTOSInstaller.reloadUrl) {
          panelParts($scope).label.text('WooCommerce active. Refreshing setup screen…');
          window.setTimeout(function(){ window.location.href = TTOSInstaller.reloadUrl; }, 700);
        }
      }).fail(function(message){ panelParts($scope).label.text('Stopped: ' + cleanMessage(message)); });
    });
    refreshNext();
  }

  /* Menu option group builder */
  $('.ttos-option-builder').each(function(){
    const $builder = $(this);
    const $json = $builder.find('.ttos-option-json');
    const $groups = $builder.find('.ttos-option-groups');
    let state = [];
    try { state = JSON.parse($builder.attr('data-initial') || $json.val() || '[]') || []; } catch(e) { state = []; }

    function emptyGroup(){
      return {name:'Choose sauce', type:'single', required:false, min:0, max:1, options:[{label:'Garlic mayo', price:'0', default:false, sold_out:false},{label:'Chilli sauce', price:'0', default:false, sold_out:false}]};
    }
    function presets(){
      return {
        kebab:[
          {name:'Choose size',type:'single',required:true,min:1,max:1,options:[{label:'Regular',price:'0',default:true,sold_out:false},{label:'Large',price:'2.00',default:false,sold_out:false}]},
          {name:'Choose sauce',type:'multiple',required:true,min:1,max:3,options:[{label:'Garlic mayo',price:'0',default:true,sold_out:false},{label:'Chilli sauce',price:'0',default:false,sold_out:false},{label:'Burger sauce',price:'0',default:false,sold_out:false},{label:'No sauce',price:'0',default:false,sold_out:false}]},
          {name:'Salad',type:'multiple',required:false,min:0,max:6,options:[{label:'Lettuce',price:'0',default:true,sold_out:false},{label:'Onion',price:'0',default:true,sold_out:false},{label:'Tomato',price:'0',default:true,sold_out:false},{label:'Cucumber',price:'0',default:true,sold_out:false},{label:'No salad',price:'0',default:false,sold_out:false}]},
          {name:'Extras',type:'multiple',required:false,min:0,max:6,options:[{label:'Extra meat',price:'3.00',default:false,sold_out:false},{label:'Cheese',price:'1.00',default:false,sold_out:false},{label:'Chips inside',price:'1.50',default:false,sold_out:false}]}
        ],
        pizza:[
          {name:'Choose size',type:'single',required:true,min:1,max:1,options:[{label:'9 inch',price:'0',default:true,sold_out:false},{label:'12 inch',price:'3.00',default:false,sold_out:false},{label:'15 inch',price:'5.00',default:false,sold_out:false}]},
          {name:'Crust',type:'single',required:true,min:1,max:1,options:[{label:'Thin crust',price:'0',default:true,sold_out:false},{label:'Deep pan',price:'0',default:false,sold_out:false},{label:'Stuffed crust',price:'2.50',default:false,sold_out:false}]},
          {name:'Toppings',type:'multiple',required:false,min:0,max:8,options:[{label:'Pepperoni',price:'1.20',default:false,sold_out:false},{label:'Mushrooms',price:'0.90',default:false,sold_out:false},{label:'Onions',price:'0.70',default:false,sold_out:false},{label:'Jalapeños',price:'0.90',default:false,sold_out:false},{label:'Extra cheese',price:'1.50',default:false,sold_out:false}]}
        ],
        burger:[
          {name:'Patty',type:'single',required:true,min:1,max:1,options:[{label:'Beef',price:'0',default:true,sold_out:false},{label:'Chicken',price:'0',default:false,sold_out:false},{label:'Veggie',price:'0',default:false,sold_out:false}]},
          {name:'Extras',type:'multiple',required:false,min:0,max:5,options:[{label:'Cheese',price:'1.00',default:false,sold_out:false},{label:'Bacon',price:'1.50',default:false,sold_out:false},{label:'Hash brown',price:'1.20',default:false,sold_out:false},{label:'Extra patty',price:'3.00',default:false,sold_out:false}]},
          {name:'Make it a meal',type:'single',required:false,min:0,max:1,options:[{label:'No thanks',price:'0',default:true,sold_out:false},{label:'Chips + drink',price:'3.50',default:false,sold_out:false},{label:'Large chips + drink',price:'4.50',default:false,sold_out:false}]}
        ]
      };
    }
    function esc(v){ return $('<div/>').text(v == null ? '' : String(v)).html(); }

    function sync(){
      const next = [];
      $groups.find('.ttos-option-group').each(function(){
        const $g = $(this);
        const group = {name:$g.find('.ttos-group-name').val() || '', type:$g.find('.ttos-group-type').val() || 'multiple', required:$g.find('.ttos-group-required').is(':checked'), min:parseInt($g.find('.ttos-group-min').val() || '0', 10), max:parseInt($g.find('.ttos-group-max').val() || '0', 10), options:[]};
        $g.find('.ttos-option-row').each(function(){
          const $o = $(this);
          group.options.push({label:$o.find('.ttos-option-label').val() || '', price:$o.find('.ttos-option-price').val() || '0', default:$o.find('.ttos-option-default').is(':checked'), sold_out:$o.find('.ttos-option-sold').is(':checked')});
        });
        next.push(group);
      });
      state = next;
      $json.val(JSON.stringify(state));
    }

    function render(){
      $groups.empty();
      if (!state.length) {
        $groups.append('<p class="ttos-muted ttos-options-empty">No configuration groups yet. Add one for sauce, size, crust, toppings, salad, drinks or meal upgrades.</p>');
      }
      state.forEach(function(group){
        const options = Array.isArray(group.options) ? group.options : [];
        const $g = $('<div class="ttos-option-group"><div class="ttos-option-group-head"><span class="ttos-drag">⋮⋮</span><strong>Option group</strong><button type="button" class="ttos-mini ttos-remove-group">Remove</button></div><div class="ttos-grid ttos-grid-4"><label>Group name<input class="ttos-group-name" value="' + esc(group.name || '') + '"></label><label>Type<select class="ttos-group-type"><option value="single">Single choice</option><option value="multiple">Multiple choice</option></select></label><label>Min<input type="number" class="ttos-group-min" value="' + esc(group.min || 0) + '"></label><label>Max<input type="number" class="ttos-group-max" value="' + esc(group.max || 1) + '"></label></div><label class="ttos-check"><input type="checkbox" class="ttos-group-required"> Required</label><div class="ttos-option-rows"></div><button type="button" class="ttos-mini ttos-add-option">Add option</button></div>');
        $g.find('.ttos-group-type').val(group.type || 'multiple');
        $g.find('.ttos-group-required').prop('checked', !!group.required);
        options.forEach(function(option){
          const $row = $('<div class="ttos-option-row"><span class="ttos-drag">↕</span><input class="ttos-option-label" placeholder="Extra cheese" value="' + esc(option.label || '') + '"><input class="ttos-option-price" placeholder="+£" value="' + esc(option.price || '0') + '"><label class="ttos-tiny-check"><input type="checkbox" class="ttos-option-default"> Default</label><label class="ttos-tiny-check"><input type="checkbox" class="ttos-option-sold"> Sold out</label><button type="button" class="ttos-mini ttos-remove-option">×</button></div>');
          $row.find('.ttos-option-default').prop('checked', !!option.default);
          $row.find('.ttos-option-sold').prop('checked', !!option.sold_out);
          $g.find('.ttos-option-rows').append($row);
        });
        $groups.append($g);
      });
      $groups.sortable({handle:'.ttos-option-group-head .ttos-drag', update:sync});
      $groups.find('.ttos-option-rows').sortable({handle:'.ttos-drag', update:sync});
      sync();
    }

    $builder.on('click', '.ttos-add-group', function(){ state.push(emptyGroup()); render(); });
    $builder.closest('form').on('click', '.ttos-apply-preset', function(e){
      e.preventDefault();
      const key = $(this).data('preset');
      const all = presets();
      if (all[key] && window.confirm('Replace the current configurator with the ' + key + ' preset?')) {
        state = JSON.parse(JSON.stringify(all[key]));
        render();
      }
    });
    $builder.on('click', '.ttos-remove-group', function(){ $(this).closest('.ttos-option-group').remove(); sync(); });
    $builder.on('click', '.ttos-add-option', function(){
      $(this).siblings('.ttos-option-rows').append('<div class="ttos-option-row"><span class="ttos-drag">↕</span><input class="ttos-option-label" placeholder="Extra cheese"><input class="ttos-option-price" placeholder="+£" value="0"><label class="ttos-tiny-check"><input type="checkbox" class="ttos-option-default"> Default</label><label class="ttos-tiny-check"><input type="checkbox" class="ttos-option-sold"> Sold out</label><button type="button" class="ttos-mini ttos-remove-option">×</button></div>');
      sync();
    });
    $builder.on('click', '.ttos-remove-option', function(){ $(this).closest('.ttos-option-row').remove(); sync(); });
    $builder.on('input change', 'input,select', sync);
    $builder.closest('form').on('submit', sync);
    render();
  });

  /* v0.4.0 visual module switches, menu bulk actions and order cockpit */
  function updateModuleSwitch($input){
    const $label = $input.closest('.ttos-module');
    const enabled = $input.is(':checked');
    $label.toggleClass('is-enabled', enabled).toggleClass('is-disabled', !enabled);
    $label.find('.ttos-module-state').text(enabled ? 'Enabled' : 'Off');
  }
  $('.ttos-module input[type="checkbox"]').each(function(){ updateModuleSwitch($(this)); });
  $(document).on('change', '.ttos-module input[type="checkbox"]', function(){ updateModuleSwitch($(this)); });

  function selectedProductIds(){
    return $('.ttos-product-select:checked').map(function(){ return this.value; }).get();
  }
  function refreshSelectedProducts(){
    const ids = selectedProductIds();
    $('.ttos-selected-products').val(ids.join(','));
    $('.ttos-selected-count').text(ids.length ? (ids.length + ' item' + (ids.length === 1 ? '' : 's') + ' selected') : 'No items selected');
  }
  $(document).on('change', '.ttos-product-select', refreshSelectedProducts);
  $(document).on('change', '.ttos-product-select-all', function(){
    $(this).closest('table').find('.ttos-product-select').prop('checked', this.checked);
    refreshSelectedProducts();
  });
  $(document).on('submit', '.ttos-menu-bulk-form', function(e){
    refreshSelectedProducts();
    if (!$(this).find('.ttos-selected-products').val()) {
      e.preventDefault();
      alert('Select at least one menu item first.');
    }
  });
  $(document).on('submit', '.ttos-product-order-form', function(){
    const pairs = $('.ttos-product-row').map(function(){
      const id = $(this).data('product-id');
      const order = $(this).find('.ttos-sort-input').val() || '0';
      return id + ':' + order;
    }).get();
    $(this).find('.ttos-product-order-field').val(pairs.join(','));
  });

  $('.ttos-sortable-categories').sortable({handle:'.ttos-drag', update:function(){}});
  $(document).on('submit', '.ttos-category-order-form', function(){
    const pairs = $(this).find('.ttos-category-pill').map(function(index){
      return $(this).data('term-id') + ':' + index;
    }).get();
    $(this).find('.ttos-category-order-field').val(pairs.join(','));
  });

  let orderTimer = null;
  let lastNewOrderCount = null;
  function orderFilters(){
    const filters = {method:'all', timing:'all'};
    $('.ttos-order-filter').each(function(){ filters[$(this).data('filter')] = $(this).val() || 'all'; });
    return filters;
  }
  function playOrderPing(){
    try {
      const AudioContext = window.AudioContext || window.webkitAudioContext;
      if (!AudioContext) return;
      const ctx = new AudioContext();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.value = 880;
      gain.gain.setValueAtTime(0.001, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.18, ctx.currentTime + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
      osc.connect(gain); gain.connect(ctx.destination); osc.start(); osc.stop(ctx.currentTime + 0.38);
    } catch(e) {}
  }
  function showOrderToast(message){
    let $toast = $('.ttos-order-toast');
    if (!$toast.length) $toast = $('<div class="ttos-order-toast" />').appendTo('body');
    $toast.text(message || 'Order board updated').addClass('is-visible');
    window.setTimeout(function(){ $toast.removeClass('is-visible'); }, 2400);
  }
  function refreshOrderBoard(){
    const $board = $('.ttos-order-board');
    if (!$board.length || typeof TTOSInstaller === 'undefined') return;
    const filters = orderFilters();
    $.ajax({
      url: TTOSInstaller.ajaxUrl,
      method: 'POST',
      dataType: 'json',
      data: {action:'ttos_order_board', nonce:TTOSInstaller.orderBoardNonce, context:$board.data('context') || 'orders', method:filters.method, timing:filters.timing}
    }).done(function(res){
      if (res && res.success && res.data && res.data.html) {
        const newCount = res.data.counts ? parseInt(res.data.counts.new || 0, 10) : 0;
        if (lastNewOrderCount !== null && newCount > lastNewOrderCount && $('.ttos-order-sound').is(':checked')) {
          playOrderPing();
          showOrderToast(TTOSInstaller.orderAlertText || 'New order received');
        }
        lastNewOrderCount = newCount;
        $board.html(res.data.html);
      }
    });
  }
  function setupOrderRefresh(){
    if (orderTimer) window.clearInterval(orderTimer);
    if ($('.ttos-order-autorefresh').is(':checked')) {
      orderTimer = window.setInterval(refreshOrderBoard, 30000);
    }
  }
  $(document).on('change', '.ttos-order-autorefresh', setupOrderRefresh);
  $(document).on('change', '.ttos-order-filter', function(){ lastNewOrderCount = null; refreshOrderBoard(); });
  $(document).on('click', '.ttos-test-alert', function(){ playOrderPing(); showOrderToast('Test ping fired. If the kitchen did not hear it, check browser audio permissions.'); });

  $(document).on('click', '.ttos-order-action-form button[type="submit"], .ttos-order-action-form button:not([type])', function(){
    const $btn = $(this);
    const $form = $btn.closest('form');
    $form.data('clicked-button', {name:$btn.attr('name') || '', value:$btn.val() || ''});
    if ($btn.data('prep')) $form.find('.ttos-prep-field').val($btn.data('prep'));
  });

  $(document).on('submit', '.ttos-order-action-form', function(e){
    if (typeof TTOSInstaller === 'undefined') return;
    e.preventDefault();
    const $form = $(this);
    const clicked = $form.data('clicked-button') || {};
    const filters = orderFilters();
    let data = $form.serializeArray();
    if (clicked.name) data.push(clicked);
    data.push({name:'action', value:'ttos_order_action'});
    data.push({name:'nonce', value:TTOSInstaller.orderActionNonce});
    data.push({name:'context', value:$('.ttos-order-board').data('context') || 'orders'});
    data.push({name:'method_filter', value:filters.method});
    data.push({name:'timing_filter', value:filters.timing});
    $form.addClass('is-working').find('button').prop('disabled', true);
    $.ajax({url:TTOSInstaller.ajaxUrl, method:'POST', dataType:'json', data:data})
      .done(function(res){
        if (res && res.success && res.data) {
          if (res.data.html) $('.ttos-order-board').html(res.data.html);
          showOrderToast(res.data.message || 'Order updated');
        } else {
          showOrderToast('Order update failed');
        }
      })
      .fail(function(xhr){
        const msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'Order update failed';
        showOrderToast(msg);
      })
      .always(function(){ $form.removeClass('is-working').find('button').prop('disabled', false); $form.removeData('clicked-button'); });
  });

  setupOrderRefresh();

});
