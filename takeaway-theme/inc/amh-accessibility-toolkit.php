<?php
/**
 * Plugin Name: BASE Accessibility Toolkit
 * Plugin URI: https://inkfire.co.uk
 * Description: WCAG 2.2 AA accessibility toolkit with an iOS-style mobile tray, desktop pill, and BASE brand styling.
 * Version: 1.1.18
 * Author: Sonny × Inkfire
 * Author URI: https://inkfire.co.uk
 * Text Domain: amh-a11y
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('AMH_Accessibility_Toolkit')) {

class AMH_Accessibility_Toolkit {

    private static $instance = null;
    private $version = '1.1.18';
    private $option_name = 'amh_a11y_settings';

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_footer', [$this, 'render_toolkit'], 9999);
    }

    public function add_admin_menu() {
        add_options_page(
            esc_html__('BASE Accessibility', 'amh-a11y'),
            esc_html__('BASE Accessibility', 'amh-a11y'),
            'manage_options',
            'amh-a11y-settings',
            [$this, 'render_admin_page']
        );
    }

    public function register_settings() {
        register_setting('amh_a11y_group', $this->option_name, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings'],
            'default' => [
                'enabled' => true,
                'save_preferences' => true,
                'load_fontawesome' => true,
            ]
        ]);
    }

    public function sanitize_settings($input) {
        return [
            'enabled' => !empty($input['enabled']),
            'save_preferences' => !empty($input['save_preferences']),
            'load_fontawesome' => !empty($input['load_fontawesome']),
        ];
    }

    public function enqueue_assets() {
        $settings = get_option($this->option_name, ['enabled' => true, 'load_fontawesome' => true]);
        if (empty($settings['enabled'])) return;

        if (!empty($settings['load_fontawesome'])) {
            $this->enqueue_fontawesome();
        }
    }

    public function enqueue_admin_assets($hook) {
        if ('settings_page_amh-a11y-settings' !== $hook) return;
        $this->enqueue_fontawesome();
    }

    private function enqueue_fontawesome() {
        if (function_exists('inkfire_enqueue_sitewide_fontawesome')) {
            inkfire_enqueue_sitewide_fontawesome();
            return;
        }

        wp_enqueue_style(
            'base-a11y-fontawesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css',
            array(),
            '6.5.2'
        );
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) wp_die('Insufficient permissions.');
        $s = get_option($this->option_name, ['enabled' => true, 'save_preferences' => true, 'load_fontawesome' => true]);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('amh_a11y_group'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="amh_enabled">Enable Toolkit</label></th>
                        <td><input type="checkbox" id="amh_enabled" name="<?php echo esc_attr($this->option_name); ?>[enabled]" value="1" <?php checked(!empty($s['enabled'])); ?> /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="amh_save">Save Preferences</label></th>
                        <td>
                            <input type="checkbox" id="amh_save" name="<?php echo esc_attr($this->option_name); ?>[save_preferences]" value="1" <?php checked(!empty($s['save_preferences'])); ?> />
                            <p class="description">Remember user preferences in localStorage.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="amh_fa">Load Font Awesome</label></th>
                        <td><input type="checkbox" id="amh_fa" name="<?php echo esc_attr($this->option_name); ?>[load_fontawesome]" value="1" <?php checked(!empty($s['load_fontawesome'])); ?> /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <hr>
            <h2>Architecture Status</h2>
            <div class="card" style="max-width:600px; padding:20px; background:#fff; border:1px solid #ddd;">
                <p><strong>Version:</strong> <?php echo esc_html($this->version); ?></p>
                <p><strong>Theme:</strong> BASE solid brand surfaces</p>
                <p><strong>Mobile Tray:</strong> Full Height (100vh) + 4-Col Grid</p>
                <p><strong>Pill:</strong> BASE red launcher + solid blue controls</p>
                <p><strong>TTS Engine:</strong> Inkfire article reader</p>
            </div>
        </div>
        <?php
    }

    public function render_toolkit() {
        $settings = get_option($this->option_name, ['enabled' => true, 'save_preferences' => true]);
        if (empty($settings['enabled'])) return;
        $save = !empty($settings['save_preferences']);

        $this->render_styles();
        $this->render_markup();
        $this->render_scripts($save);
    }

    private function render_styles() {
        ?>
        <style id="amh-a11y-styles">
            /* =========================================================
               FONTS & GLOBALS
               ========================================================= */
            @font-face {
                font-family: 'OpenDyslexic';
                src: url('https://cdn.jsdelivr.net/npm/opendyslexic@1.0.3/fonts/OpenDyslexic-Regular.woff') format('woff');
                font-weight: normal; font-style: normal; font-display: swap;
            }

            /* =========================================================
               ISOLATION CONTAINER (Strict Scoping)
               ========================================================= */
            #amh-a11y-wrapper {
                all: initial;
                display: block; 
                position: fixed; top: 0; left: 0; width: 100%; height: 0;
                z-index: 2147483647;
                font-family: 'Atkinson Hyperlegible Next', -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                overflow: visible;
                pointer-events: none;
                direction: ltr;
                
                /* THEME VARIABLES */
                --amh-green: #07A079;
                --amh-yellow: #E27200;
                --amh-white: #FFFFFF;
                --glass-bg: rgba(21, 22, 34, 0.94);
                --glass-bg-soft: rgba(21, 22, 34, 0.72);
                --glass-stroke: rgba(255, 255, 255, 0.14);
                --glass-glow: radial-gradient(circle at top left, rgba(7, 160, 121, 0.24) 0%, rgba(21, 22, 34, 0) 58%), radial-gradient(circle at bottom right, rgba(226, 114, 0, 0.16) 0%, rgba(21, 22, 34, 0) 48%);
                --glass-shadow: 0 15px 35px rgba(0,0,0,0.35), 0 0 30px rgba(7, 160, 121, 0.12);
                --radius: 35px;
                --radius-pill: 50px;
                --txt: #ffffff;
                --txt-soft: rgba(255,255,255,0.7);
                --focus: 0 0 0 4px rgba(242, 201, 76, 0.55);
            }
            #amh-a11y-wrapper * { box-sizing: border-box; pointer-events: auto; }
            #amh-a11y-wrapper .amh-a11y-sr { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
            #amh-a11y-wrapper .fa,
            #amh-a11y-wrapper .fas,
            #amh-a11y-wrapper .far,
            #amh-a11y-wrapper .fab,
            #amh-a11y-wrapper .fa-solid,
            #amh-a11y-wrapper .fa-regular,
            #amh-a11y-wrapper .fa-brands {
                display: inline-block;
                font-style: normal !important;
                font-variant: normal !important;
                line-height: 1 !important;
                text-rendering: auto;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }
            #amh-a11y-wrapper .fa,
            #amh-a11y-wrapper .fas,
            #amh-a11y-wrapper .fa-solid {
                font-family: "Font Awesome 6 Free", "Font Awesome 5 Free", FontAwesome !important;
                font-weight: 900 !important;
            }
            #amh-a11y-wrapper .far,
            #amh-a11y-wrapper .fa-regular {
                font-family: "Font Awesome 6 Free", "Font Awesome 5 Free", FontAwesome !important;
                font-weight: 400 !important;
            }
            #amh-a11y-wrapper .fab,
            #amh-a11y-wrapper .fa-brands {
                font-family: "Font Awesome 6 Brands", "Font Awesome 5 Brands", FontAwesome !important;
                font-weight: 400 !important;
            }

            /* =========================================================
               COMPONENT: PILL (Green Glass Theme, Centered Right)
               ========================================================= */
            #amh-a11y-wrapper .amh-a11y-pill {
                display: flex !important;
                position: fixed; 
                right: 10px; 
                top: 50%; 
                bottom: auto;
                transform: translateY(-50%) translateZ(0);
                flex-direction: column; 
                gap: 12px; 
                padding: 10px;
                
                /* Green Glass Theme */
                background: var(--glass-bg);
                border-radius: 50px;
                border: 1px solid var(--glass-stroke);
                box-shadow: 0 8px 30px rgba(0,0,0,0.25);
                backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
                
                z-index: 999990;
                transition: opacity 0.3s ease, transform 0.3s ease;
            }

            /* HERO MODE: Collapsed state (Load / Top of Page) */
            #amh-a11y-wrapper .amh-a11y-pill.amh-hero-mode {
                background: transparent;
                border: none;
                box-shadow: none;
                backdrop-filter: none;
                padding: 0;
                right: 20px;
                top: auto;
                bottom: 20px; /* Position bottom-right initially */
                transform: none;
            }
            #amh-a11y-wrapper .amh-a11y-pill.amh-hero-mode .amh-a11y-quick:not(#amh-a11y-expand) {
                display: none; /* Hide everything except main toggle */
            }
            #amh-a11y-wrapper .amh-a11y-pill.amh-hero-mode #amh-a11y-expand {
                width: 54px; height: 54px; font-size: 24px; /* Bigger trigger */
                background-color: #B95400;
                background-image: linear-gradient(135deg, rgba(142, 56, 0, 0.92) 0%, rgba(194, 84, 0, 0.96) 100%);
                color: #FFFFFF;
                box-shadow: 0 10px 24px rgba(0,0,0,0.28), inset 0 0 20px rgba(255, 255, 255, 0.08);
                border: 1px solid rgba(255, 190, 120, 0.45);
                border-top: 1px solid rgba(255, 255, 255, 0.25);
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
            }
            /* Reveal on hover while in hero mode */
            #amh-a11y-wrapper .amh-a11y-pill.amh-hero-mode:hover .amh-a11y-quick {
                display: flex;
            }
            #amh-a11y-wrapper .amh-a11y-pill.amh-hero-mode:hover {
                /* Apply Green Glass on hover expansion */
                background: var(--glass-bg);
                padding: 10px;
                border: 1px solid var(--glass-stroke);
                box-shadow: 0 8px 30px rgba(0,0,0,0.25);
                backdrop-filter: blur(16px);
                bottom: 20px;
            }
            #amh-a11y-wrapper .amh-a11y-pill.amh-hero-mode:hover #amh-a11y-expand {
                width: 44px; height: 44px; font-size: 18px; /* Revert size */
                background: var(--glass-bg);
                background-image: var(--glass-glow);
                border: 1px solid var(--glass-stroke);
                color: #fff;
                box-shadow: var(--glass-shadow);
                backdrop-filter: blur(16px);
                -webkit-backdrop-filter: blur(16px);
            }

            /* Standard Pill Buttons - White Glass Icons */
            #amh-a11y-wrapper .amh-a11y-quick {
                width: 44px; height: 44px; border-radius: 50%;
                /* White Glass */
                background: rgba(255, 255, 255, 0.12);
                border: 1px solid rgba(255, 255, 255, 0.25);
                color: #fff; 
                display:flex; align-items:center; justify-content:center;
                cursor:pointer; 
                transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); 
                font-size: 18px;
                flex-shrink: 0; padding: 0; margin: 0;
            }
            #amh-a11y-wrapper .amh-a11y-quick:hover {
                background: var(--amh-yellow); color: #151622; transform: scale(1.15) rotate(5deg);
                box-shadow: 0 4px 15px rgba(226, 114, 0, 0.35);
                border-color: var(--amh-yellow);
            }
            #amh-a11y-wrapper .amh-a11y-quick[aria-pressed="true"] {
                background: var(--amh-yellow); color: #151622; box-shadow: 0 0 15px rgba(226, 114, 0, 0.35);
            }
            #amh-a11y-wrapper :is(
                .amh-a11y-quick,
                .amh-a11y-module,
                .amh-a11y-slider-btn,
                .amh-a11y-reset,
                .amh-p-close,
                .amh-ruler-close
            ):focus-visible {
                outline: 3px solid #F2C94C !important;
                outline-offset: 3px !important;
                box-shadow: var(--focus) !important;
            }

            /* =========================================================
               COMPONENT: PANEL (Desktop)
               ========================================================= */
            #amh-a11y-wrapper .amh-a11y-panel {
                position: fixed; bottom: 20px; right: -550px; 
                width: min(420px, calc(100vw - 40px));
                display: flex; flex-direction: column; 
                max-height: 85vh; 
                
                border-radius: var(--radius); padding: 24px;
                background-color: var(--glass-bg); background-image: var(--glass-glow);
                border: 1px solid var(--glass-stroke);
                box-shadow: var(--glass-shadow);
                backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
                
                opacity: 0; visibility: hidden;
                transition: all 0.5s cubic-bezier(0.19, 1, 0.22, 1);
                z-index: 999991; color: var(--txt);
            }
            #amh-a11y-wrapper .amh-a11y-panel.is-open {
                right: 20px; opacity: 1; visibility: visible;
            }

            /* Header */
            #amh-a11y-wrapper .amh-p-header {
                display: flex; justify-content: space-between; align-items: center;
                gap: 12px; margin-bottom: 20px; flex-shrink: 0;
            }
            #amh-a11y-wrapper .amh-p-title {
                margin: 0; font-size: 1.35rem; font-weight: 800; color: var(--amh-white); line-height: 1.1;
            }
            #amh-a11y-wrapper .amh-p-subtitle {
                display: block; margin-top: 6px; font-size: 0.75rem; font-weight: 700;
                text-transform: uppercase; letter-spacing: 0.05em; color: var(--txt-soft);
            }
            #amh-a11y-wrapper .amh-p-subtitle a { color: var(--amh-yellow) !important; text-decoration: none !important; }
            
            /* Close Button: Brand Yellow Circle - Fixed Shape */
            #amh-a11y-wrapper .amh-p-close {
                width: 36px; height: 36px; 
                min-width: 36px; min-height: 36px; 
                border-radius: 50%;
                padding: 0; margin: 0;
                background: var(--amh-yellow) !important; 
                border: 1px solid var(--amh-yellow) !important;
                color: #151622 !important;
                display: flex; align-items: center; justify-content: center;
                cursor: pointer; font-size: 18px; 
                transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
                box-shadow: 0 4px 12px rgba(226, 114, 0, 0.24);
                flex-shrink: 0;
            }
            #amh-a11y-wrapper .amh-p-close:hover { 
                transform: scale(1.1) rotate(90deg); 
                background: #fff !important; 
                color: #151622 !important;
            }

            /* Content Scroller */
            #amh-a11y-wrapper .amh-p-content {
                flex: 1; overflow-y: auto; min-height: 0; padding-right: 6px; -webkit-overflow-scrolling: touch;
            }

            /* =========================================================
               COMPONENT: TRAY (Mobile)
               ========================================================= */
            #amh-a11y-wrapper .amh-a11y-tray {
                display: none; position: fixed; bottom: 0; left: 0; right: 0;
                z-index: 999997;
                transform: translateY(100%);
                transition: transform 0.45s cubic-bezier(0.25, 1, 0.5, 1);
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                height: 100dvh; /* Full Height on Mobile */
            }
            #amh-a11y-wrapper .amh-a11y-tray.is-open {
                transform: translateY(0);
                opacity: 1;
                visibility: visible;
                pointer-events: auto;
            }
            #amh-a11y-wrapper .amh-a11y-tray-inner {
                background: rgba(1, 49, 45, 0.98); backdrop-filter: blur(20px);
                border-radius: 0; /* Full screen needs no top radius */
                padding: 16px;
                padding-top: max(20px, env(safe-area-inset-top)); /* Safe area top */
                padding-bottom: calc(70px + max(10px, env(safe-area-inset-bottom)));
                height: 100%;
                overflow-y: auto;
                box-shadow: 0 -20px 60px rgba(0,0,0,0.3); 
                display: flex; flex-direction: column;
            }
            #amh-a11y-wrapper .amh-a11y-tray-handle {
                width: 40px; height: 4px; background: rgba(255,255,255,0.2); border-radius: 2px; margin: 0 auto 16px; flex-shrink: 0;
            }
            #amh-a11y-wrapper .amh-a11y-tray-head {
                display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-shrink: 0;
            }
            #amh-a11y-wrapper .amh-a11y-tray-title { font-size: 1.2rem; font-weight: 800; color: #fff; }
            #amh-a11y-wrapper .amh-a11y-tray-subtitle { font-size: 0.7rem; color: rgba(255,255,255,0.6); text-transform: uppercase; }

            /* =========================================================
               SHARED: GRID & MODULES
               ========================================================= */
            #amh-a11y-wrapper .amh-a11y-grid {
                display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; padding-bottom: 10px;
            }
            
            #amh-a11y-wrapper .amh-a11y-module,
            #amh-a11y-wrapper .amh-a11y-slider-row {
                background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.1);
                border-radius: 16px; color: var(--txt);
            }
            
            #amh-a11y-wrapper .amh-a11y-module {
                min-height: 80px; width: 100%; padding: 10px 6px;
                display: flex; flex-direction: column; align-items: center; justify-content: center;
                cursor: pointer; transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            }
            #amh-a11y-wrapper .amh-a11y-module:hover {
                background: rgba(226, 114, 0, 0.18) !important;
                border-color: rgba(226, 114, 0, 0.55) !important;
                transform: translateY(-3px) scale(1.02);
                box-shadow: 0 10px 20px rgba(0,0,0,0.15), 0 0 15px rgba(226, 114, 0, 0.2);
            }
            #amh-a11y-wrapper .amh-a11y-module:hover .amh-a11y-icon {
                background: var(--amh-yellow) !important;
                color: #151622 !important;
                transform: scale(1.1);
            }

            #amh-a11y-wrapper .amh-a11y-module.is-on,
            #amh-a11y-wrapper .amh-a11y-module[aria-pressed="true"] {
                background: rgba(226, 114, 0, 0.16); border-color: var(--amh-yellow);
                box-shadow: inset 0 0 20px rgba(226, 114, 0, 0.12);
            }
            #amh-a11y-wrapper .amh-a11y-module.is-on .amh-a11y-icon {
                background: var(--amh-yellow) !important; color: #151622 !important;
            }

            #amh-a11y-wrapper .amh-a11y-label {
                font-size: 0.75rem; font-weight: 700; text-align: center; margin-top: 8px; line-height: 1.2; color: var(--amh-white);
            }
            #amh-a11y-wrapper .amh-a11y-icon {
                width: 32px; height: 32px; border-radius: 999px;
                display: flex; align-items: center; justify-content: center; font-size: 15px;
                background: rgba(255,255,255,0.15) !important; color: var(--amh-white) !important;
                border: 1px solid rgba(255,255,255,0.2); transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            }

            #amh-a11y-wrapper .amh-span-2 { grid-column: span 2; }
            #amh-a11y-wrapper .amh-a11y-wide { flex-direction: row; gap: 12px; justify-content: flex-start; padding-left: 16px; }

            /* Sliders */
            #amh-a11y-wrapper .amh-a11y-slider-row { padding: 12px; display: flex; flex-direction: column; justify-content: space-between; }
            #amh-a11y-wrapper .amh-a11y-slider-head {
                display: flex; justify-content: space-between; gap: 10px;
                font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: var(--txt-soft); margin-bottom: 8px;
            }
            #amh-a11y-wrapper .amh-a11y-slider-val { color: var(--amh-white); font-weight: 900; }
            #amh-a11y-wrapper .amh-a11y-slider-btns { display: flex; gap: 8px; }
            #amh-a11y-wrapper .amh-a11y-slider-btn {
                flex: 1; height: 36px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.15);
                background: rgba(255,255,255,0.05); color: var(--amh-white);
                display: flex; align-items: center; justify-content: center; cursor: pointer;
                transition: all 0.2s ease;
            }
            #amh-a11y-wrapper .amh-a11y-slider-btn:hover { background: rgba(255,255,255,0.15); }
            #amh-a11y-wrapper .amh-a11y-slider-btn:active {
                background: var(--amh-yellow); color: #151622; border-color: transparent; transform: scale(0.95);
            }

            /* Reset */
            #amh-a11y-wrapper .amh-a11y-reset {
                grid-column: span 2; background: #FFF !important;
                border: 1px solid #FFF !important; border-radius: 16px; padding: 12px;
                display: flex; align-items: center; justify-content: center; gap: 8px;
                cursor: pointer; color: #C53030 !important; font-weight: 800; font-size: 0.8rem;
                min-height: 80px; transition: transform 0.2s;
            }
            #amh-a11y-wrapper .amh-a11y-reset:active { transform: scale(0.95); }
            #amh-a11y-wrapper .amh-a11y-reset i { color: #C53030; }
            #amh-a11y-wrapper .amh-a11y-reset:hover {
                background: #F7FAFC !important;
                border-color: #F7FAFC !important;
            }

            /* Entry Animation */
            @keyframes amhSlideUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
            .is-open .amh-a11y-module, .is-open .amh-a11y-slider-row, .is-open .amh-a11y-reset {
                opacity: 1;
                transform: translateY(0);
            }
            @media (prefers-reduced-motion: no-preference) {
                .is-open .amh-a11y-module, .is-open .amh-a11y-slider-row, .is-open .amh-a11y-reset {
                    animation: amhSlideUp 0.4s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
                    opacity: 0;
                }
                .is-open .amh-a11y-module:nth-child(1) { animation-delay: 0.05s; }
                .is-open .amh-a11y-slider-row:nth-child(2) { animation-delay: 0.1s; }
                .is-open .amh-a11y-slider-row:nth-child(3) { animation-delay: 0.15s; }
                .is-open .amh-a11y-slider-row:nth-child(4) { animation-delay: 0.2s; }
                .is-open .amh-a11y-module:nth-child(n+5) { animation-delay: 0.25s; }
            }

            /* Ruler */
            #amh-a11y-ruler {
                position: fixed; left: 0; width: 100%; height: 60px;
                background: rgba(242, 201, 76, 0.15);
                z-index: 2147483646; pointer-events: none; display: none;
                border-top: 2px solid var(--amh-yellow); border-bottom: 2px solid var(--amh-yellow);
                box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.5);
            }
            html.rts-ruler #amh-a11y-ruler { display: block; }
            #amh-a11y-ruler.is-visible { display: block !important; }
            #amh-a11y-ruler .amh-ruler-close {
                position: absolute; right: 20px; top: 50%; transform: translateY(-50%);
                width: 30px; height: 30px; border-radius: 50%;
                background: linear-gradient(135deg, rgba(21, 22, 34, 0.98) 0%, rgba(7, 160, 121, 0.78) 100%); color: #fff; border: 2px solid var(--amh-yellow);
                display: flex; align-items: center; justify-content: center;
                font-size: 18px; pointer-events: auto; cursor: pointer;
            }

            /* Responsive (Mobile Only) */
            @media (max-width: 1100px) {
                #amh-a11y-wrapper .amh-a11y-pill { display: none !important; }
                #amh-a11y-wrapper .amh-a11y-tray { display: block; }
                
                /* DENSITY FIX: 4 Columns on Mobile */
                #amh-a11y-wrapper .amh-a11y-grid { grid-template-columns: repeat(4, 1fr); gap: 8px; }
                #amh-a11y-wrapper .amh-span-2 { grid-column: span 2 !important; }
                
                /* Smaller buttons on mobile */
                #amh-a11y-wrapper .amh-a11y-module { min-height: 65px; padding: 8px 4px; }
                #amh-a11y-wrapper .amh-a11y-icon { width: 28px; height: 28px; font-size: 13px; margin-bottom: 4px; }
                #amh-a11y-wrapper .amh-a11y-label { font-size: 0.72rem; font-weight: 800; margin-top: 4px; }
                #amh-a11y-wrapper .amh-a11y-slider-row { padding: 8px 10px; }
                #amh-a11y-wrapper .amh-a11y-slider-head { font-size: 0.74rem; }
                #amh-a11y-wrapper .amh-a11y-reset { min-height: 65px; }
            }

            /* ---- GLOBAL MODES ---- */
            html.rts-dyslexia body, html.rts-dyslexia body * { font-family: 'OpenDyslexic', 'Comic Sans MS', sans-serif !important; }
            /* EXCLUDE ICONS from Dyslexia Font Override */
            html.rts-dyslexia body .fa, 
            html.rts-dyslexia body .fas, 
            html.rts-dyslexia body .far, 
            html.rts-dyslexia body .fab, 
            html.rts-dyslexia body .fa-solid, 
            html.rts-dyslexia body .fa-regular, 
            html.rts-dyslexia body .fa-brands,
            html.rts-dyslexia body i[class*="fa-"],
            html.rts-dyslexia body [class*="icon-"],
            html.rts-dyslexia body i {
                font-family: "Font Awesome 6 Free", "Font Awesome 5 Free", FontAwesome, sans-serif !important;
            }

            html.rts-font-boost body :is(p, li, span, div, a, button, input, textarea, select, h1, h2, h3, h4, h5, h6) { font-size: calc(100% + var(--rts-font-add, 0%)) !important; }
            html.rts-lineheight-boost body :is(p, li, h1, h2, h3, h4, h5, h6, blockquote, figcaption, label, button, a) { line-height: var(--rts-lineheight-value, 1.8) !important; }
            html.rts-spacing-boost body :is(p, li, h1, h2, h3, h4, h5, h6, blockquote, figcaption, label, button, a) { letter-spacing: var(--rts-spacing-value, 0.12em) !important; word-spacing: 0.16em !important; }
            html.rts-textalign body :is(p, li, h1, h2, h3, h4, h5, h6, blockquote, figcaption) { text-align: center !important; }
            html.rts-links body a { text-decoration: underline !important; font-weight: 700 !important; color: #A34700 !important; text-underline-offset: 0.16em !important; }
            html.rts-contrast { filter: contrast(1.5); }
            html.rts-darkmode { filter: invert(1) hue-rotate(180deg); background-color: #f0f0f0; }
            html.rts-darkmode img, html.rts-darkmode video, html.rts-darkmode iframe { filter: invert(1) hue-rotate(180deg); }
            html.rts-darkmode.rts-contrast { filter: invert(1) hue-rotate(180deg) contrast(1.5); }
            html.rts-monochrome { filter: grayscale(100%); }
            html.rts-saturate { filter: saturate(200%); }
            html.rts-calm { filter: sepia(30%) grayscale(20%); background-color: #fffff0; }
            html.rts-nomotion *, html.rts-nomotion *::before, html.rts-nomotion *::after { animation: none !important; transition: none !important; scroll-behavior: auto !important; }
            html.rts-nomotion #amh-a11y-wrapper .amh-a11y-module,
            html.rts-nomotion #amh-a11y-wrapper .amh-a11y-slider-row,
            html.rts-nomotion #amh-a11y-wrapper .amh-a11y-reset {
                opacity: 1 !important;
                transform: none !important;
            }
            html.rts-bigcursor, html.rts-bigcursor * { cursor: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48"><path d="M0 0 L0 32 L10 22 L20 32 L24 28 L14 18 L28 18 Z" fill="black" stroke="white" stroke-width="2"/></svg>'), auto !important; }
            html.rts-focus body::before { display: none; }
            html.rts-focus { cursor: none !important; }
            html.rts-focus :is(a, button, input, select, textarea, summary, [role="button"], [tabindex]:not([tabindex="-1"])):is(:hover, :focus-visible) {
                cursor: none !important;
                outline: 3px solid #E27200 !important;
                outline-offset: 3px !important;
                box-shadow: 0 0 0 4px rgba(226, 114, 0, 0.2) !important;
            }

            /* BASE brand override: keep the Inkfire toolkit behaviour, remove glass styling. */
            #amh-a11y-wrapper {
                --amh-green: #142151;
                --amh-yellow: #d13e43;
                --glass-bg: #142151;
                --glass-bg-soft: #1a2b6b;
                --glass-stroke: rgba(255, 255, 255, 0.18);
                --glass-glow: none;
                --glass-shadow: 0 18px 42px rgba(13, 22, 54, 0.32);
                --radius: 25px;
                --radius-pill: 25px;
                --focus: 0 0 0 4px rgba(209, 62, 67, 0.34);
            }
            #amh-a11y-wrapper .amh-a11y-pill,
            #amh-a11y-wrapper .amh-a11y-panel,
            #amh-a11y-wrapper .amh-a11y-tray-inner,
            #amh-a11y-wrapper .amh-a11y-module,
            #amh-a11y-wrapper .amh-a11y-slider-row {
                background-image: none !important;
                backdrop-filter: none !important;
                -webkit-backdrop-filter: none !important;
            }
            #amh-a11y-wrapper .amh-a11y-pill {
                right: 20px !important;
                top: auto !important;
                bottom: 20px !important;
                transform: none !important;
                gap: 10px !important;
                border-radius: 25px !important;
                border: 1px solid rgba(255, 255, 255, 0.16) !important;
                border-top: 1px solid rgba(255, 255, 255, 0.16) !important;
                border-bottom: 1px solid rgba(255, 255, 255, 0.16) !important;
                box-shadow: 0 18px 42px rgba(13, 22, 54, 0.32) !important;
            }
            #amh-a11y-wrapper .amh-a11y-pill.amh-hero-mode {
                background: transparent !important;
                border-color: transparent !important;
                box-shadow: none !important;
            }
            #amh-a11y-wrapper .amh-a11y-pill.amh-hero-mode #amh-a11y-expand,
            #amh-a11y-wrapper .amh-a11y-quick:hover,
            #amh-a11y-wrapper .amh-a11y-quick[aria-pressed="true"],
            #amh-a11y-wrapper .amh-p-close,
            #amh-a11y-wrapper .amh-a11y-reset:hover,
            #amh-a11y-wrapper .amh-a11y-module:hover .amh-a11y-icon,
            #amh-a11y-wrapper .amh-a11y-module.is-on .amh-a11y-icon,
            #amh-a11y-wrapper .amh-a11y-module[aria-pressed="true"] .amh-a11y-icon {
                background: #d13e43 !important;
                border-color: #d13e43 !important;
                color: #ffffff !important;
            }
            #amh-a11y-wrapper .amh-a11y-panel,
            #amh-a11y-wrapper .amh-a11y-tray-inner {
                background: #142151 !important;
                border: 1px solid rgba(255, 255, 255, 0.18) !important;
                border-radius: 25px !important;
            }
            #amh-a11y-wrapper .amh-a11y-grid {
                gap: 10px !important;
            }
            #amh-a11y-wrapper .amh-a11y-module,
            #amh-a11y-wrapper .amh-a11y-slider-row {
                background: #1a2b6b !important;
                border: 1px solid rgba(255, 255, 255, 0.16) !important;
                border-radius: 25px !important;
            }
            #amh-a11y-wrapper .amh-a11y-slider-btn,
            #amh-a11y-wrapper .amh-a11y-icon {
                background: rgba(255, 255, 255, 0.12) !important;
                color: #ffffff !important;
                border-color: rgba(255, 255, 255, 0.2) !important;
            }
            #amh-a11y-wrapper .amh-a11y-reset {
                border-radius: 25px !important;
                color: #d13e43 !important;
            }
            #amh-a11y-wrapper .amh-p-subtitle a,
            #amh-a11y-wrapper .amh-a11y-tray-subtitle a {
                color: #ffffff !important;
            }
            #amh-a11y-ruler {
                background: rgba(209, 62, 67, 0.14) !important;
                border-color: #d13e43 !important;
            }
            #amh-a11y-ruler .amh-ruler-close {
                background: #d13e43 !important;
                border-color: #d13e43 !important;
            }
            html.rts-focus :is(a, button, input, select, textarea, summary, [role="button"], [tabindex]:not([tabindex="-1"])):is(:hover, :focus-visible) {
                outline-color: #d13e43 !important;
                box-shadow: 0 0 0 4px rgba(209, 62, 67, 0.24) !important;
            }
            @media (max-width: 1100px) {
                #amh-a11y-wrapper .amh-a11y-pill {
                    display: flex !important;
                    right: 10px !important;
                    bottom: 10px !important;
                    top: auto !important;
                    transform: none !important;
                    gap: 0 !important;
                    padding: 0 !important;
                    background: transparent !important;
                    border: none !important;
                    box-shadow: none !important;
                    backdrop-filter: none !important;
                    -webkit-backdrop-filter: none !important;
                }
                #amh-a11y-wrapper .amh-a11y-pill .amh-a11y-quick:not(#amh-a11y-expand) {
                    display: none !important;
                }
                #amh-a11y-wrapper .amh-a11y-pill #amh-a11y-expand {
                    display: flex !important;
                    width: 54px !important;
                    height: 54px !important;
                    border-radius: 999px !important;
                    background: #d13e43 !important;
                    color: #ffffff !important;
                    border: 1px solid rgba(255, 255, 255, 0.2) !important;
                    box-shadow: 0 10px 24px rgba(13, 22, 54, 0.28) !important;
                }
            }
        </style>
        <?php
    }

    private function render_grid() {
        ?>
        <div class="amh-a11y-grid">
            <!-- Row 1 -->
            <button type="button" class="amh-a11y-module amh-a11y-wide amh-span-2" data-a11y="tts" aria-pressed="false" aria-label="<?php esc_attr_e('Toggle read aloud', 'amh-a11y'); ?>">
                <span class="amh-a11y-icon" style="background:#E27200; color:#151622;"><i class="fa-solid fa-volume-high"></i></span>
                <span class="amh-a11y-label"><?php esc_html_e('Read Aloud', 'amh-a11y'); ?></span>
            </button>
            <div class="amh-a11y-slider-row amh-span-2">
                <div class="amh-a11y-slider-head"><span><?php esc_html_e('Font Size', 'amh-a11y'); ?></span><span class="amh-a11y-slider-val" data-val="font">100%</span></div>
                <div class="amh-a11y-slider-btns">
                    <button type="button" class="amh-a11y-slider-btn" data-ctrl="font-dec" aria-label="<?php esc_attr_e('Decrease font size', 'amh-a11y'); ?>"><i class="fa-solid fa-minus"></i></button>
                    <button type="button" class="amh-a11y-slider-btn" data-ctrl="font-inc" aria-label="<?php esc_attr_e('Increase font size', 'amh-a11y'); ?>"><i class="fa-solid fa-plus"></i></button>
                </div>
            </div>

            <!-- Row 2 -->
            <div class="amh-a11y-slider-row amh-span-2">
                <div class="amh-a11y-slider-head"><span><?php esc_html_e('Zoom', 'amh-a11y'); ?></span><span class="amh-a11y-slider-val" data-val="zoom">100%</span></div>
                <div class="amh-a11y-slider-btns">
                    <button type="button" class="amh-a11y-slider-btn" data-ctrl="zoom-dec" aria-label="<?php esc_attr_e('Decrease zoom', 'amh-a11y'); ?>"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
                    <button type="button" class="amh-a11y-slider-btn" data-ctrl="zoom-inc" aria-label="<?php esc_attr_e('Increase zoom', 'amh-a11y'); ?>"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
                </div>
            </div>
            <div class="amh-a11y-slider-row amh-span-2">
                <div class="amh-a11y-slider-head"><span><?php esc_html_e('Line Height', 'amh-a11y'); ?></span><span class="amh-a11y-slider-val" data-val="line">Normal</span></div>
                <div class="amh-a11y-slider-btns">
                    <button type="button" class="amh-a11y-slider-btn" data-ctrl="line-dec" aria-label="<?php esc_attr_e('Decrease line height', 'amh-a11y'); ?>"><i class="fa-solid fa-arrow-down-short-wide"></i></button>
                    <button type="button" class="amh-a11y-slider-btn" data-ctrl="line-inc" aria-label="<?php esc_attr_e('Increase line height', 'amh-a11y'); ?>"><i class="fa-solid fa-arrow-up-wide-short"></i></button>
                </div>
            </div>

            <!-- Row 3 & 4 (Single Toggles) -->
            <?php
            $toggles = [
                ['dyslexia', 'fa-font', 'Dyslexia'],
                ['contrast', 'fa-circle-half-stroke', 'Invert'],
                ['darkmode', 'fa-moon', 'Dark'],
                ['monochrome', 'fa-droplet-slash', 'Mono'],
                ['saturate', 'fa-droplet', 'Vivid'],
                ['calm', 'fa-mug-hot', 'Calm'],
                ['links', 'fa-link', 'Links'],
                ['textalign', 'fa-align-center', 'Align'],
            ];
            foreach ($toggles as $t) : ?>
            <button type="button" class="amh-a11y-module" data-a11y-toggle="<?php echo esc_attr($t[0]); ?>" aria-pressed="false" aria-label="<?php echo esc_attr( sprintf( __( 'Toggle %s mode', 'amh-a11y' ), $t[2] ) ); ?>">
                <span class="amh-a11y-icon"><i class="fa-solid <?php echo esc_attr($t[1]); ?>"></i></span>
                <span class="amh-a11y-label"><?php echo esc_html($t[2]); ?></span>
            </button>
            <?php endforeach; ?>

            <!-- Row 5: Letter Spacing + Cursor + Ruler -->
            <div class="amh-a11y-slider-row amh-span-2">
                <div class="amh-a11y-slider-head"><span><?php esc_html_e('Letter Spacing', 'amh-a11y'); ?></span><span class="amh-a11y-slider-val" data-val="spacing">Normal</span></div>
                <div class="amh-a11y-slider-btns">
                    <button type="button" class="amh-a11y-slider-btn" data-ctrl="spacing-dec" aria-label="<?php esc_attr_e('Decrease letter spacing', 'amh-a11y'); ?>"><i class="fa-solid fa-compress"></i></button>
                    <button type="button" class="amh-a11y-slider-btn" data-ctrl="spacing-inc" aria-label="<?php esc_attr_e('Increase letter spacing', 'amh-a11y'); ?>"><i class="fa-solid fa-expand"></i></button>
                </div>
            </div>
            <button type="button" class="amh-a11y-module" data-a11y-toggle="bigcursor" aria-pressed="false" aria-label="<?php esc_attr_e('Toggle large cursor mode', 'amh-a11y'); ?>">
                <span class="amh-a11y-icon"><i class="fa-solid fa-arrow-pointer"></i></span>
                <span class="amh-a11y-label">Cursor</span>
            </button>
            <button type="button" class="amh-a11y-module" data-a11y-toggle="ruler" aria-pressed="false" aria-label="<?php esc_attr_e('Toggle reading ruler', 'amh-a11y'); ?>">
                <span class="amh-a11y-icon"><i class="fa-solid fa-ruler-horizontal"></i></span>
                <span class="amh-a11y-label">Ruler</span>
            </button>

            <!-- Row 6: Focus + No Motion + Reset -->
            <button type="button" class="amh-a11y-module" data-a11y-toggle="focus" aria-pressed="false" aria-label="<?php esc_attr_e('Toggle focus highlight mode', 'amh-a11y'); ?>">
                <span class="amh-a11y-icon"><i class="fa-solid fa-eye"></i></span>
                <span class="amh-a11y-label">Focus</span>
            </button>
            <button type="button" class="amh-a11y-module" data-a11y-toggle="nomotion" aria-pressed="false" aria-label="<?php esc_attr_e('Toggle reduced motion mode', 'amh-a11y'); ?>">
                <span class="amh-a11y-icon"><i class="fa-solid fa-ban"></i></span>
                <span class="amh-a11y-label">No Motion</span>
            </button>
            <button type="button" class="amh-a11y-reset amh-span-2" data-a11y-reset aria-label="<?php esc_attr_e('Reset all accessibility settings', 'amh-a11y'); ?>">
                <i class="fa-solid fa-rotate-left"></i> <?php esc_html_e('Reset All', 'amh-a11y'); ?>
            </button>
        </div>
        <?php
    }

    private function render_markup() {
        $credit = sprintf('by %s', '<a href="https://inkfire.co.uk" target="_blank" rel="noopener noreferrer">Sonny x Inkfire</a>');
        ?>
        <div id="amh-a11y-wrapper">
            <!-- SR Live -->
            <div id="amh-a11y-live" class="amh-a11y-sr" role="status" aria-live="polite"></div>

            <!-- DESKTOP PILL -->
            <div class="amh-a11y-pill amh-hero-mode" id="amh-a11y-pill">
                <button type="button" class="amh-a11y-quick" data-a11y="tts" aria-pressed="false" aria-label="Text to speech"><i class="fa-solid fa-volume-high"></i></button>
                <button type="button" class="amh-a11y-quick" data-a11y-toggle="dyslexia" aria-pressed="false" aria-label="Dyslexia font"><i class="fa-solid fa-font"></i></button>
                <button type="button" class="amh-a11y-quick" data-a11y-toggle="ruler" aria-pressed="false" aria-label="Reading ruler"><i class="fa-solid fa-ruler-horizontal"></i></button>
                <button type="button" class="amh-a11y-quick" data-a11y-toggle="contrast" aria-pressed="false" aria-label="Invert colours"><i class="fa-solid fa-circle-half-stroke"></i></button>
                <button type="button" class="amh-a11y-quick" id="amh-a11y-expand" aria-controls="amh-a11y-panel" aria-expanded="false" aria-label="Open accessibility menu"><i class="fa-solid fa-universal-access"></i></button>
            </div>

            <!-- DESKTOP PANEL -->
            <div class="amh-a11y-panel" id="amh-a11y-panel" role="dialog" aria-modal="false" aria-labelledby="amh-a11y-panel-title" aria-hidden="true">
                <div class="amh-p-header">
                    <div>
                        <h2 class="amh-p-title" id="amh-a11y-panel-title">Accessibility Tools</h2>
                        <span class="amh-p-subtitle"><?php echo $credit; ?></span>
                    </div>
                    <button type="button" class="amh-p-close" id="amh-a11y-panel-close" aria-label="<?php esc_attr_e('Close accessibility panel', 'amh-a11y'); ?>"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="amh-p-content"><?php $this->render_grid(); ?></div>
            </div>

            <!-- MOBILE TRAY -->
            <div class="amh-a11y-tray" id="amh-a11y-tray" role="dialog" aria-modal="false" aria-labelledby="amh-a11y-tray-title" aria-hidden="true">
                <div class="amh-a11y-tray-inner">
                    <div class="amh-a11y-tray-handle"></div>
                    <div class="amh-a11y-tray-head">
                        <div>
                            <div class="amh-a11y-tray-title" id="amh-a11y-tray-title">Accessibility</div>
                            <div class="amh-a11y-tray-subtitle"><?php echo $credit; ?></div>
                        </div>
                        <!-- Added Close Button for Mobile -->
                        <button type="button" class="amh-p-close" id="amh-a11y-tray-close" aria-label="Close tray"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <?php $this->render_grid(); ?>
                </div>
            </div>

            <!-- RULER -->
            <div id="amh-a11y-ruler" aria-hidden="true">
                <button type="button" class="amh-ruler-close" aria-label="<?php esc_attr_e('Close reading ruler', 'amh-a11y'); ?>">×</button>
            </div>
        </div>
        <?php
    }

    private function render_scripts($save) {
        ?>
        <script>
        (function(){
            'use strict';
            const SAVE = <?php echo $save ? 'true' : 'false'; ?>;
            const KEY = 'amh_a11y_prefs_v2';
            const RULER_HEIGHT = 60;
            let state = { font: 0, zoom: 100, lineHeight: 0, spacing: 0, toggles: {} };
            
            function announce(msg) {
                const el = document.getElementById('amh-a11y-live');
                if(el) { el.textContent = ''; setTimeout(() => el.textContent = msg, 50); }
            }
            function save() {
                if(!SAVE) return;
                try { localStorage.setItem(KEY, JSON.stringify(state)); } catch(e){}
            }

            function chunkText(text, maxLength) {
                const limit = maxLength || 220;
                const clean = (text || '').replace(/\s+/g, ' ').trim();
                if (!clean) return [];
                const sentences = clean.match(/[^.!?]+[.!?]*/g) || [clean];
                const chunks = [];
                let current = '';

                sentences.forEach(function(sentence) {
                    const piece = sentence.trim();
                    if (!piece) return;
                    if ((current + ' ' + piece).trim().length <= limit) {
                        current = (current ? current + ' ' : '') + piece;
                        return;
                    }
                    if (current) {
                        chunks.push(current.trim());
                    }
                    if (piece.length <= limit) {
                        current = piece;
                        return;
                    }
                    for (let i = 0; i < piece.length; i += limit) {
                        chunks.push(piece.slice(i, i + limit).trim());
                    }
                    current = '';
                });

                if (current) {
                    chunks.push(current.trim());
                }

                return chunks;
            }

            function syncRuler() {
                const ruler = document.getElementById('amh-a11y-ruler');
                if (!ruler) return;
                const on = !!state.toggles.ruler;
                ruler.classList.toggle('is-visible', on);
                ruler.setAttribute('aria-hidden', on ? 'false' : 'true');
                ruler.style.display = on ? 'block' : 'none';
                if (on && !ruler.style.top) {
                    ruler.style.top = '30vh';
                }
            }

            function sync() {
                document.querySelectorAll('[data-a11y-toggle]').forEach(b => {
                    const k = b.dataset.a11yToggle;
                    const on = !!state.toggles[k];
                    b.classList.toggle('is-on', on);
                    b.setAttribute('aria-pressed', on);
                });
                document.querySelectorAll('[data-a11y="tts"]').forEach(b => {
                    b.setAttribute('aria-pressed', tts.active ? 'true' : 'false');
                    b.setAttribute('aria-label', tts.active ? 'Stop reading aloud' : 'Start reading aloud');
                });
                const setVal = (k, v) => document.querySelectorAll(`[data-val="${k}"]`).forEach(e => e.textContent = v);
                setVal('font', (100 + (state.font*10)) + '%');
                setVal('zoom', state.zoom + '%');
                setVal('line', state.lineHeight === 0 ? 'Normal' : (1.6 + (state.lineHeight * 0.2)).toFixed(1));
                setVal('spacing', state.spacing === 0 ? 'Normal' : (state.spacing === 1 ? 'Med' : 'Wide'));
                document.querySelectorAll('[data-a11y="tts"]').forEach(b => {
                    b.classList.toggle('is-on', tts.active);
                    const l = b.querySelector('.amh-a11y-label');
                    if(l) l.textContent = tts.active ? 'Stop Reading' : 'Read Aloud';
                });
            }

            function apply() {
                const html = document.documentElement;
                Object.keys(state.toggles).forEach(k => { html.classList.toggle('rts-' + k, state.toggles[k]); });
                
                if (state.font > 0) { document.body.style.setProperty('--rts-font-add', (state.font * 10) + '%'); html.classList.add('rts-font-boost'); }
                else { document.body.style.removeProperty('--rts-font-add'); html.classList.remove('rts-font-boost'); }

                document.body.style.zoom = ''; document.body.style.transform = ''; document.body.style.width = '';
                if (state.zoom !== 100) {
                    if ('zoom' in document.body.style) { document.body.style.zoom = state.zoom + '%'; }
                    else { const s = state.zoom / 100; document.body.style.transform = `scale(${s})`; document.body.style.transformOrigin = 'top center'; document.body.style.width = `${100/s}%`; }
                }

                if (state.lineHeight > 0) { document.body.style.setProperty('--rts-lineheight-value', 1.6 + (state.lineHeight * 0.2)); html.classList.add('rts-lineheight-boost'); }
                else { document.body.style.removeProperty('--rts-lineheight-value'); html.classList.remove('rts-lineheight-boost'); }

                if (state.spacing > 0) { document.body.style.setProperty('--rts-spacing-value', (state.spacing * 0.12) + 'em'); html.classList.add('rts-spacing-boost'); }
                else { document.body.style.removeProperty('--rts-spacing-value'); html.classList.remove('rts-spacing-boost'); }

                syncRuler();
                sync();
                save();
            }

            function toggle(k) { state.toggles[k] = !state.toggles[k]; apply(); }
            
            const ctrls = {
                'font-inc': () => { if(state.font < 4) state.font++; },
                'font-dec': () => { if(state.font > 0) state.font--; },
                'zoom-inc': () => { if(state.zoom < 200) state.zoom += 10; },
                'zoom-dec': () => { if(state.zoom > 50) state.zoom -= 10; },
                'line-inc': () => { if(state.lineHeight < 4) state.lineHeight++; },
                'line-dec': () => { if(state.lineHeight > 0) state.lineHeight--; },
                'spacing-inc': () => { if(state.spacing < 2) state.spacing++; }, 
                'spacing-dec': () => { if(state.spacing > 0) state.spacing--; },
            };

            function reset() {
                state = { font: 0, zoom: 100, lineHeight: 0, spacing: 0, toggles: {} };
                if (tts.active) tts.stop();
                // Ensure DOM clean up
                document.body.style.removeProperty('--rts-font-add');
                document.body.style.removeProperty('--rts-lineheight-value');
                document.body.style.removeProperty('--rts-spacing-value');
                document.body.style.zoom = ''; document.body.style.transform = ''; document.body.style.width = '';
                // Specific cleanup for Focus mode which uses !important
                document.documentElement.classList.remove('rts-focus');
                document.body.style.cursor = ''; // Force clear
                
                apply();
                announce('Reset complete');
            }

            /* =========================================================
               READ ALOUD
               ========================================================= */
            const tts = {
                active: false,
                synth: window.speechSynthesis,
                utterance: null,
                voices: [],

                init: () => {
                    if (!tts.synth) return;
                    const load = () => { tts.voices = tts.synth.getVoices(); };
                    load();
                    if (typeof tts.synth.onvoiceschanged !== 'undefined') {
                        tts.synth.onvoiceschanged = load;
                    }
                },

                getVoice: () => {
                    if (!tts.voices.length && tts.synth) {
                        tts.voices = tts.synth.getVoices();
                    }
                    let v = tts.voices.find(x => x.lang && x.lang.startsWith('en') && x.name && x.name.includes('Google'));
                    if (!v) v = tts.voices.find(x => x.lang && x.lang.startsWith('en'));
                    if (!v) v = tts.voices[0];
                    return v;
                },

                getText: () => {
                    const container = document.querySelector('article, [role="main"], .entry-content, .post-content, main');
                    if (container) {
                        const text = (container.innerText || '').replace(/\s+/g, ' ').trim();
                        if (text.length > 50) {
                            return text;
                        }
                    }
                    return (document.body.innerText || '').replace(/\s+/g, ' ').trim().substring(0, 1000);
                },

                speak: () => {
                    if (!tts.synth || typeof window.SpeechSynthesisUtterance === 'undefined') {
                        announce('Read aloud is not available in this browser');
                        return;
                    }

                    if (tts.active) {
                        tts.stop();
                        return;
                    }

                    const text = tts.getText();
                    if (text && text.length > 50) {
                        tts.synth.cancel();
                        tts.utterance = new SpeechSynthesisUtterance(text);
                        const voice = tts.getVoice();
                        if (voice) {
                            tts.utterance.voice = voice;
                        }
                        tts.utterance.onstart = () => {
                            tts.active = true;
                            sync();
                            announce('Read aloud started');
                        };
                        tts.utterance.onend = () => tts.stop();
                        tts.utterance.onerror = () => tts.stop();
                        tts.synth.speak(tts.utterance);
                    } else {
                        announce('No content found');
                    }
                },

                stop: () => {
                    if (tts.synth) {
                        tts.synth.cancel();
                    }
                    tts.active = false;
                    tts.utterance = null;
                    sync();
                    announce('Read aloud stopped');
                }
            };
            
            // Initial Load
            tts.init();
            // Retry voices load after delay just in case
            setTimeout(tts.init, 500);

            document.addEventListener('click', e => {
                const btn = e.target.closest('button');
                if (!btn) return;
                if (btn.dataset.a11yToggle) { toggle(btn.dataset.a11yToggle); return; }
                if (btn.dataset.ctrl) { ctrls[btn.dataset.ctrl](); apply(); return; }
                if (btn.dataset.a11y === 'tts') { tts.speak(); return; }
                if (btn.hasAttribute('data-a11y-reset')) { reset(); return; }
                if (btn.id === 'amh-a11y-expand') {
                    handleToggle(true);
                    return;
                }
                if (btn.id === 'amh-a11y-panel-close' || btn.id === 'amh-a11y-tray-close') {
                    document.getElementById('amh-a11y-panel').classList.remove('is-open');
                    document.getElementById('amh-a11y-expand').setAttribute('aria-expanded', 'false');
                    handleToggle(false); // Ensure tray closes too
                }
                if (btn.classList.contains('amh-ruler-close')) {
                    state.toggles.ruler = false; apply();
                }
            });

            /* SCROLL LISTENER: Hero Mode */
            var pill = document.getElementById('amh-a11y-pill');
            if (pill) {
                window.addEventListener('scroll', function() {
                    if (window.scrollY > 100) {
                        pill.classList.remove('amh-hero-mode');
                    } else {
                        pill.classList.add('amh-hero-mode');
                    }
                }, {passive: true});
            }

            /* Tray Connector & Smart Toggle Logic */
            const tray = document.getElementById('amh-a11y-tray');
            const panel = document.getElementById('amh-a11y-panel');
            const expandBtn = document.getElementById('amh-a11y-expand');
            let lastFocusTrigger = expandBtn || null;

            function handleToggle(open) {
                // If on mobile/tablet (<= 1100px), toggle the tray
                if (window.innerWidth <= 1100) {
                    if (tray) {
                        tray.classList.toggle('is-open', open);
                        tray.setAttribute('aria-hidden', open ? 'false' : 'true');
                    }
                    if (panel) {
                        panel.classList.remove('is-open');
                        panel.setAttribute('aria-hidden', 'true');
                    }
                } else {
                    // On desktop, toggle the panel
                    if (panel && open) {
                        panel.classList.add('is-open');
                        panel.setAttribute('aria-hidden', 'false');
                        if (expandBtn) expandBtn.setAttribute('aria-expanded', 'true');
                    } else if (panel && !open) {
                        panel.classList.remove('is-open');
                        panel.setAttribute('aria-hidden', 'true');
                        if (expandBtn) expandBtn.setAttribute('aria-expanded', 'false');
                    }
                    if (tray) {
                        tray.classList.remove('is-open');
                        tray.setAttribute('aria-hidden', 'true');
                    }
                }
                // Notify others (header) about state
                document.dispatchEvent(new CustomEvent('amh:a11y:state', { detail: { open: open } }));

                const target = window.innerWidth <= 1100
                    ? document.getElementById('amh-a11y-tray-close')
                    : document.getElementById('amh-a11y-panel-close');

                if (open && target) {
                    requestAnimationFrame(() => target.focus());
                }

                if (!open && lastFocusTrigger) {
                    requestAnimationFrame(() => lastFocusTrigger.focus());
                }
            }

            document.addEventListener('amh:a11y:toggle', function(e) {
                handleToggle(e.detail && e.detail.open);
            });

            document.addEventListener('amh:a11y:close', function() {
                handleToggle(false);
            });

            handleToggle(false);

            /* Swipe & Drag Logic for Mobile Tray */
            if (tray) {
                var tY = 0;
                var header = tray.querySelector('.amh-a11y-tray-head');
                var handle = tray.querySelector('.amh-a11y-tray-handle');
                
                // Attach to header/handle for dragging down to close
                function touchStart(e) { tY = e.touches[0].clientY; }
                function touchMove(e) {
                    // Only close if dragging down significantly
                    if (e.touches[0].clientY - tY > 60) handleToggle(false);
                }
                if (header) {
                    header.addEventListener('touchstart', touchStart, {passive: true});
                    header.addEventListener('touchmove', touchMove, {passive: true});
                }
                if (handle) {
                    handle.addEventListener('touchstart', touchStart, {passive: true});
                    handle.addEventListener('touchmove', touchMove, {passive: true});
                }
            }

            document.addEventListener('mousemove', function(e) {
                if (!state.toggles.ruler) return;
                const ruler = document.getElementById('amh-a11y-ruler');
                if (!ruler) return;
                const top = Math.max(0, e.clientY - (RULER_HEIGHT / 2));
                ruler.style.top = top + 'px';
            }, { passive: true });

            /* Load saved preferences */
            if (SAVE) {
                try {
                    var saved = localStorage.getItem(KEY);
                    if (saved) {
                        var p = JSON.parse(saved);
                        if (typeof p === 'object') {
                            state = Object.assign(state, p);
                            // Apply state correctly using the existing function
                            apply(); 
                        }
                    }
                } catch(e){}
            }

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && ((panel && panel.classList.contains('is-open')) || (tray && tray.classList.contains('is-open')))) {
                    handleToggle(false);
                }
            });

            document.addEventListener('click', function(e) {
                if (e.target && e.target.id === 'amh-a11y-expand') {
                    lastFocusTrigger = e.target;
                }
            }, true);
        })();
        </script>
        <?php
    }
}
AMH_Accessibility_Toolkit::get_instance();
}
