<?php

defined('ABSPATH') || exit;

require_once TTHEME_DIR . '/inc/vendor/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Register GitHub-powered theme updates against packaged release assets.
 *
 * The Takeaway repo is a monorepo, so we must use the built theme zip from
 * GitHub releases rather than the raw repository archive.
 */
function ttheme_register_updater(): void {
    static $checker = null;

    if ($checker !== null) {
        return;
    }

    $checker = PucFactory::buildUpdateChecker(
        'https://github.com/hawks010/takeaway_theme/',
        TTHEME_DIR,
        'takeaway-theme'
    );

    if (method_exists($checker, 'setBranch')) {
        $checker->setBranch('main');
    }

    if (method_exists($checker, 'getVcsApi')) {
        $checker->getVcsApi()->enableReleaseAssets('/takeaway-theme-v.*-bundled\\.zip$/i');
    }
}

ttheme_register_updater();
