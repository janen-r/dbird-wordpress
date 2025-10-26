<?php

// === Force CORS for DBIRD REST API ===
add_action('rest_api_init', function () {
  add_filter('rest_pre_serve_request', function ($value) {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Authorization, Content-Type");
    return $value;
  });
});

add_action('init', function() {
  if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Authorization, Content-Type");
    exit(0);
  }
});

// === Register the API route ===
add_action('rest_api_init', function () {
  register_rest_route('dbird/v1', '/check-permission', [
    'methods'             => 'GET',
    'callback'            => 'dbird_check_permission',
    'permission_callback' => '__return_true',
  ]);
});

/**
 * Check if a user has an active WooCommerce subscription for a given product ID.
 *
 * @param int $user_id
 * @param int $product_id
 * @return bool
 */
function dbird_user_has_active_subscription($user_id, $product_id) {
    if ( ! class_exists('WC_Subscriptions') ) return false;

    $subscriptions = wcs_get_users_subscriptions($user_id);
    if ( empty($subscriptions) ) return false;

    foreach ($subscriptions as $subscription) {
        if ( ! $subscription->has_status('active') ) continue;

        foreach ($subscription->get_items() as $item) {
            $item_product_id = $item->get_product_id();
            if ( (int)$item_product_id === (int)$product_id ) {
                return true;
            }
        }
    }

    return false;
}

function dbird_check_permission(WP_REST_Request $request) {
  $token = sanitize_text_field($request->get_param('token'));
  $from  = sanitize_text_field($request->get_param('from')); // e.g. chrome_extension_teams

  if (!$token || !$from) {
    return [
      'email' => null,
      'hasPermission' => false,
      'error' => 'Missing token or source.',
    ];
  }

  // Find user by token
  $user_query = new WP_User_Query([
    'meta_key'   => '_dbird_token',
    'meta_value' => $token,
    'number'     => 1,
  ]);
  $users = $user_query->get_results();
  if (empty($users)) {
    return [
      'email' => null,
      'hasPermission' => false,
      'error' => 'Invalid or expired token.',
    ];
  }

  $user = $users[0];
  $email = $user->user_email;

  // === MOCK CHECK: for now, simulate product subscription check ===
  // Later we’ll check WooCommerce orders/subscriptions for real
  $hasPermission = false;
  $upgrade_url = null;
  $view_subscriptions_url = home_url('/my-account/subscriptions/');

  // TODO: Replace this mock logic with real subscription/product checks
  if ($from === 'ce_dual_teams') {
    $product_id = 62;
    $hasPermission = dbird_user_has_active_subscription($user->ID, $product_id);
    $upgrade_url = home_url('/checkout/?add-to-cart=62');
  } elseif ($from === 'ce_dual_zoom') {
    $hasPermission = false;
  }

  return [
    'email' => $email,
    'hasPermission' => $hasPermission,
    'upgrade_url' => $upgrade_url,
    'view_subscriptions_url' => $view_subscriptions_url,
  ];
}
