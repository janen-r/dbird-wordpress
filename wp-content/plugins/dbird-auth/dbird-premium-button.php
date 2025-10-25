<?php
/**
 * DBIRD Premium Button Shortcode
 * Generates a dynamic WooCommerce button that auto-disables for existing subscribers.
 */

if ( ! defined( 'ABSPATH' ) ) exit; // no direct access

add_shortcode('dbird_premium_button', function($atts) {
    $atts = shortcode_atts([
        'id' => 0,
        'text' => 'Buy Premium Subscription',
        'subscribed_text' => 'You already have Premium Access'
    ], $atts);

    $product_id = intval($atts['id']);
    $user_has_active = false;

    if (is_user_logged_in() && function_exists('wcs_get_users_subscriptions')) {
        $subs = wcs_get_users_subscriptions(get_current_user_id());
        foreach ($subs as $sub) {
            if ($sub->get_status() === 'active') {
                foreach ($sub->get_items() as $item) {
                    if ($item->get_product_id() === $product_id) {
                        $user_has_active = true;
                        break 2;
                    }
                }
            }
        }
    }

    if ($user_has_active) {
        $button_html = '
        <div class="wp-block-button">
            <a class="wp-block-button__link has-background wp-element-button disabled"
               style="border-radius:25px;background-color:#009966;">
               ' . esc_html($atts['subscribed_text']) . '
            </a>
        </div>';
    } else {
        $checkout_url = esc_url( wc_get_checkout_url() . '?add-to-cart=' . $product_id );
        $button_html = '
        <div class="wp-block-button">
            <a class="wp-block-button__link has-background wp-element-button"
               href="' . $checkout_url . '"
               style="border-radius:25px;background-color:#009966;">
               ' . esc_html($atts['text']) . '
            </a>
        </div>';
    }

    return $button_html;
});
