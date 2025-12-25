<?php
/**
 * Plugin Name: Coonex JWT SSO
 * Description: JWT-based Single Sign-On for Coonex (SSO only – stable & WordPress-native)
 * Version: 2.0.0
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

    // 1) اشتغل فقط لو token موجود
    if (!isset($_GET['token'])) {
        return;
    }

    // 2) لو المستخدم logged in خلاص
    if (is_user_logged_in()) {
        return;
    }

    $jwt    = trim($_GET['token']);
    $secret = getenv('COONEX_SSO_SECRET');

    if (!$secret) {
        wp_die('SSO secret not configured');
    }

    // 3) Validate JWT structure
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        wp_die('Invalid token structure');
    }

    [$header, $payload, $signature] = $parts;

    // 4) Verify signature (HS256)
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

    // 5) Decode payload
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

    // 6) User data
    $email = sanitize_email($data['email']);
    $name  = sanitize_text_field($data['name'] ?? '');

    if (!$email) {
        wp_die('Invalid email in token');
    }

    // 7) Resolve role
    $allowed_roles = ['administrator', 'editor', 'author', 'subscriber'];
    $jwt_role = sanitize_key($data['role'] ?? '');
    $role = in_array($jwt_role, $allowed_roles, true) ? $jwt_role : 'subscriber';

    // 8) Find or create user
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
    }

    /**
     * =====================================================
     * 9) LOGIN THE WORDPRESS WAY (CRITICAL FIX)
     * =====================================================
     */
    $signon = wp_signon([
        'user_login'    => $user->user_login,
        'user_password' => null,
        'remember'      => true,
    ], is_ssl());

    if (is_wp_error($signon)) {
        wp_die('SSO login failed');
    }

    // 10) Redirect
    wp_safe_redirect(admin_url());
    exit;
}

/**
 * =====================================================
 * RUN SSO SAFELY
 * =====================================================
 */
add_action('login_init', 'coonex_handle_sso');
