<?php
/**
 * Plugin Name: DBIRD Auth
 */

// === Include API files ===
require_once plugin_dir_path(__FILE__) . 'dbird-api-check-permission.php';

// === Capture extension ID once ===
add_action('init', function() {
  if (!session_id()) session_start();

  // Capture ?ext_id=XYZ from any request
  if (isset($_GET['ext_id'])) {
    $_SESSION['dbird_ext_id'] = sanitize_text_field($_GET['ext_id']);
  }
});

// // === After successful normal WP login ===
add_filter('login_redirect', function($redirect_to, $requested_redirect_to, $user) {
  if (!session_id()) session_start();

  $ext_id = $_SESSION['dbird_ext_id'] ?? '';

  if ($ext_id && is_a($user, 'WP_User')) {
    // Generate token
    $token = wp_create_nonce('dbird_login_' . $user->user_email);
    update_user_meta($user->ID, '_dbird_token', $token);

    // Clear session so it doesn’t trigger again
    unset($_SESSION['dbird_ext_id']);

    // Redirect to extension welcome page
    return "chrome-extension://{$ext_id}/welcome.html?auth_token={$token}";
  }

  return $redirect_to;
}, 10, 3);

/**
 * Handle both cases:
 * 1️⃣ After Google login (via Nextend)
 * 2️⃣ Already logged-in user visiting /login-to-dbird
 */

// // === Case 1: After successful Google login (Nextend) ===
add_action('nsl_login', function($user_id, $provider) {
  if (!session_id()) session_start();
  if ($provider !== 'google') return;

  $ext_id = $_SESSION['dbird_ext_id'] ?? '';
  if (!$ext_id) return;

  $user = get_user_by('id', $user_id);
  if (!$user) return;

  // Generate token
  $token = wp_create_nonce('dbird_login_' . $user->user_email);
  update_user_meta($user->ID, '_dbird_token', $token);

  // Clear session after use
  unset($_SESSION['dbird_ext_id']);

  // Redirect target (your product dashboard)
  $redirect_url = urlencode(home_url('/premium-dashboard/'));
  $target = "chrome-extension://{$ext_id}/welcome.html?auth_token={$token}&redirect_url={$redirect_url}";

  wp_redirect($target);
  echo "<script>location.href='" . esc_js($target) . "';</script>";
  exit;
}, 10, 2);

// // === Case 2: Already logged-in user visiting /login-to-dbird ===
add_action('template_redirect', function() {
  if (!session_id()) session_start();

  // Only act on the login bridge page
  if (!is_page('login-to-dbird')) return;

  $ext_id = $_SESSION['dbird_ext_id'] ?? ($_GET['ext_id'] ?? '');
  if (!$ext_id) return;

  $user = wp_get_current_user();
  if (!$user || !$user->exists()) return;

  // Generate token again for this logged-in user
  $token = wp_create_nonce('dbird_login_' . $user->user_email);
  update_user_meta($user->ID, '_dbird_token', $token);

  unset($_SESSION['dbird_ext_id']);

  $redirect_url = urlencode(home_url('/premium-dashboard/'));
  $target = "chrome-extension://{$ext_id}/welcome.html?auth_token={$token}&redirect_url={$redirect_url}";

  // Try header redirect first, JS fallback for browsers blocking it
  wp_redirect($target);
  echo "<script>location.href='" . esc_js($target) . "';</script>";
  exit;
});