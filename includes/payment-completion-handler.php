<?php
/**
 * CleanIndex Portal - Payment Completion Handler
 * ADD THIS CODE to your existing includes/payment-handler.php file
 * OR create as a new file and include it
 */

if (!defined('ABSPATH')) exit;

// Hook into WooCommerce order completion
add_action('woocommerce_order_status_completed', 'cip_handle_payment_completion', 10, 1);
add_action('woocommerce_payment_complete', 'cip_handle_payment_completion', 10, 1);

function cip_handle_payment_completion($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    
    // Check if this is a CleanIndex subscription order
    $is_cip_order = false;
    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        // Check if product is one of our pricing plans
        $cip_products = [];
        for ($i = 0; $i < 10; $i++) {
            $prod_id = get_option('cip_woo_product_' . $i);
            if ($prod_id) $cip_products[] = intval($prod_id);
        }
        
        if (in_array($product_id, $cip_products)) {
            $is_cip_order = true;
            break;
        }
    }
    
    if (!$is_cip_order) return;
    
    // Get user
    $user_id = $order->get_user_id();
    if (!$user_id) return;
    
    global $wpdb;
    $table_reg = $wpdb->prefix . 'company_registrations';
    
    // Find registration by WordPress user ID
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_reg WHERE wordpress_user_id = %d OR email = %s",
        $user_id,
        $order->get_billing_email()
    ), ARRAY_A);
    
    if (!$registration) {
        error_log('CIP: No registration found for user ' . $user_id);
        return;
    }
    
    $company_id = $registration['id'];
    
    // Check if certificate already exists
    $table_certs = $wpdb->prefix . 'cip_certificates';
    $existing_cert = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_certs WHERE user_id = %d",
        $company_id
    ));
    
    if ($existing_cert) {
        error_log('CIP: Certificate already exists for user ' . $company_id);
        // Redirect to certificate page
        $cert_url = home_url('/cleanindex/certificate');
        update_post_meta($order_id, '_cip_certificate_url', $cert_url);
        return;
    }
    
    // Check if user is eligible
    if (function_exists('cip_is_eligible_for_certificate')) {
        $eligibility = cip_is_eligible_for_certificate($company_id);
        if (!$eligibility['eligible']) {
            error_log('CIP: User ' . $company_id . ' not eligible: ' . $eligibility['reason']);
            // Still activate subscription but no certificate yet
            cip_activate_subscription($user_id, $order);
            return;
        }
    }
    
    // Generate certificate
    if (function_exists('cip_generate_certificate_pdf')) {
        $result = cip_generate_certificate_pdf($company_id);
        
        if ($result['success']) {
            // Store certificate URL in order meta
            update_post_meta($order_id, '_cip_certificate_url', $result['url']);
            update_post_meta($order_id, '_cip_certificate_number', $result['certificate_number']);
            
            // Activate subscription
            cip_activate_subscription($user_id, $order);
            
            // Send certificate email
            cip_send_certificate_email($registration, $result);
            
            error_log('CIP: Certificate generated for user ' . $company_id . ': ' . $result['certificate_number']);
        } else {
            error_log('CIP: Failed to generate certificate: ' . $result['message']);
        }
    } else {
        error_log('CIP: Certificate generator function not found');
    }
}

// Activate subscription
function cip_activate_subscription($user_id, $order) {
    $validity_years = get_option('cip_cert_validity_years', 1);
    $end_date = date('Y-m-d H:i:s', strtotime("+{$validity_years} year"));
    
    update_user_meta($user_id, 'cip_subscription_status', 'active');
    update_user_meta($user_id, 'cip_subscription_start', current_time('mysql'));
    update_user_meta($user_id, 'cip_subscription_end', $end_date);
    update_user_meta($user_id, 'cip_subscription_order_id', $order->get_id());
    
    // Get plan name from order
    foreach ($order->get_items() as $item) {
        $product_name = $item->get_name();
        update_user_meta($user_id, 'cip_subscription_plan', $product_name);
        break;
    }
}

// Send certificate email
function cip_send_certificate_email($registration, $cert_result) {
    $to = $registration['email'];
    $subject = 'Your ESG Certificate is Ready!';
    $cert_url = home_url('/cleanindex/certificate');
    
    $message = sprintf(
        "Dear %s,\n\n" .
        "Congratulations! Your ESG certificate has been issued.\n\n" .
        "Certificate Details:\n" .
        "- Certificate Number: %s\n" .
        "- Grade: %s\n" .
        "- Score: %d/100\n\n" .
        "You can view and download your certificate here:\n%s\n\n" .
        "Direct download: %s\n\n" .
        "Thank you for your commitment to ESG excellence!\n\n" .
        "Best regards,\nCleanIndex Team",
        $registration['employee_name'],
        $cert_result['certificate_number'],
        $cert_result['grade'],
        $cert_result['score'],
        $cert_url,
        $cert_result['url']
    );
    
    wp_mail($to, $subject, $message);
}

// Add certificate link to order completion page
add_action('woocommerce_thankyou', 'cip_add_certificate_link_to_thankyou', 10, 1);
function cip_add_certificate_link_to_thankyou($order_id) {
    $cert_url = get_post_meta($order_id, '_cip_certificate_url', true);
    $cert_number = get_post_meta($order_id, '_cip_certificate_number', true);
    
    if ($cert_url && $cert_number) {
        echo '<div style="background: #4CAF50; color: white; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center;">';
        echo '<h2 style="margin: 0 0 10px 0;">🎉 Your Certificate is Ready!</h2>';
        echo '<p style="margin: 0 0 15px 0;">Certificate Number: <strong>' . esc_html($cert_number) . '</strong></p>';
        echo '<a href="' . home_url('/cleanindex/certificate') . '" style="background: white; color: #4CAF50; padding: 12px 24px; border-radius: 6px; text-decoration: none; display: inline-block; font-weight: bold;">View & Download Certificate</a>';
        echo '</div>';
    }
}

// Redirect after payment to certificate page (optional)
add_filter('woocommerce_get_return_url', 'cip_redirect_after_payment', 10, 2);
function cip_redirect_after_payment($return_url, $order) {
    // Check if order has certificate
    $cert_url = get_post_meta($order->get_id(), '_cip_certificate_url', true);
    
    if ($cert_url) {
        // Redirect to certificate page instead of default thank you page
        return home_url('/cleanindex/certificate?order=' . $order->get_id());
    }
    
    return $return_url;
}