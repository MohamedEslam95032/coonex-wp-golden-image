<?php
/**
 * Plugin Name: Xpress Core Installer
 * Description: Enforces uiXpress as always-on (auto activate, hide, protect).
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

define('XPRESS_OPTION', 'xpress_uixpress_enforced');
define('XPRESS_PLUGIN_FILE', 'xpress/uixpress.php');

/**
 * 1️⃣ Auto-activate uiXpress (safe timing)
 */
add_action('current_screen', function () {

    if (get_option(XPRESS_OPTION)) {
        return;
    }

    // لازم نكون داخل wp-admin
    if (!is_admin()) {
        return;
    }

    // نشتغل في نفس Context التفعيل اليدوي
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->id !== 'plugins') {
        return;
    }

    $plugin_path = WP_PLUGIN_DIR . '/' . XPRESS_PLUGIN_FILE;
    if (!file_exists($plugin_path)) {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/plugin.php';

    if (!is_plugin_active(XPRESS_PLUGIN_FILE)) {
        activate_plugin(XPRESS_PLUGIN_FILE);
    }

    update_option(XPRESS_OPTION, 1);
});

/**
 * 2️⃣ Prevent deactivation & deletion
 */
add_filter('plugin_action_links', function ($actions, $plugin_file) {
    if ($plugin_file === XPRESS_PLUGIN_FILE) {
        unset($actions['deactivate'], $actions['delete']);
    }
    return $actions;
}, 10, 2);

/**
 * 3️⃣ Hide uiXpress from Plugins list
 */
add_filter('all_plugins', function ($plugins) {
    if (isset($plugins[XPRESS_PLUGIN_FILE])) {
        unset($plugins[XPRESS_PLUGIN_FILE]);
    }
    return $plugins;
});

/**
 * 4️⃣ Enforce activation (self-healing)
 */
add_action('admin_init', function () {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';

    if (!is_plugin_active(XPRESS_PLUGIN_FILE)) {
        activate_plugin(XPRESS_PLUGIN_FILE, '', false, true);
    }
});
