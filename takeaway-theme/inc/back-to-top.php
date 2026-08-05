<?php
/**
 * Discreet back-to-top control.
 */

defined('ABSPATH') || exit;

add_action('wp_footer', 'ttheme_back_to_top_button', 10001);

function ttheme_back_to_top_button(): void {
    if (is_admin()) {
        return;
    }
    ?>
    <button class="tt-backtop" type="button" aria-label="<?php esc_attr_e('Back to top', 'takeaway-theme'); ?>" hidden>
        <svg aria-hidden="true" viewBox="0 0 24 24" focusable="false">
            <path d="M12 5.5 5.75 11.75l1.5 1.5L11 9.5V20h2V9.5l3.75 3.75 1.5-1.5L12 5.5Z"></path>
        </svg>
    </button>
    <style id="tt-backtop-style">
        .tt-backtop{
            position:fixed;
            left:16px;
            bottom:16px;
            z-index:99970;
            width:48px;
            height:48px;
            display:inline-grid;
            place-items:center;
            border:1px solid rgba(6,26,64,.14);
            border-radius:999px;
            background:rgba(255,255,255,.96);
            color:#0b5fff;
            box-shadow:0 10px 24px rgba(6,26,64,.13),0 0 0 1px rgba(255,255,255,.72);
            cursor:pointer;
            opacity:0;
            transform:translateY(8px);
            transition:opacity .18s ease,transform .18s ease,background .18s ease,color .18s ease,border-color .18s ease;
        }
        .tt-backtop:not([hidden]){
            opacity:1;
            transform:none;
        }
        .tt-backtop:hover{
            background:#0b5fff;
            border-color:#0b5fff;
            color:#fff;
            transform:translateY(-1px);
        }
        .tt-backtop:focus-visible{
            outline:3px solid #f5b700;
            outline-offset:3px;
        }
        .tt-backtop svg{
            width:23px;
            height:23px;
            fill:currentColor;
        }
        @media(max-width:1100px){
            .tt-backtop{
                left:12px;
                bottom:calc(88px + env(safe-area-inset-bottom,0px));
            }
        }
    </style>
    <script>
    (function(){
        var button = document.querySelector('.tt-backtop');
        if (!button) return;
        var threshold = 520;
        var ticking = false;
        function sync(){
            button.hidden = window.scrollY < threshold;
            ticking = false;
        }
        function requestSync(){
            if (ticking) return;
            ticking = true;
            window.requestAnimationFrame(sync);
        }
        button.addEventListener('click', function(){
            var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
        });
        window.addEventListener('scroll', requestSync, { passive: true });
        sync();
    })();
    </script>
    <?php
}
