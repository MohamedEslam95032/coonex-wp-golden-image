<?php
/**
 * Plugin Name: Xpress Core Enforcer
 * Description: Forces uiXpress as an internal admin engine (auto activate, hide, lock, block access).
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * CONFIG
 */
define('XPRESS_OPTION', 'xpress_uixpress_enforced');
define('XPRESS_PLUGIN_FILE', 'xpress/uixpress.php');
define('XPRESS_BLOCK_PAGES', ['uipress', 'uixpress', 'ui-xpress']);

/**
 * 1️⃣ Auto-activate uiXpress
 * (exact same context as manual activation)
 */
add_action('current_screen', function () {

    if (get_option(XPRESS_OPTION)) {
        return;
    }

    if (!is_admin() || !function_exists('get_current_screen')) {
        return;
    }

    $screen = get_current_screen();
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
 * 2️⃣ Self-healing
 * (if someone disables it by DB or any hack)
 */
add_action('admin_init', function () {

    $plugin_path = WP_PLUGIN_DIR . '/' . XPRESS_PLUGIN_FILE;
    if (!file_exists($plugin_path)) {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/plugin.php';

    if (!is_plugin_active(XPRESS_PLUGIN_FILE)) {
        activate_plugin(XPRESS_PLUGIN_FILE, '', false, true);
    }
});

/**
 * 3️⃣ Hide uiXpress from Plugins list
 */
add_filter('all_plugins', function ($plugins) {
    unset($plugins[XPRESS_PLUGIN_FILE]);
    return $plugins;
});

/**
 * 4️⃣ Remove deactivate & delete actions (extra safety)
 */
add_filter('plugin_action_links', function ($actions, $plugin_file) {
    if ($plugin_file === XPRESS_PLUGIN_FILE) {
        unset($actions['deactivate'], $actions['delete']);
    }
    return $actions;
}, 10, 2);

/**
 * 5️⃣ Force remove uiXpress from admin sidebar
 * (aggressive & final)
 */
add_action('admin_menu', function () {

    global $menu, $submenu;

    // Remove any menu containing xpress
    foreach ((array) $menu as $key => $item) {
        if (isset($item[0]) && stripos($item[0], 'xpress') !== false) {
            unset($menu[$key]);
        }
    }

    // Remove any submenu containing xpress
    foreach ((array) $submenu as $parent => $items) {
        foreach ((array) $items as $index => $sub) {
            if (isset($sub[0]) && stripos($sub[0], 'xpress') !== false) {
                unset($submenu[$parent][$index]);
            }
        }
    }

}, 9999);

/**
 * 6️⃣ Block direct access via URL (HARD BLOCK)
 * Even if user types the URL manually
 */
add_action('admin_init', function () {

    if (!isset($_GET['page'])) {
        return;
    }

    if (in_array($_GET['page'], XPRESS_BLOCK_PAGES, true)) {
        wp_die(
            __('Access denied.', 'xpress'),
            __('Restricted', 'xpress'),
            ['response' => 403]
        );
    }
});

/**
 * 7️⃣ Extra protection: block uiXpress assets pages
 */
add_action('current_screen', function () {

    if (!function_exists('get_current_screen')) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen) {
        return;
    }

    foreach (XPRESS_BLOCK_PAGES as $slug) {
        if (stripos($screen->id, $slug) !== false) {
            wp_die(
                __('Access denied.', 'xpress'),
                __('Restricted', 'xpress'),
                ['response' => 403]
            );
        }
    }
});
