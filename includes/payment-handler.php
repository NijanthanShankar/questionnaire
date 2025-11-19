<?php
/**
 * CleanIndex Portal - WooCommerce Payment Integration
 * Handles order creation and payment processing via WooCommerce
 */

if (!defined('ABSPATH')) exit;

/**
 * Create WooCommerce products for pricing plans (run once on activation)
 */
function cip_create_woocommerce_products() {
    if (!class_exists('WooCommerce')) {
        return false;
    }
    
    $pricing_plans = get_option('cip_pricing_plans', []);
    
    foreach ($pricing_plans as $index => $plan) {
        // Check if product already exists
        $existing_product_id = get_option('cip_woo_product_' . $index);
        
        if ($existing_product_id && get_post_status($existing_product_id) === 'publish') {
            continue; // Product already exists
        }
        
        // Create WooCommerce product
        $product = new WC_Product_Simple();
        $product->set_name($plan['name'] . ' - ESG Certification');
        $product->set_regular_price($plan['price']);
        $product->set_description('CleanIndex ' . $plan['name'] . ' Plan - ESG Certification');
        
        // Set features as short description
        $features = is_array($plan['features']) 
            ? implode("\n", $plan['features']) 
            : $plan['features'];
        $product->set_short_description($features);
        
        $product->set_catalog_visibility('hidden'); // Hide from shop
        $product->set_virtual(true); // Digital product
        $product->set_sold_individually(true); // Only buy once
        $product->set_manage_stock(false);
        
        // Add custom meta
        $product->update_meta_data('_cip_plan_index', $index);
        $product->update_meta_data('_cip_plan_name', $plan['name']);
        
        $product_id = $product->save();
        
        // Store product ID
        update_option('cip_woo_product_' . $index, $product_id);
    }
    
    return true;
}

/**
 * Update WooCommerce products when pricing plans change
 */
add_action('update_option_cip_pricing_plans', 'cip_update_woocommerce_products', 10, 2);

function cip_update_woocommerce_products($old_value, $new_value) {
    if (!class_exists('WooCommerce')) {
        return;
    }
    
    // Delete old products
    for ($i = 0; $i < 10; $i++) {
        $product_id = get_option('cip_woo_product_' . $i);
        if ($product_id) {
            wp_delete_post($product_id, true);
            delete_option('cip_woo_product_' . $i);
        }
    }
    
    // Create new products
    cip_create_woocommerce_products();
}

/**
 * Add to cart and redirect to checkout
 */
function cip_add_to_cart_and_checkout($plan_index) {
    if (!class_exists('WooCommerce')) {
        return new WP_Error('woocommerce_missing', 'WooCommerce is not installed');
    }
    
    // Clear cart first
    WC()->cart->empty_cart();
    
    // Get product ID
    $product_id = get_option('cip_woo_product_' . $plan_index);
    
    if (!$product_id) {
        return new WP_Error('product_not_found', 'Product not found');
    }
    
    // Add to cart
    WC()->cart->add_to_cart($product_id, 1);
    
    // Store selected plan in user meta
    if (is_user_logged_in()) {
        update_user_meta(get_current_user_id(), 'cip_selected_plan_index', $plan_index);
    }
    
    // Return checkout URL
    return wc_get_checkout_url();
}

/**
 * Handle order completion - Generate certificate
 */
add_action('woocommerce_order_status_completed', 'cip_woocommerce_order_completed');

function cip_woocommerce_order_completed($order_id) {
    $order = wc_get_order($order_id);
    
    if (!$order) {
        return;
    }
    
    // Get user ID
    $user_id = $order->get_user_id();
    
    if (!$user_id) {
        return;
    }
    
    // Check if this is a CleanIndex product
    $is_cleanindex_order = false;
    $plan_index = null;
    $plan_name = '';
    
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if ($product) {
            $plan_index = $product->get_meta('_cip_plan_index');
            if ($plan_index !== '') {
                $is_cleanindex_order = true;
                $plan_name = $product->get_meta('_cip_plan_name');
                break;
            }
        }
    }
    
    if (!$is_cleanindex_order) {
        return; // Not a CleanIndex order
    }
    
    // Update user subscription status
    update_user_meta($user_id, 'cip_subscription_status', 'active');
    update_user_meta($user_id, 'cip_subscription_plan', $plan_name);
    update_user_meta($user_id, 'cip_subscription_start', current_time('mysql'));
    update_user_meta($user_id, 'cip_woo_order_id', $order_id);
    
    // Get user registration
    $user = get_userdata($user_id);
    global $wpdb;
    $table_registrations = $wpdb->prefix . 'company_registrations';
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_registrations WHERE email = %s",
        $user->user_email
    ), ARRAY_A);
    
    if (!$registration) {
        return;
    }
    
    // Generate certificate
    $cert_mode = get_option('cip_cert_grading_mode', 'automatic');
    
    if ($cert_mode === 'automatic') {
        // Get assessment data
        $table_assessments = $wpdb->prefix . 'company_assessments';
        $assessment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_assessments WHERE user_id = %d",
            $registration['id']
        ), ARRAY_A);
        
        if ($assessment) {
            require_once CIP_PLUGIN_DIR . 'includes/pdf-generator.php';
            
            $assessment_data = json_decode($assessment['assessment_json'], true);
            $grade = CIP_PDF_Generator::calculate_grade($assessment_data);
            
            // Generate certificate
            $result = CIP_PDF_Generator::generate_certificate_pdf($registration['id'], $grade);
            
            if ($result['success']) {
                // Send email with certificate
                cip_send_certificate_email($user->user_email, $registration['company_name'], $grade, $result['url']);
                
                // Add order note
                $order->add_order_note('ESG Certificate generated automatically. Grade: ' . $grade);
            }
        }
    } else {
        // Manual mode - notify admin
        cip_notify_admin(
            'New Certificate Ready for Review',
            'Order #' . $order_id . ' completed. Please review and generate certificate manually from admin dashboard.'
        );
        
        $order->add_order_note('Awaiting manual certificate generation by admin.');
    }
}

/**
 * Add custom thank you message after order
 */
add_filter('woocommerce_thankyou_order_received_text', 'cip_custom_thankyou_message', 20, 2);

function cip_custom_thankyou_message($thank_you_message, $order) {
    if (!$order) {
        return $thank_you_message;
    }
    
    // Check if this is a CleanIndex order
    $is_cleanindex_order = false;
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if ($product && $product->get_meta('_cip_plan_index') !== '') {
            $is_cleanindex_order = true;
            break;
        }
    }
    
    if (!$is_cleanindex_order) {
        return $thank_you_message;
    }
    
    // Custom message for CleanIndex orders
    $message = '<div style="background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(3, 169, 244, 0.1)); padding: 2rem; border-radius: 12px; margin: 2rem 0;">';
    $message .= '<h2 style="color: #4CAF50; margin-top: 0;">🎉 Welcome to CleanIndex!</h2>';
    $message .= '<p style="font-size: 1.1rem;">Thank you for your subscription. Your ESG certificate is being generated.</p>';
    $message .= '<div style="margin: 1.5rem 0;">';
    $message .= '<strong>What happens next?</strong>';
    $message .= '<ol style="line-height: 2;">';
    $message .= '<li>Your certificate will be generated within a few minutes</li>';
    $message .= '<li>You\'ll receive an email with download instructions</li>';
    $message .= '<li>Access your certificate from your dashboard</li>';
    $message .= '<li>Download and display your ESG badge</li>';
    $message .= '</ol>';
    $message .= '</div>';
    $message .= '<a href="' . home_url('/cleanindex/dashboard') . '" class="button" style="background: #4CAF50; color: white; padding: 1rem 2rem; text-decoration: none; border-radius: 8px; display: inline-block;">Go to Dashboard</a>';
    $message .= '</div>';
    
    return $message;
}

/**
 * Send certificate email
 */
function cip_send_certificate_email($email, $company_name, $grade, $certificate_url) {
    $subject = 'Your ESG Certificate is Ready! - CleanIndex';
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 12px; }
            h1 { color: #4CAF50; }
            .grade-badge { background: #4CAF50; color: white; padding: 1rem 2rem; border-radius: 50px; font-size: 2rem; font-weight: bold; display: inline-block; }
            .button { background: #4CAF50; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🏆 Your ESG Certificate is Ready!</h1>
            <p>Dear ' . esc_html($company_name) . ',</p>
            <p>Congratulations! Your ESG certification has been completed.</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <div class="grade-badge">' . esc_html($grade) . '</div>
            </div>
            
            <p>You have achieved the <strong>' . esc_html($grade) . '</strong> certification level.</p>
            
            <div style="text-align: center;">
                <a href="' . esc_url($certificate_url) . '" class="button">Download Certificate</a>
            </div>
            
            <p>You can also:</p>
            <ul>
                <li>View your certificate in your dashboard</li>
                <li>Download the ESG badge for your website</li>
                <li>Share your achievement on social media</li>
            </ul>
            
            <p>Thank you for choosing CleanIndex!</p>
            
            <p style="margin-top: 40px; color: #666; font-size: 12px;">
                © ' . date('Y') . ' CleanIndex. All rights reserved.
            </p>
        </div>
    </body>
    </html>
    ';
    
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: CleanIndex <noreply@cleanindex.com>'
    ];
    
    wp_mail($email, $subject, $message, $headers);
}

/**
 * Prevent duplicate purchases
 */
add_filter('woocommerce_is_purchasable', 'cip_prevent_duplicate_purchase', 10, 2);

function cip_prevent_duplicate_purchase($purchasable, $product) {
    // Check if this is a CleanIndex product
    $plan_index = $product->get_meta('_cip_plan_index');
    
    if ($plan_index === '') {
        return $purchasable; // Not a CleanIndex product
    }
    
    // Check if user already has an active subscription
    if (is_user_logged_in()) {
        $subscription_status = get_user_meta(get_current_user_id(), 'cip_subscription_status', true);
        
        if ($subscription_status === 'active') {
            return false; // Already subscribed
        }
    }
    
    return $purchasable;
}

/**
 * Add custom message on product page
 */
add_action('woocommerce_single_product_summary', 'cip_product_custom_message', 25);

function cip_product_custom_message() {
    global $product;
    
    // Check if this is a CleanIndex product
    $plan_index = $product->get_meta('_cip_plan_index');
    
    if ($plan_index === '') {
        return; // Not a CleanIndex product
    }
    
    echo '<div style="background: #f0f8ff; padding: 15px; border-radius: 8px; margin: 20px 0;">';
    echo '<strong>📋 Requirements:</strong><br>';
    echo 'You must complete the ESG assessment before purchasing a subscription plan.';
    echo '</div>';
}

/**
 * Create products on plugin activation
 */
register_activation_hook(CIP_PLUGIN_FILE, 'cip_create_woocommerce_products');

/**
 * AJAX: Get checkout URL
 */
add_action('wp_ajax_cip_get_checkout_url', 'cip_ajax_get_checkout_url');

function cip_ajax_get_checkout_url() {
    check_ajax_referer('cip_checkout', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Please login first']);
        return;
    }
    
    $plan_index = intval($_POST['plan_index']);
    
    $checkout_url = cip_add_to_cart_and_checkout($plan_index);
    
    if (is_wp_error($checkout_url)) {
        wp_send_json_error(['message' => $checkout_url->get_error_message()]);
    } else {
        wp_send_json_success(['checkout_url' => $checkout_url]);
    }
}