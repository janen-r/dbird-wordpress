<?php
/**
 * DBIRD Order Complete Handler
 * Auto-completes Razorpay orders for digital products.
 */
if ( ! defined( 'ABSPATH' ) ) exit; // no direct access

add_action('woocommerce_thankyou_razorpay', function($order_id) {

    if ( ! $order_id ) return;
    $order = wc_get_order($order_id);
    if ( ! $order ) return;

    // Only for paid Razorpay orders
    if ( $order->is_paid() ) {
        // Check if all items are virtual (digital)
        $all_virtual = true;
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( $product && ! $product->is_virtual() ) {
                $all_virtual = false;
                break;
            }
        }

        // ✅ If virtual, mark as completed
        if ( $all_virtual ) {
            $order->update_status('completed');
        }
    }
});
