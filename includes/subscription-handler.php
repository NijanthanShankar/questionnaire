<?php
/**
 * CleanIndex Portal - Subscription Handler
 * FIX FOR ISSUE #1: Missing cip_subscription_table
 * 
 * FILE LOCATION: includes/subscription-handler.php
 */

if (!defined('ABSPATH')) exit;

/**
 * Create subscriptions table on plugin activation
 * This function should be called during plugin activation
 */
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
    
    error_log('CIP: Subscriptions table created/verified');
}

/**
 * Create a new subscription for a user
 */
function cip_create_subscription($user_id, $company_id, $plan) {
    global $wpdb;
    $table = $wpdb->prefix . 'cip_subscriptions';
    
    // Get validity from settings
    $validity_years = get_option('cip_cert_validity_years', 1);
    $end_date = date('Y-m-d H:i:s', strtotime("+$validity_years year"));
    
    // Insert subscription
    $result = $wpdb->insert($table, [
        'user_id' => $user_id,
        'company_id' => $company_id,
        'plan_name' => $plan['name'],
        'plan_price' => $plan['price'],
        'currency' => isset($plan['currency']) ? $plan['currency'] : 'EUR',
        'status' => 'active',
        'end_date' => $end_date,
        'next_billing_date' => $end_date
    ]);
    
    if ($result === false) {
        error_log('CIP Error: Failed to create subscription - ' . $wpdb->last_error);
        return false;
    }
    
    $subscription_id = $wpdb->insert_id;
    
    // Update user meta
    update_user_meta($user_id, 'cip_subscription_id', $subscription_id);
    update_user_meta($user_id, 'cip_subscription_status', 'active');
    update_user_meta($user_id, 'cip_subscription_plan', $plan['name']);
    update_user_meta($user_id, 'cip_subscription_start', current_time('mysql'));
    update_user_meta($user_id, 'cip_subscription_end', $end_date);
    
    // Create notification if function exists
    if (function_exists('cip_create_notification')) {
        cip_create_notification(
            $user_id,
            'Subscription Activated',
            'Your ' . $plan['name'] . ' subscription is now active for ' . $validity_years . ' year(s).',
            'success',
            home_url('/cleanindex/dashboard')
        );
    }
    
    do_action('cip_subscription_created', $subscription_id, $user_id, $plan);
    
    error_log("CIP: Created subscription #{$subscription_id} for user #{$user_id}");
    
    return $subscription_id;
}

/**
 * Get subscription by user ID
 */
function cip_get_subscription($user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'cip_subscriptions';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
        $user_id
    ), ARRAY_A);
}

/**
 * Get all subscriptions for a user
 */
function cip_get_user_subscriptions($user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'cip_subscriptions';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC",
        $user_id
    ), ARRAY_A);
}

/**
 * Update subscription status
 */
function cip_update_subscription_status($subscription_id, $status) {
    global $wpdb;
    $table = $wpdb->prefix . 'cip_subscriptions';
    
    $valid_statuses = ['active', 'cancelled', 'expired', 'pending'];
    
    if (!in_array($status, $valid_statuses)) {
        return false;
    }
    
    return $wpdb->update(
        $table,
        ['status' => $status, 'updated_at' => current_time('mysql')],
        ['id' => $subscription_id]
    );
}

/**
 * Cancel a subscription
 */
function cip_cancel_subscription($subscription_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'cip_subscriptions';
    
    $subscription = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $subscription_id
    ), ARRAY_A);
    
    if (!$subscription) {
        return false;
    }
    
    // Update subscription status
    $result = $wpdb->update(
        $table,
        ['status' => 'cancelled', 'updated_at' => current_time('mysql')],
        ['id' => $subscription_id]
    );
    
    if ($result !== false) {
        // Update user meta
        update_user_meta($subscription['user_id'], 'cip_subscription_status', 'cancelled');
        
        do_action('cip_subscription_cancelled', $subscription_id, $subscription['user_id']);
        
        error_log("CIP: Cancelled subscription #{$subscription_id}");
    }
    
    return $result;
}

/**
 * Check if subscription is expired and update status
 */
function cip_check_subscription_expiry($subscription_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'cip_subscriptions';
    
    $subscription = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $subscription_id
    ), ARRAY_A);
    
    if (!$subscription) {
        return false;
    }
    
    // Check if expired
    if ($subscription['status'] === 'active' && 
        !empty($subscription['end_date']) && 
        strtotime($subscription['end_date']) < current_time('timestamp')) {
        
        cip_update_subscription_status($subscription_id, 'expired');
        update_user_meta($subscription['user_id'], 'cip_subscription_status', 'expired');
        
        do_action('cip_subscription_expired', $subscription_id, $subscription['user_id']);
        
        error_log("CIP: Subscription #{$subscription_id} marked as expired");
        
        return true;
    }
    
    return false;
}

/**
 * AJAX: Subscribe to a plan directly (without WooCommerce)
 */
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
        wp_send_json_success([
            'message' => 'Subscription activated successfully',
            'subscription_id' => $subscription_id,
            'redirect' => home_url('/cleanindex/dashboard')
        ]);
    } else {
        wp_send_json_error(['message' => 'Failed to create subscription']);
    }
}

/**
 * Cron job to check expired subscriptions
 * Run daily
 */
add_action('cip_daily_subscription_check', 'cip_daily_subscription_check_callback');

function cip_daily_subscription_check_callback() {
    global $wpdb;
    $table = $wpdb->prefix . 'cip_subscriptions';
    
    // Get all active subscriptions that have passed their end date
    $expired = $wpdb->get_results(
        "SELECT * FROM $table 
        WHERE status = 'active' 
        AND end_date IS NOT NULL 
        AND end_date < NOW()",
        ARRAY_A
    );
    
    foreach ($expired as $subscription) {
        cip_update_subscription_status($subscription['id'], 'expired');
        update_user_meta($subscription['user_id'], 'cip_subscription_status', 'expired');
        
        error_log("CIP: Auto-expired subscription #{$subscription['id']}");
    }
    
    if (count($expired) > 0) {
        error_log("CIP: Expired " . count($expired) . " subscriptions");
    }
}

// Schedule the cron job if not already scheduled
if (!wp_next_scheduled('cip_daily_subscription_check')) {
    wp_schedule_event(time(), 'daily', 'cip_daily_subscription_check');
}