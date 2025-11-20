<?php
/**
 * ============================================
 * SOLUTION 1: DIRECT SUBSCRIPTION SYSTEM
 * (Without WooCommerce)
 * ============================================
 * 
 * ADD THIS TO: includes/subscription-handler.php (NEW FILE)
 */

// 1. CREATE SUBSCRIPTION TABLE ON ACTIVATION
function cip_create_subscriptions_table() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    $table = $wpdb->prefix . 'cip_subscriptions';
    
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        company_id INT NOT NULL,
        plan_name VARCHAR(100) NOT NULL,
        plan_price DECIMAL(10, 2) NOT NULL,
        currency VARCHAR(3) DEFAULT 'EUR',
        status ENUM('active', 'cancelled', 'expired', 'pending') DEFAULT 'pending',
        payment_method VARCHAR(50),
        transaction_id VARCHAR(255),
        start_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        end_date DATETIME,
        next_billing_date DATETIME,
        auto_renew TINYINT(1) DEFAULT 1,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_status (status),
        INDEX idx_end_date (end_date)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    error_log('CIP: Subscriptions table created');
}

// 2. SUBSCRIPTION MANAGEMENT FUNCTIONS
function cip_create_subscription($user_id, $company_id, $plan) {
    global $wpdb;
    $table = $wpdb->prefix . 'cip_subscriptions';
    
    $validity_years = get_option('cip_cert_validity_years', 1);
    $end_date = date('Y-m-d H:i:s', strtotime("+$validity_years year"));
    
    $wpdb->insert($table, [
        'user_id' => $user_id,
        'company_id' => $company_id,
        'plan_name' => $plan['name'],
        'plan_price' => $plan['price'],
        'currency' => $plan['currency'],
        'status' => 'active',
        'end_date' => $end_date,
        'next_billing_date' => $end_date
    ]);
    
    $subscription_id = $wpdb->insert_id;
    
    // Update user meta
    update_user_meta($user_id, 'cip_subscription_id', $subscription_id);
    update_user_meta($user_id, 'cip_subscription_status', 'active');
    update_user_meta($user_id, 'cip_subscription_plan', $plan['name']);
    update_user_meta($user_id, 'cip_subscription_start', current_time('mysql'));
    update_user_meta($user_id, 'cip_subscription_end', $end_date);
    
    // Create notification
    cip_create_notification(
        $user_id,
        'Subscription Activated',
        'Your ' . $plan['name'] . ' subscription is now active for ' . $validity_years . ' year(s).',
        'success',
        home_url('/cleanindex/dashboard')
    );
    
    do_action('cip_subscription_created', $subscription_id, $user_id, $plan);
    
    return $subscription_id;
}

function cip_get_subscription($user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'cip_subscriptions';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
        $user_id
    ), ARRAY_A);
}

function cip_cancel_subscription($subscription_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'cip_subscriptions';
    
    return $wpdb->update(
        $table,
        ['status' => 'cancelled', 'updated_at' => current_time('mysql')],
        ['id' => $subscription_id]
    );
}

// 3. AJAX HANDLER FOR DIRECT SUBSCRIPTION
add_action('wp_ajax_cip_subscribe_plan', 'cip_ajax_subscribe_plan');

function cip_ajax_subscribe_plan() {
    check_ajax_referer('cip_subscribe', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Please login first']);
        return;
    }
    
    $user_id = get_current_user_id();
    $plan_index = intval($_POST['plan_index']);
    
    $pricing_plans = get_option('cip_pricing_plans', []);
    
    if (!isset($pricing_plans[$plan_index])) {
        wp_send_json_error(['message' => 'Invalid plan']);
        return;
    }
    
    $plan = $pricing_plans[$plan_index];
    
    // Get user registration/company
    global $wpdb;
    $table_registrations = $wpdb->prefix . 'company_registrations';
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_registrations WHERE email = %s",
        wp_get_current_user()->user_email
    ), ARRAY_A);
    
    if (!$registration) {
        wp_send_json_error(['message' => 'Company not found']);
        return;
    }
    
    // Create subscription
    $subscription_id = cip_create_subscription($user_id, $registration['id'], $plan);
    
    if ($subscription_id) {
        // Auto-generate certificate if assessment is complete
        $table_assessments = $wpdb->prefix . 'company_assessments';
        $assessment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_assessments WHERE user_id = %d AND progress >= 5",
            $registration['id']
        ), ARRAY_A);
        
        if ($assessment && class_exists('CIP_PDF_Generator')) {
            $cert_mode = get_option('cip_cert_grading_mode', 'automatic');
            
            if ($cert_mode === 'automatic') {
                $assessment_data = json_decode($assessment['assessment_json'], true);
                $grade = CIP_PDF_Generator::calculate_grade($assessment_data);
                $result = CIP_PDF_Generator::generate_certificate_pdf($registration['id'], $grade);
                
                if ($result['success']) {
                    // Send certificate email
                    $subject = 'Your ESG Certificate - CleanIndex';
                    $message = "Congratulations! Your ESG certificate (Grade: {$grade}) has been generated.\n\n";
                    $message .= "Download: " . $result['url'] . "\n";
                    $message .= "Certificate Number: " . $result['cert_number'];
                    
                    wp_mail(
                        wp_get_current_user()->user_email,
                        $subject,
                        $message,
                        ['Content-Type: text/plain; charset=UTF-8']
                    );
                }
            }
        }
        
        wp_send_json_success([
            'message' => 'Subscription activated successfully!',
            'subscription_id' => $subscription_id,
            'redirect' => home_url('/cleanindex/dashboard')
        ]);
    } else {
        wp_send_json_error(['message' => 'Failed to create subscription']);
    }
}