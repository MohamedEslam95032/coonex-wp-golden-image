<?php
/**
 * Plugin Name: Coonex JWT SSO
 * Description: Secure JWT-based SSO for Coonex (Role controlled via ENV)
 */

/**
 * Main SSO handler
 */
function coonex_handle_sso() {

    // --------------------------------------------
    // 1) Enforce SSO-only login
    // --------------------------------------------
    if (!isset($_GET['token'])) {
        wp_die('Login via Coonex only');
    }

    $jwt    = trim($_GET['token']);
    $secret = getenv('COONEX_SSO_SECRET');

    if (!$secret) {
        wp_die('SSO secret not configured');
    }

    // --------------------------------------------
    // 2) Validate JWT structure
    // --------------------------------------------
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        wp_die('Invalid token structure');
    }

    [$header, $payload, $signature] = $parts;

    // --------------------------------------------
    // 3) Verify JWT signature (HS256)
    // --------------------------------------------
    $expected_signature = rtrim(strtr(
        base64_encode(hash_hmac(
            'sha256',
            "$header.$payload",
            $secret,
            true
        )),
        '+/',
        '-_'
    ), '=');

    if (!hash_equals($expected_signature, $signature)) {
        wp_die('Invalid SSO signature');
    }

    // --------------------------------------------
    // 4) Decode payload
    // --------------------------------------------
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

    // --------------------------------------------
    // 5) Extract user data
    // --------------------------------------------
    $email = sanitize_email($data['email']);
    $name  = sanitize_text_field($data['name'] ?? '');

    // --------------------------------------------
    // 6) Resolve role (ENV > JWT > fallback)
    // --------------------------------------------
    $allowed_roles = ['administrator', 'editor', 'author', 'subscriber'];

    $env_role = sanitize_key(getenv('COONEX_DEFAULT_ROLE') ?: '');
    $jwt_role = sanitize_key($data['role'] ?? '');

    if (in_array($env_role, $allowed_roles, true)) {
        $role = $env_role;                // ✅ Coolify ENV wins
    } elseif (in_array($jwt_role, $allowed_roles, true)) {
        $role = $jwt_role;                // fallback to JWT
    } else {
        $role = 'subscriber';             // safe default
    }

    // --------------------------------------------
    // 7) Find or create WordPress user
    // --------------------------------------------
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
        // Enforce role from ENV/JWT every login
        if (!in_array($role, (array) $user->roles, true)) {
            wp_update_user([
                'ID'   => $user->ID,
                'role' => $role
            ]);
        }
    }

    // --------------------------------------------
    // 8) Authenticate user (no password)
    // --------------------------------------------
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true);
    do_action('wp_login', $user->user_login, $user);

    // --------------------------------------------
    // 9) Redirect to admin
    // --------------------------------------------
    wp_safe_redirect(admin_url());
    exit;
}

/**
 * Run SSO only on wp-login.php
 */
add_action('login_init', 'coonex_handle_sso');
