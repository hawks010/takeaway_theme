<?php
/**
 * Universal colour treatment for the copied BASE accessibility toolkit.
 *
 * Keep the AMH toolkit source exact, then layer this theme colour pass after it.
 */

defined('ABSPATH') || exit;

add_action('wp_footer', 'ttheme_amh_accessibility_universal_colour', 10000);

function ttheme_amh_accessibility_universal_colour(): void {
    if (is_admin()) {
        return;
    }
    ?>
    <style id="tt-amh-universal-colour">
        #amh-a11y-wrapper{
            --amh-green:#0b5fff;
            --amh-yellow:#f5b700;
            --glass-bg:#061a40;
            --glass-bg-soft:#0a2d67;
            --glass-stroke:rgba(255,255,255,.2);
            --glass-glow:
                radial-gradient(circle at 12% 10%,rgba(11,95,255,.34) 0%,rgba(11,95,255,0) 42%),
                radial-gradient(circle at 88% 18%,rgba(245,183,0,.18) 0%,rgba(245,183,0,0) 38%),
                linear-gradient(145deg,#061a40 0%,#0b2f6f 58%,#061a40 100%);
            --glass-shadow:0 22px 58px rgba(6,26,64,.34),0 0 0 1px rgba(255,255,255,.06);
            --focus:0 0 0 4px rgba(245,183,0,.42);
        }
        #amh-a11y-wrapper .amh-a11y-panel,
        #amh-a11y-wrapper .amh-a11y-tray-inner{
            background:var(--glass-glow)!important;
            border-color:rgba(255,255,255,.2)!important;
            box-shadow:var(--glass-shadow)!important;
        }
        #amh-a11y-wrapper .amh-a11y-pill{
            background:transparent!important;
            border-color:transparent!important;
            box-shadow:none!important;
            padding:0!important;
            gap:0!important;
        }
        #amh-a11y-wrapper .amh-a11y-pill .amh-a11y-quick:not(#amh-a11y-expand){
            display:none!important;
        }
        #amh-a11y-wrapper .amh-a11y-pill.amh-hero-mode{
            background:transparent!important;
            box-shadow:none!important;
            right:16px!important;
            bottom:16px!important;
        }
        #amh-a11y-wrapper .amh-a11y-pill.amh-hero-mode #amh-a11y-expand,
        #amh-a11y-wrapper .amh-a11y-pill #amh-a11y-expand{
            width:48px!important;
            height:48px!important;
            background:rgba(255,255,255,.96)!important;
            border-color:rgba(6,26,64,.14)!important;
            color:#0b5fff!important;
            box-shadow:0 10px 24px rgba(6,26,64,.13),0 0 0 1px rgba(255,255,255,.72)!important;
        }
        #amh-a11y-wrapper .amh-a11y-pill.amh-hero-mode #amh-a11y-expand:hover,
        #amh-a11y-wrapper .amh-a11y-pill #amh-a11y-expand:hover{
            background:#0b5fff!important;
            border-color:#0b5fff!important;
            color:#fff!important;
            transform:translateY(-1px)!important;
        }
        #amh-a11y-wrapper .amh-a11y-module,
        #amh-a11y-wrapper .amh-a11y-slider-row{
            background:rgba(255,255,255,.1)!important;
            border-color:rgba(255,255,255,.16)!important;
        }
        #amh-a11y-wrapper .amh-a11y-module:hover,
        #amh-a11y-wrapper .amh-a11y-module.is-on,
        #amh-a11y-wrapper .amh-a11y-module[aria-pressed="true"]{
            background:rgba(11,95,255,.26)!important;
            border-color:#5ea0ff!important;
            box-shadow:inset 0 0 0 1px rgba(255,255,255,.06),0 12px 28px rgba(11,95,255,.16)!important;
        }
        #amh-a11y-wrapper .amh-a11y-icon,
        #amh-a11y-wrapper .amh-a11y-slider-btn,
        #amh-a11y-wrapper .amh-a11y-quick{
            background:rgba(255,255,255,.14)!important;
            border-color:rgba(255,255,255,.22)!important;
            color:#fff!important;
        }
        #amh-a11y-wrapper .amh-a11y-quick:hover,
        #amh-a11y-wrapper .amh-a11y-quick[aria-pressed="true"],
        #amh-a11y-wrapper .amh-a11y-module:hover .amh-a11y-icon,
        #amh-a11y-wrapper .amh-a11y-module.is-on .amh-a11y-icon,
        #amh-a11y-wrapper .amh-a11y-module[aria-pressed="true"] .amh-a11y-icon,
        #amh-a11y-wrapper .amh-a11y-slider-btn:active{
            background:#f5b700!important;
            border-color:#f5b700!important;
            color:#061a40!important;
        }
        #amh-a11y-wrapper .amh-p-close,
        #amh-a11y-wrapper .amh-ruler-close{
            background:#f5b700!important;
            border-color:#f5b700!important;
            color:#061a40!important;
        }
        #amh-a11y-wrapper .amh-p-close:hover,
        #amh-a11y-wrapper .amh-ruler-close:hover{
            background:#fff!important;
            border-color:#fff!important;
            color:#061a40!important;
        }
        #amh-a11y-wrapper .amh-a11y-reset{
            background:#fff!important;
            border-color:#fff!important;
            color:#0b5fff!important;
        }
        #amh-a11y-wrapper .amh-a11y-reset:hover{
            background:#f5b700!important;
            border-color:#f5b700!important;
            color:#061a40!important;
        }
        #amh-a11y-wrapper .amh-p-subtitle a,
        #amh-a11y-wrapper .amh-a11y-tray-subtitle a{
            color:#f5b700!important;
        }
        #amh-a11y-ruler{
            background:rgba(245,183,0,.15)!important;
            border-color:#f5b700!important;
        }
        html.rts-links body a{
            color:#0b5fff!important;
        }
        html.rts-focus :is(a,button,input,select,textarea,summary,[role="button"],[tabindex]:not([tabindex="-1"])):is(:hover,:focus-visible){
            outline-color:#f5b700!important;
            box-shadow:0 0 0 4px rgba(245,183,0,.32)!important;
        }
        @media(max-width:1100px){
            #amh-a11y-wrapper .amh-a11y-pill,
            #amh-a11y-wrapper .amh-a11y-pill.amh-hero-mode{
                right:12px!important;
                bottom:calc(88px + env(safe-area-inset-bottom,0px))!important;
            }
            #amh-a11y-wrapper .amh-a11y-pill #amh-a11y-expand{
                width:48px!important;
                height:48px!important;
                background:rgba(255,255,255,.96)!important;
                color:#0b5fff!important;
                box-shadow:0 10px 24px rgba(6,26,64,.14)!important;
            }
        }
    </style>
    <?php
}
