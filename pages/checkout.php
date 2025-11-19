<?php
/**
 * CleanIndex Portal - Checkout Redirect Handler
 * Redirects to WooCommerce checkout with selected plan
 */

if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
    wp_redirect(home_url('/cleanindex/login'));
    exit;
}

// Handle plan selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cip_checkout_nonce'])) {
    if (wp_verify_nonce($_POST['cip_checkout_nonce'], 'cip_checkout')) {
        $plan_index = intval($_POST['plan_index']);
        
        // Add to cart and get checkout URL
        $checkout_url = cip_add_to_cart_and_checkout($plan_index);
        
        if (is_wp_error($checkout_url)) {
            // Show error
            $error_message = $checkout_url->get_error_message();
        } else {
            // Redirect to WooCommerce checkout
            wp_redirect($checkout_url);
            exit;
        }
    }
}

// If we reach here, something went wrong
wp_redirect(home_url('/cleanindex/pricing'));
exit;