<?php
/**
 * Plugin Name: DBIRD Auth
 * Description: Handles login, subscription redirects, and other DBIRD-specific logic.
 * Version: 1.0
 * Author: HappyCoders.in
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Usecase: API Permission Check from DBIRD Extension
require_once plugin_dir_path(__FILE__) . 'dbird-api-check-permission.php';

// Usecase: Premium Subscription Button Shortcode
require_once plugin_dir_path(__FILE__) . 'dbird-premium-button.php';

// // Usecase: Skipping Cart Page, redirecting directly to Checkout
// add_filter('woocommerce_add_to_cart_redirect', function() {
//     return wc_get_checkout_url();
// });

// Usecase: Redirect users away from Cart/Shop to My Account/Homepage
add_action('template_redirect', function() {

    // Exit early if not cart or shop page
    if ( ! is_cart() && ! is_shop() ) {
        return;
    }

    // If logged in → go to My Account
    if ( is_user_logged_in() ) {
        wp_safe_redirect( home_url('/my-account/') );
        exit;
    }

    // If guest (not logged in) → go to homepage
    wp_safe_redirect( home_url('/') );
    exit;
});

// Usecase: After login, redirect back to extension with token
add_action('template_redirect', function() {

    // 🛑 Skip for admin dashboard or AJAX calls
    if ( is_admin() || defined('DOING_AJAX') ) return;

    // ✅ Only run on the WooCommerce My Account page
    if ( ! function_exists('is_account_page') || ! is_account_page() ) return;

    if ( ! session_id() ) session_start();

    // === Capture params from request ===
    $ext_redirect = sanitize_text_field($_GET['extension_redirect'] ?? '');

    // Store redirect temporarily (only if it’s a valid Chrome extension URL)
    if (!empty($ext_redirect) && str_starts_with($ext_redirect, 'chrome-extension://')) {
        $_SESSION['dbird_redirect'] = $ext_redirect;
    } else { 
      // (so normal visits to /my-account/ won't cause redirect)
      return;
    }

    // === Proceed only if user is logged in ===
    $user = wp_get_current_user();
    if ( ! $user || ! $user->exists() ) return;

    // Get stored redirect or default
    $target = $_SESSION['dbird_redirect'] ?? '';
    if ( empty($target) ) {
        $target = home_url('/dual-subtitles-for-teams-chrome-extension/');
    }

    // === Create token for extension auth ===
    $token = wp_create_nonce('dbird_login_' . $user->user_email);
    update_user_meta($user->ID, '_dbird_token', $token);

    // Build redirect URL
    $redirect_url = urlencode( home_url('/dual-subtitles-for-teams-chrome-extension/') );
    $final_target = $target . '?auth_token=' . $token . '&redirect_url=' . $redirect_url;

    // Clean up session data
    unset($_SESSION['dbird_redirect'], $_SESSION['dbird_ext_id']);

    // 🚀 Redirect (with JS fallback)
    wp_redirect($final_target);
    echo "<script>location.href='" . esc_js($final_target) . "';</script>";
    exit;
});

// === Capture extension ID once ===
// add_action('init', function() {
//   if (!session_id()) session_start();

//   // Capture ?ext_id=XYZ from any request
//   if (isset($_GET['ext_id'])) {
//     $_SESSION['dbird_ext_id'] = sanitize_text_field($_GET['ext_id']);
//   }
// });

// // === After successful normal WP login ===
// add_filter('login_redirect', function($redirect_to, $requested_redirect_to, $user) {
//   if (!session_id()) session_start();

//   $ext_id = $_SESSION['dbird_ext_id'] ?? '';

//   if ($ext_id && is_a($user, 'WP_User')) {
//     // Generate token
//     $token = wp_create_nonce('dbird_login_' . $user->user_email);
//     update_user_meta($user->ID, '_dbird_token', $token);

//     // Clear session so it doesn’t trigger again
//     unset($_SESSION['dbird_ext_id']);

//     // Redirect to extension welcome page
//     return "chrome-extension://{$ext_id}/welcome.html?auth_token={$token}";
//   }

//   return $redirect_to;
// }, 10, 3);

/**
 * Handle both cases:
 * 1️⃣ After Google login (via Nextend)
 * 2️⃣ Already logged-in user visiting /login-to-dbird
 */

// // === Case 1: After successful Google login (Nextend) ===
// add_action('nsl_login', function($user_id, $provider) {
//   if (!session_id()) session_start();
//   if ($provider !== 'google') return;

//   $ext_id = $_SESSION['dbird_ext_id'] ?? '';
//   if (!$ext_id) return;

//   $user = get_user_by('id', $user_id);
//   if (!$user) return;

//   // Generate token
//   $token = wp_create_nonce('dbird_login_' . $user->user_email);
//   update_user_meta($user->ID, '_dbird_token', $token);

//   // Clear session after use
//   unset($_SESSION['dbird_ext_id']);

//   // Redirect target (your product dashboard)
//   $redirect_url = urlencode(home_url('/premium-dashboard/'));
//   $target = "chrome-extension://{$ext_id}/welcome.html?auth_token={$token}&redirect_url={$redirect_url}";

//   wp_redirect($target);
//   echo "<script>location.href='" . esc_js($target) . "';</script>";
//   exit;
// }, 10, 2);

// // === Case 2: Already logged-in user visiting /login-to-dbird ===
// add_action('template_redirect', function() {
//   if (!session_id()) session_start();

//   // Only act on the login bridge page
//   if (!is_page('login-to-dbird')) return;

//   $ext_id = $_SESSION['dbird_ext_id'] ?? ($_GET['ext_id'] ?? '');
//   if (!$ext_id) return;

//   $user = wp_get_current_user();
//   if (!$user || !$user->exists()) return;

//   // Generate token again for this logged-in user
//   $token = wp_create_nonce('dbird_login_' . $user->user_email);
//   update_user_meta($user->ID, '_dbird_token', $token);

//   unset($_SESSION['dbird_ext_id']);

//   $redirect_url = urlencode(home_url('/premium-dashboard/'));
//   $target = "chrome-extension://{$ext_id}/welcome.html?auth_token={$token}&redirect_url={$redirect_url}";

//   // Try header redirect first, JS fallback for browsers blocking it
//   wp_redirect($target);
//   echo "<script>location.href='" . esc_js($target) . "';</script>";
//   exit;
// });