<?php
/**
 * Plugin Name: Coonex Security Layer
 * Description: Core security rules for Coonex managed WordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * --------------------------------------------------
 * 1) Disable Theme Editor completely
 * --------------------------------------------------
 */

// Hide editor links from UI
add_action('admin_menu', function () {
    remove_submenu_page('themes.php', 'theme-editor.php');
});

// Extra hard block (direct URL access)
add_action('admin_init', function () {
    if (isset($_GET['file']) && strpos($_SERVER['PHP_SELF'], 'theme-editor.php') !== false) {
        wp_die(__('Theme editor is disabled on this platform.'));
    }
});


/**
 * --------------------------------------------------
 * 2) Block User Profile & Edit Profile (self included)
 * --------------------------------------------------
 */

// Remove profile menu items
add_action('admin_menu', function () {
    remove_menu_page('profile.php');          // Profile
    remove_submenu_page('users.php', 'profile.php');
});

// Block direct access to profile pages
add_action('admin_init', function () {

    $blocked_pages = [
        'profile.php',
        'user-edit.php',
    ];

    $current_page = basename($_SERVER['PHP_SELF']);

    if (in_array($current_page, $blocked_pages, true)) {
        wp_die(__('User profile editing is disabled on this platform.'));
    }
});

// Prevent REST-based profile updates (important)
add_filter('rest_authentication_errors', function ($result) {

    if (!is_user_logged_in()) {
        return $result;
    }

    $route = $_SERVER['REQUEST_URI'] ?? '';

    if (strpos($route, '/wp/v2/users') !== false) {
        return new WP_Error(
            'coonex_user_edit_blocked',
            __('User profile modification is disabled on this platform.'),
            ['status' => 403]
        );
    }

    return $result;
});
