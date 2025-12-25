<?php
/**
 * Plugin Name: Coonex JWT SSO
 * Description: JWT-based Single Sign-On for Coonex (SSO only – safe for wp-login.php)
 * Version: 1.2.0
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

    /**
     * 1) اشتغل فقط لو token موجود
     * مهم جدًا عشان ما نكسرش wp-login.php
     */
    if (!isset($_GET['token'])) {
        return;
    }

    /**
     * 2) لو المستخدم already logged in
     * سيب ووردبريس يكمل طبيعي
     */
    if (is_user_logged_in()) {
        return;
    }

    $jwt    = trim($_GET['token']);
    $secret = getenv('COONEX_SSO_SECRET');

    if (!$secret) {
        wp_die('SSO secret not configured');
    }

    /**
     * 3) Validate JWT structure
     */
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        wp_die('Invalid token structure');
    }

    [$header, $payload, $signature] = $parts;

    /**
     * 4) Verify signature (HS256)
     */
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

    /**
     * 5) Decode payload
     */
    $data = json_decode(
        base64_decode(strtr($payload, '-_', '+/')),
        true
    );

    if (
        empty($data['email']) ||
        empty($data['exp']) ||
        time() > (int) $data['exp']
    ) {
        wp_die('Expired or invalid token');
    }

    /**
     * 6) Extract user data
     */
    $email = sanitize_email($data['email']);
    $name  = sanitize_text_field($data['name'] ?? '');

    if (!$email) {
        wp_die('Invalid email in token');
    }

    /**
     * 7) Resolve role (ENV > JWT > fallback)
     */
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

    /**
     * 8) Find or create WordPress user
     */
    $user = get_user_by('email', $email);

    if (!$user) {
        $username = sanitize_user(strstr($email, '@', true));

        if (username_exists($username)) {
            $username .= '_' . wp_generate_password(4, false);
        }

        $user_id = wp_create_user(
            $username,
            wp_generate_password(32),
            $email
        );

        if (is_wp_error($user_id)) {
            wp_die('Failed to create user');
        }

        wp_update_user([
            'ID'           => $user_id,
            'display_name' => $name,
            'role'         => $role
        ]);

        $user = get_user_by('id', $user_id);
    } else {
        // Enforce role every SSO login
        if (!in_array($role, (array) $user->roles, true)) {
            wp_update_user([
                'ID'   => $user->ID,
                'role' => $role
            ]);
        }
    }

    /**
     * =====================================================
     * 9) AUTHENTICATE USER (FULL WORDPRESS SESSION)
     * =====================================================
     */
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true, is_ssl());

    // 🔑 Critical: regenerate session tokens (fix nonce / link expired)
    if (class_exists('WP_Session_Tokens')) {
        $manager = WP_Session_Tokens::get_instance($user->ID);
        $manager->destroy_all();
        $manager->create(time() + DAY_IN_SECONDS);
    }

    do_action('wp_login', $user->user_login, $user);

    /**
     * 10) Redirect to admin
     */
    wp_safe_redirect(admin_url());
    exit;
}

/**
 * =====================================================
 * HOOK SSO SAFELY INTO wp-login.php
 * =====================================================
 */
add_action('login_init', 'coonex_handle_sso');
