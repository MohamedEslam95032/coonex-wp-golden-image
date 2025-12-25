<?php
/**
 * Plugin Name: Coonex JWT SSO
 * Description: Secure JWT-based Single Sign-On for Coonex (SSO only – no UI restrictions)
 * Version: 1.0.0
 * Author: Coonex
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * =====================================================
 * MAIN SSO HANDLER
 * =====================================================
 */
function coonex_handle_sso() {

    // Enforce SSO-only login
    if (!isset($_GET['token'])) {
        wp_die('Login via Coonex only');
    }

    $jwt    = trim($_GET['token']);
    $secret = getenv('COONEX_SSO_SECRET');

    if (!$secret) {
        wp_die('SSO secret not configured');
    }

    // Validate JWT structure
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        wp_die('Invalid token structure');
    }

    [$header, $payload, $signature] = $parts;

    // Verify signature (HS256)
    $expected_signature = rtrim(strtr(
        base64_encode(
            hash_hmac('sha256', "$header.$payload", $secret, true)
        ),
        '+/',
        '-_'
    ), '=');

    if (!hash_equals($expected_signature, $signature)) {
        wp_die('Invalid SSO signature');
    }

    // Decode payload
    $data = json_decode(
        base64_decode(strtr($payload, '-_', '+/')),
        true
    );

    if (
        empty($data['email']) ||
        empty($data['exp']) ||
        time() > $data['exp']
    ) {
        wp_die('Expired or invalid token');
    }

    // Extract user data
    $email = sanitize_email($data['email']);
    $name  = sanitize_text_field($data['name'] ?? '');

    // Resolve role (ENV > JWT > fallback)
    $allowed_roles = ['administrator', 'editor', 'author', 'subscriber'];

    $env_role = sanitize_key(getenv('COONEX_DEFAULT_ROLE') ?: '');
    $jwt_role = sanitize_key($data['role'] ?? '');

    if (in_array($env_role, $allowed_roles, true)) {
        $role = $env_role;
    } elseif (in_array($jwt_role, $allowed_roles, true)) {
        $role = $jwt_role;
    } else {
        $role = 'subscriber';
    }

    // Find or create WordPress user
    $user = get_user_by('email', $email);

    if (!$user) {
        $username = sanitize_user(strstr($email, '@', true));

        $user_id = wp_create_user(
            $username,
            wp_generate_password(32),
            $email
        );

        wp_update_user([
            'ID'           => $user_id,
            'display_name' => $name,
            'role'         => $role
        ]);

        $user = get_user_by('id', $user_id);
    } else {
        // Enforce role on every SSO login
        if (!in_array($role, (array) $user->roles, true)) {
            wp_update_user([
                'ID'   => $user->ID,
                'role' => $role
            ]);
        }
    }

    /**
     * =====================================================
     * AUTHENTICATE USER (FULL WORDPRESS SESSION)
     * =====================================================
     */
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true, is_ssl());

    // 🔑 Critical fix: regenerate session tokens
    if (class_exists('WP_Session_Tokens')) {
        $manager = WP_Session_Tokens::get_instance($user->ID);
        $manager->destroy_all();
        $manager->create(time() + DAY_IN_SECONDS);
    }

    do_action('wp_login', $user->user_login, $user);

    // Redirect to admin dashboard
    wp_safe_redirect(admin_url());
    exit;
}

/**
 * Run SSO only on wp-login.php
 */
add_action('login_init', 'coonex_handle_sso');
