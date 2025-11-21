<?php
/**
 * Direct WooCommerce Payment Integration (No Cart/Checkout)
 * REPLACE: includes/payment-handler.php
 */

if (!defined('ABSPATH')) exit;

/**
 * Initialize WooCommerce Payment Gateway Support
 */
add_action('init', 'cip_init_woo_payment');

function cip_init_woo_payment() {
    if (!class_exists('WooCommerce')) {
        return;
    }
    
    // Make sure products exist
    cip_ensure_products_exist();
}

/**
 * Ensure WooCommerce products exist for each plan
 */
function cip_ensure_products_exist() {
    if (!class_exists('WooCommerce')) {
        return;
    }
    
    $pricing_plans = get_option('cip_pricing_plans', []);
    
    foreach ($pricing_plans as $index => $plan) {
        $product_id = get_option('cip_woo_product_' . $index);
        
        // Check if product exists
        if (!$product_id || get_post_status($product_id) !== 'publish') {
            // Create product
            $product = new WC_Product_Simple();
            $product->set_name('CleanIndex ' . $plan['name'] . ' Plan');
            $product->set_regular_price($plan['price']);
            $product->set_description('ESG Certification - ' . $plan['name'] . ' Plan');
            $product->set_virtual(true);
            $product->set_sold_individually(true);
            $product->set_catalog_visibility('hidden');
            
            // Save meta
            $product->update_meta_data('_cip_plan_index', $index);
            $product->update_meta_data('_cip_plan_name', $plan['name']);
            
            $product_id = $product->save();
            update_option('cip_woo_product_' . $index, $product_id);
        }
    }
}

/**
 * AJAX: Create Order and Get Payment Form
 */
add_action('wp_ajax_cip_create_payment_order', 'cip_ajax_create_payment_order');

function cip_ajax_create_payment_order() {
    check_ajax_referer('cip_payment_direct', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Please login first']);
        return;
    }
    
    if (!class_exists('WooCommerce')) {
        wp_send_json_error(['message' => 'WooCommerce is not installed']);
        return;
    }
    
    $plan_index = intval($_POST['plan_index']);
    $payment_method = sanitize_text_field($_POST['payment_method']);
    
    // Get product
    $product_id = get_option('cip_woo_product_' . $plan_index);
    
    if (!$product_id) {
        wp_send_json_error(['message' => 'Product not found']);
        return;
    }
    
    $product = wc_get_product($product_id);
    
    if (!$product) {
        wp_send_json_error(['message' => 'Invalid product']);
        return;
    }
    
    // Get current user
    $user = wp_get_current_user();
    
    // Get registration data
    global $wpdb;
    $table = $wpdb->prefix . 'company_registrations';
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE email = %s",
        $user->user_email
    ), ARRAY_A);
    
    if (!$registration) {
        wp_send_json_error(['message' => 'Registration not found']);
        return;
    }
    
    try {
        // Create order programmatically
        $order = wc_create_order([
            'customer_id' => $user->ID,
            'customer_note' => 'CleanIndex Subscription',
            'created_via' => 'cleanindex'
        ]);
        
        if (is_wp_error($order)) {
            throw new Exception($order->get_error_message());
        }
        
        // Add product to order
        $order->add_product($product, 1);
        
        // Set billing details
        $order->set_billing_first_name($registration['employee_name']);
        $order->set_billing_email($registration['email']);
        $order->set_billing_company($registration['company_name']);
        $order->set_billing_country($registration['country']);
        
        // Calculate totals
        $order->calculate_totals();
        
        // Set payment method
        $order->set_payment_method($payment_method);
        
        // Add order meta
        $order->update_meta_data('_cip_plan_index', $plan_index);
        $order->update_meta_data('_cip_user_id', $user->ID);
        $order->update_meta_data('_cip_registration_id', $registration['id']);
        
        $order->save();
        
        // Get payment gateway
        $gateways = WC()->payment_gateways->get_available_payment_gateways();
        
        if (!isset($gateways[$payment_method])) {
            throw new Exception('Payment method not available');
        }
        
        $gateway = $gateways[$payment_method];
        
        // Process payment
        $result = $gateway->process_payment($order->get_id());
        
        if (isset($result['result']) && $result['result'] === 'success') {
            wp_send_json_success([
                'redirect' => $result['redirect'],
                'order_id' => $order->get_id()
            ]);
        } else {
            throw new Exception('Payment processing failed');
        }
        
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Handle Order Completion
 */
add_action('woocommerce_order_status_completed', 'cip_handle_woo_order_completed');
add_action('woocommerce_payment_complete', 'cip_handle_woo_order_completed');

function cip_handle_woo_order_completed($order_id) {
    $order = wc_get_order($order_id);
    
    if (!$order) {
        return;
    }
    
    // Check if it's a CleanIndex order
    $plan_index = $order->get_meta('_cip_plan_index');
    $user_id = $order->get_meta('_cip_user_id');
    $registration_id = $order->get_meta('_cip_registration_id');
    
    if ($plan_index === '' || !$user_id) {
        return;
    }
    
    // Check if already processed
    if ($order->get_meta('_cip_subscription_activated')) {
        return;
    }
    
    // Get plan details
    $pricing_plans = get_option('cip_pricing_plans', []);
    
    if (!isset($pricing_plans[$plan_index])) {
        return;
    }
    
    $plan = $pricing_plans[$plan_index];
    
    // Activate subscription
    $validity_years = get_option('cip_cert_validity_years', 1);
    $end_date = date('Y-m-d H:i:s', strtotime("+{$validity_years} year"));
    
    update_user_meta($user_id, 'cip_subscription_status', 'active');
    update_user_meta($user_id, 'cip_subscription_plan', $plan['name']);
    update_user_meta($user_id, 'cip_subscription_start', current_time('mysql'));
    update_user_meta($user_id, 'cip_subscription_end', $end_date);
    update_user_meta($user_id, 'cip_woo_order_id', $order_id);
    
    // Create subscription record
    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'cip_subscriptions',
        [
            'user_id' => $user_id,
            'company_id' => $registration_id,
            'plan_name' => $plan['name'],
            'plan_price' => $plan['price'],
            'currency' => $plan['currency'],
            'status' => 'active',
            'payment_method' => $order->get_payment_method(),
            'transaction_id' => $order->get_transaction_id(),
            'end_date' => $end_date
        ]
    );
    
    // Mark order as processed
    $order->update_meta_data('_cip_subscription_activated', true);
    $order->save();
    
    // Send notification
    if (function_exists('cip_create_notification')) {
        cip_create_notification(
            $user_id,
            'Subscription Activated',
            'Your ' . $plan['name'] . ' subscription is now active!',
            'success',
            home_url('/cleanindex/dashboard')
        );
    }
    
    // Auto-generate certificate if assessment complete
    if ($registration_id && class_exists('CIP_PDF_Generator')) {
        $table_assessments = $wpdb->prefix . 'company_assessments';
        $assessment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_assessments WHERE user_id = %d AND progress >= 5",
            $registration_id
        ), ARRAY_A);
        
        if ($assessment) {
            $cert_mode = get_option('cip_cert_grading_mode', 'automatic');
            
            if ($cert_mode === 'automatic') {
                $assessment_data = json_decode($assessment['assessment_json'], true);
                $grade = CIP_PDF_Generator::calculate_grade($assessment_data);
                $result = CIP_PDF_Generator::generate_certificate_pdf($registration_id, $grade);
                
                if ($result['success']) {
                    // Send certificate email
                    $user = get_userdata($user_id);
                    wp_mail(
                        $user->user_email,
                        'Your ESG Certificate - CleanIndex',
                        "Congratulations! Your ESG certificate (Grade: {$grade}) has been generated.\n\nView it at: " . home_url('/cleanindex/certificate')
                    );
                    
                    // Create notification
                    if (function_exists('cip_create_notification')) {
                        cip_create_notification(
                            $user_id,
                            'Certificate Generated',
                            'Your ' . $grade . ' certificate is ready!',
                            'success',
                            home_url('/cleanindex/certificate')
                        );
                    }
                }
            }
        }
    }
}

/**
 * Get Available Payment Methods
 */
function cip_get_available_payment_methods() {
    if (!class_exists('WooCommerce')) {
        return [];
    }
    
    $gateways = WC()->payment_gateways->get_available_payment_gateways();
    $methods = [];
    
    foreach ($gateways as $gateway_id => $gateway) {
        if ($gateway->is_available()) {
            $methods[] = [
                'id' => $gateway_id,
                'title' => $gateway->get_title(),
                'description' => $gateway->get_description(),
                'icon' => $gateway->get_icon()
            ];
        }
    }
    
    return $methods;
}

/**
 * AJAX: Get Payment Methods
 */
add_action('wp_ajax_cip_get_payment_methods', 'cip_ajax_get_payment_methods');

function cip_ajax_get_payment_methods() {
    check_ajax_referer('cip_payment_direct', 'nonce');
    
    $methods = cip_get_available_payment_methods();
    
    if (empty($methods)) {
        wp_send_json_error(['message' => 'No payment methods available']);
    } else {
        wp_send_json_success(['methods' => $methods]);
    }
}