<?php
/**
 * Takeaway accessibility toolkit adapted from the Inkfire/BASE a11y pattern.
 */

defined('ABSPATH') || exit;

add_action('wp_footer', 'ttheme_accessibility_toolkit', 90);

function ttheme_accessibility_toolkit(): void {
    if (is_admin()) {
        return;
    }
    ?>
    <div class="tt-a11y" id="tt-a11y" data-tt-a11y>
        <button class="tt-a11y-toggle" type="button" aria-expanded="false" aria-controls="tt-a11y-panel">
            <span aria-hidden="true">Aa</span>
            <span class="screen-reader-text"><?php esc_html_e('Open accessibility tools', 'takeaway-theme'); ?></span>
        </button>
        <section class="tt-a11y-panel" id="tt-a11y-panel" hidden aria-label="<?php esc_attr_e('Accessibility tools', 'takeaway-theme'); ?>">
            <div class="tt-a11y-head">
                <strong><?php esc_html_e('Accessibility', 'takeaway-theme'); ?></strong>
                <button class="tt-a11y-close" type="button" aria-label="<?php esc_attr_e('Close accessibility tools', 'takeaway-theme'); ?>">×</button>
            </div>
            <div class="tt-a11y-grid">
                <button type="button" data-a11y-action="large-text" aria-pressed="false"><?php esc_html_e('Large text', 'takeaway-theme'); ?></button>
                <button type="button" data-a11y-action="contrast" aria-pressed="false"><?php esc_html_e('High contrast', 'takeaway-theme'); ?></button>
                <button type="button" data-a11y-action="dyslexia" aria-pressed="false"><?php esc_html_e('Dyslexia font', 'takeaway-theme'); ?></button>
                <button type="button" data-a11y-action="motion" aria-pressed="false"><?php esc_html_e('Reduce motion', 'takeaway-theme'); ?></button>
                <button type="button" data-a11y-action="spacing" aria-pressed="false"><?php esc_html_e('More spacing', 'takeaway-theme'); ?></button>
                <button type="button" data-a11y-action="reset"><?php esc_html_e('Reset', 'takeaway-theme'); ?></button>
            </div>
            <p class="tt-a11y-credit"><?php esc_html_e('Sonny x Inkfire accessibility tools', 'takeaway-theme'); ?></p>
        </section>
    </div>
    <script>
    (function(){
        var root = document.documentElement;
        var wrap = document.querySelector('[data-tt-a11y]');
        if (!wrap) return;
        var panel = wrap.querySelector('.tt-a11y-panel');
        var toggle = wrap.querySelector('.tt-a11y-toggle');
        var close = wrap.querySelector('.tt-a11y-close');
        var actions = ['large-text','contrast','dyslexia','motion','spacing'];
        var key = 'tt-a11y-prefs';
        function read(){
            try { return JSON.parse(localStorage.getItem(key) || '{}') || {}; } catch(e){ return {}; }
        }
        function write(state){
            try { localStorage.setItem(key, JSON.stringify(state)); } catch(e){}
        }
        function apply(state){
            actions.forEach(function(action){
                root.classList.toggle('tt-a11y-' + action, !!state[action]);
                var btn = wrap.querySelector('[data-a11y-action="' + action + '"]');
                if (btn) btn.setAttribute('aria-pressed', state[action] ? 'true' : 'false');
            });
        }
        function setOpen(open){
            panel.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) panel.querySelector('button').focus();
        }
        var state = read();
        apply(state);
        toggle.addEventListener('click', function(){ setOpen(panel.hidden); });
        close.addEventListener('click', function(){ setOpen(false); toggle.focus(); });
        wrap.addEventListener('click', function(e){
            var btn = e.target.closest('[data-a11y-action]');
            if (!btn) return;
            var action = btn.getAttribute('data-a11y-action');
            if (action === 'reset') {
                state = {};
            } else {
                state[action] = !state[action];
            }
            apply(state);
            write(state);
        });
        document.addEventListener('keydown', function(e){
            if (e.key === 'Escape' && !panel.hidden) setOpen(false);
        });
    })();
    </script>
    <?php
}
