<?php 
/**
 * ============================================
 * FILE 5: checkout.php (NEW)
 * ============================================
 */

if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
    wp_redirect(home_url('/cleanindex/login'));
    exit;
}

// Handle checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cip_checkout_nonce'])) {
    if (wp_verify_nonce($_POST['cip_checkout_nonce'], 'cip_checkout')) {
        $plan_index = intval($_POST['plan_index']);
        $pricing_plans = get_option('cip_pricing_plans', []);
        
        if (isset($pricing_plans[$plan_index])) {
            $selected_plan = $pricing_plans[$plan_index];
            
            // Store selected plan in session
            WP_Session_Tokens::get_instance(get_current_user_id());
            update_user_meta(get_current_user_id(), 'cip_selected_plan', $plan_index);
        }
    }
}

$selected_plan_index = get_user_meta(get_current_user_id(), 'cip_selected_plan', true);
$pricing_plans = get_option('cip_pricing_plans', []);
$selected_plan = $pricing_plans[$selected_plan_index] ?? null;

if (!$selected_plan) {
    wp_redirect(home_url('/cleanindex/pricing'));
    exit;
}

$payment_gateway = get_option('cip_payment_gateway', 'stripe');

?>
/**
 * ============================================
 * FILE 6: payment-handler.php (NEW)
 * ============================================
 */

if (!defined('ABSPATH')) exit;

// Payment processing AJAX handler
add_action('wp_ajax_cip_process_payment', 'cip_process_payment');

function cip_process_payment() {
    check_ajax_referer('cip_payment', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Not authenticated']);
        return;
    }
    
    $payment_gateway = get_option('cip_payment_gateway', 'stripe');
    $plan_index = intval($_POST['plan_index']);
    $pricing_plans = get_option('cip_pricing_plans', []);
    
    if (!isset($pricing_plans[$plan_index])) {
        wp_send_json_error(['message' => 'Invalid plan']);
        return;
    }
    
    $plan = $pricing_plans[$plan_index];
    
    if ($payment_gateway === 'stripe') {
        cip_process_stripe_payment($plan, $_POST['payment_method_id']);
    } elseif ($payment_gateway === 'paypal') {
        cip_process_paypal_payment($plan);
    }
}

function cip_process_stripe_payment($plan, $payment_method_id) {
    require_once CIP_PLUGIN_DIR . 'vendor/autoload.php';
    
    \Stripe\Stripe::setApiKey(get_option('cip_stripe_secret_key'));
    
    try {
        $amount = intval($plan['price']) * 100; // Convert to cents
        
        $payment_intent = \Stripe\PaymentIntent::create([
            'amount' => $amount,
            'currency' => strtolower($plan['currency']),
            'payment_method' => $payment_method_id,
            'confirm' => true,
            'description' => 'CleanIndex ' . $plan['name'] . ' Plan',
            'metadata' => [
                'user_id' => get_current_user_id(),
                'plan_name' => $plan['name']
            ]
        ]);
        
        if ($payment_intent->status === 'succeeded') {
            // Update user subscription status
            update_user_meta(get_current_user_id(), 'cip_subscription_status', 'active');
            update_user_meta(get_current_user_id(), 'cip_subscription_plan', $plan['name']);
            update_user_meta(get_current_user_id(), 'cip_subscription_start', current_time('mysql'));
            update_user_meta(get_current_user_id(), 'cip_payment_id', $payment_intent->id');
            
            // Get user registration
            $user = wp_get_current_user();
            global $wpdb;
            $table_registrations = $wpdb->prefix . 'company_registrations';
            $registration = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_registrations WHERE email = %s",
                $user->user_email
            ), ARRAY_A);
            
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
                    $assessment_data = json_decode($assessment['assessment_json'], true);
                    $grade = CIP_PDF_Generator::calculate_grade($assessment_data);
                    CIP_PDF_Generator::generate_certificate_pdf($registration['id'], $grade);
                }
            }
            
            wp_send_json_success(['message' => 'Payment successful']);
        } else {
            wp_send_json_error(['message' => 'Payment failed']);
        }
    } catch (Exception $e) {
        error_log('CleanIndex Payment Error: ' . $e->getMessage());
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}
