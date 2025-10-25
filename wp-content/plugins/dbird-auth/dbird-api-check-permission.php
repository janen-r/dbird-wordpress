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

  // Example: decide based on $from and dummy rules
  if ($from === 'chrome_extension_teams') {
    // Check if user purchased the Teams product (mocked)
    $hasPermission = true; // TODO: replace with real check
  } elseif ($from === 'chrome_extension_zoom') {
    $hasPermission = false; // not purchased
  }

  return [
    'email' => $email,
    'hasPermission' => $hasPermission,
    "upgrade_url" => home_url('/premium-dashboard/')
  ];
}
